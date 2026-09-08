<?php

namespace App\Actions\Professionals;

use App\Actions\Operational\OperationalAction;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use App\Support\Images\UploadedImageOptimizer;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateProfessional extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, Professional $professional, array $data, ?int $expectedVersion = null): Professional
    {
        $expectedVersion ??= isset($data['lock_version']) ? (int) $data['lock_version'] : null;
        unset($data['lock_version']);
        $unit = $this->unit($actor, $context, 'professional.manage');
        if ($professional->tenant_id !== $context->tenant->getKey() || $professional->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The professional belongs to another workspace.');
        }
        if ($expectedVersion === null) {
            throw new ConflictHttpException('The professional lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $professional, $data, $expectedVersion): Professional {
            $serviceIds = $data['service_ids'] ?? null;
            unset($data['service_ids']);

            $hasAvatarKey = array_key_exists('avatar', $data) || array_key_exists('avatar_file', $data);
            /** @var UploadedFile|null $avatarFile */
            $avatarFile = $data['avatar'] ?? $data['avatar_file'] ?? null;
            unset($data['avatar'], $data['avatar_file']);

            $locked = Professional::query()->whereKey($professional->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The professional was modified concurrently.');
            }

            if ($hasAvatarKey) {
                $diskName = (string) config('filesystems.media_disk');
                if ($avatarFile instanceof UploadedFile) {
                    if ($locked->avatar_path) {
                        Storage::disk($diskName)->delete($locked->avatar_path);
                    }
                    $path = "{$context->tenant->getKey()}/professionals/{$locked->getKey()}/".Str::random(40).'.webp';
                    $stored = app(UploadedImageOptimizer::class)->storeWebp($avatarFile, Storage::disk($diskName), $path);
                    $data['avatar_path'] = $stored ? $path : null;
                } elseif ($avatarFile === null) {
                    if ($locked->avatar_path) {
                        Storage::disk($diskName)->delete($locked->avatar_path);
                    }
                    $data['avatar_path'] = null;
                }
            }

            $locked->forceFill([...$data, 'lock_version' => $locked->lock_version + 1])->save();
            if ($serviceIds !== null) {
                $serviceIds = array_values(array_unique($serviceIds));
                if (count($serviceIds) !== Service::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $context->unit?->getKey())->whereIn('id', $serviceIds)->count()) {
                    throw new \InvalidArgumentException('Each service must belong to the active unit.');
                }
                $locked->services()->syncWithPivotValues($serviceIds, ['tenant_id' => $context->tenant->getKey(), 'unit_id' => $context->unit?->getKey()]);
            }
            $this->events->record($actor, $context, 'professional.updated', $locked, [
                'status' => $locked->status,
                'lock_version' => $locked->lock_version,
                ...($serviceIds === null ? [] : ['service_ids' => $serviceIds]),
            ]);

            return $locked->fresh()->load('services');
        }, 5);
    }
}
