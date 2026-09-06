<?php

namespace App\Actions\Professionals;

use App\Actions\Operational\OperationalAction;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class CreateProfessional extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): Professional
    {
        $unit = $this->unit($actor, $context, 'professional.manage');

        return DB::transaction(function () use ($actor, $context, $data, $unit): Professional {
            $serviceIds = $data['service_ids'] ?? [];
            unset($data['service_ids']);

            /** @var UploadedFile|null $avatarFile */
            $avatarFile = $data['avatar'] ?? $data['avatar_file'] ?? null;
            unset($data['avatar'], $data['avatar_file']);

            $professionalId = (string) Str::uuid7();
            $avatarPath = null;
            if ($avatarFile instanceof UploadedFile) {
                $hash = Str::random(40);
                $ext = $avatarFile->guessExtension() ?: $avatarFile->getClientOriginalExtension();
                $diskName = (string) config('filesystems.media_disk', 'public');
                $storedPath = Storage::disk($diskName)->putFileAs(
                    "{$context->tenant->getKey()}/professionals/{$professionalId}",
                    $avatarFile,
                    "{$hash}.{$ext}"
                );
                $avatarPath = $storedPath !== false ? $storedPath : null;
            }

            $professional = new Professional;
            $professional->id = $professionalId;
            $professional->tenant_id = $context->tenant->getKey();
            $professional->unit_id = $unit->getKey();
            $professional->avatar_path = $avatarPath;
            $professional->lock_version = 0;
            $professional->fill($data);
            $professional->save();
            $this->syncServices($professional, $context, $serviceIds);
            $this->events->record($actor, $context, 'professional.created', $professional, [
                'status' => $professional->status,
                'service_ids' => $serviceIds,
            ]);

            return $professional->load('services');
        }, 5);
    }

    /** @param list<string> $serviceIds */
    private function syncServices(Professional $professional, TenantContext $context, array $serviceIds): void
    {
        $serviceIds = array_values(array_unique($serviceIds));
        if (count($serviceIds) !== Service::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $context->unit?->getKey())->whereIn('id', $serviceIds)->count()) {
            throw new \InvalidArgumentException('Each service must belong to the active unit.');
        }
        $professional->services()->syncWithPivotValues($serviceIds, ['tenant_id' => $context->tenant->getKey(), 'unit_id' => $context->unit?->getKey()]);
    }
}
