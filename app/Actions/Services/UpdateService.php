<?php

namespace App\Actions\Services;

use App\Actions\Operational\OperationalAction;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateService extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, Service $service, array $data, ?int $expectedVersion = null): Service
    {
        $expectedVersion ??= isset($data['lock_version']) ? (int) $data['lock_version'] : null;
        unset($data['lock_version']);
        $unit = $this->unit($actor, $context, 'service.manage');
        if ($service->tenant_id !== $context->tenant->getKey() || $service->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The service belongs to another workspace.');
        }
        if ($expectedVersion === null) {
            throw new ConflictHttpException('The service lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $service, $data, $expectedVersion): Service {
            $professionalIds = $data['professional_ids'] ?? null;
            unset($data['professional_ids']);

            $hasImageKey = array_key_exists('image', $data) || array_key_exists('image_file', $data);
            /** @var UploadedFile|null $imageFile */
            $imageFile = $data['image'] ?? $data['image_file'] ?? null;
            unset($data['image'], $data['image_file']);

            $locked = Service::query()->whereKey($service->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The service was modified concurrently.');
            }

            if ($hasImageKey) {
                $diskName = 'public';
                if ($imageFile instanceof UploadedFile) {
                    if ($locked->image_path) {
                        Storage::disk($diskName)->delete($locked->image_path);
                    }
                    $hash = Str::random(40);
                    $ext = $imageFile->guessExtension() ?: $imageFile->getClientOriginalExtension();
                    $storedPath = Storage::disk($diskName)->putFileAs(
                        "{$context->tenant->getKey()}/services/{$locked->getKey()}",
                        $imageFile,
                        "{$hash}.{$ext}"
                    );
                    $data['image_path'] = $storedPath !== false ? $storedPath : null;
                } elseif ($imageFile === null) {
                    if ($locked->image_path) {
                        Storage::disk($diskName)->delete($locked->image_path);
                    }
                    $data['image_path'] = null;
                }
            }

            $locked->forceFill([...$data, 'lock_version' => $locked->lock_version + 1])->save();
            if ($professionalIds !== null) {
                $professionalIds = array_values(array_unique($professionalIds));
                if (count($professionalIds) !== Professional::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $context->unit?->getKey())->whereIn('id', $professionalIds)->count()) {
                    throw new \InvalidArgumentException('Each professional must belong to the active unit.');
                }
                $locked->professionals()->syncWithPivotValues($professionalIds, ['tenant_id' => $context->tenant->getKey(), 'unit_id' => $context->unit?->getKey()]);
            }
            $this->events->record($actor, $context, 'service.updated', $locked, [
                'status' => $locked->status,
                'price_cents' => $locked->price_cents,
                'duration_minutes' => $locked->duration_minutes,
                'lock_version' => $locked->lock_version,
                ...($professionalIds === null ? [] : ['professional_ids' => $professionalIds]),
            ]);

            return $locked->fresh()->load('professionals');
        }, 5);
    }
}
