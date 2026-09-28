<?php

namespace App\Actions\OnlineBooking;

use App\Enums\OnlineBookingHandleStatus;
use App\Models\OnlineBookingHandle;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ManageOnlineBookingHandle
{
    public function reserveForDraft(string $tenantId, string $unitId, string $handle): OnlineBookingHandle
    {
        $handle = Str::lower(trim($handle));
        $this->purgeExpiredHandle($handle);

        $this->releaseDraftReservations($tenantId, $unitId, $handle);

        $existing = OnlineBookingHandle::query()
            ->where('handle', $handle)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof OnlineBookingHandle) {
            if ($existing->unit_id !== $unitId || $existing->tenant_id !== $tenantId) {
                throw new ConflictHttpException('Este identificador público já está sendo usado por outra barbearia.');
            }

            if ($existing->status === OnlineBookingHandleStatus::Redirect) {
                throw new ConflictHttpException('Este identificador foi usado recentemente e ainda está reservado para redirecionamento.');
            }

            return $existing;
        }

        return OnlineBookingHandle::query()->create([
            'id' => (string) Str::uuid7(),
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
            'handle' => $handle,
            'status' => OnlineBookingHandleStatus::Reserved,
        ]);
    }

    public function activateForPublication(string $tenantId, string $unitId, ?string $previousHandle, string $handle): OnlineBookingHandle
    {
        $previousHandle = $previousHandle !== null ? Str::lower(trim($previousHandle)) : null;
        $handle = Str::lower(trim($handle));
        $this->purgeExpiredHandle($handle);

        $desired = OnlineBookingHandle::query()
            ->where('handle', $handle)
            ->lockForUpdate()
            ->first();

        if ($desired instanceof OnlineBookingHandle && ($desired->tenant_id !== $tenantId || $desired->unit_id !== $unitId)) {
            throw new ConflictHttpException('Este identificador público já está sendo usado por outra barbearia.');
        }

        if ($desired?->status === OnlineBookingHandleStatus::Redirect) {
            throw new ConflictHttpException('Este identificador foi usado recentemente e ainda está reservado para redirecionamento.');
        }

        $currentHandles = OnlineBookingHandle::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->current()
            ->lockForUpdate()
            ->get();

        if ($previousHandle !== null && $previousHandle !== $handle && ! $currentHandles->contains('handle', $previousHandle)) {
            $previous = OnlineBookingHandle::query()
                ->where('handle', $previousHandle)
                ->lockForUpdate()
                ->first();

            if ($previous instanceof OnlineBookingHandle && ($previous->tenant_id !== $tenantId || $previous->unit_id !== $unitId)) {
                throw new ConflictHttpException('O identificador da publicação anterior pertence a outra barbearia.');
            }

            $previous ??= OnlineBookingHandle::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'handle' => $previousHandle,
                'status' => OnlineBookingHandleStatus::Current,
            ]);
            $currentHandles->push($previous);
        }

        foreach ($currentHandles as $currentHandle) {
            if ($currentHandle->handle !== $handle) {
                $currentHandle->forceFill([
                    'status' => OnlineBookingHandleStatus::Redirect,
                    'redirect_until' => now()->addDays(3),
                ])->save();
            }
        }

        $desired ??= OnlineBookingHandle::query()->create([
            'id' => (string) Str::uuid7(),
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
            'handle' => $handle,
            'status' => OnlineBookingHandleStatus::Current,
        ]);

        $desired->forceFill([
            'status' => OnlineBookingHandleStatus::Current,
            'redirect_until' => null,
        ])->save();

        OnlineBookingHandle::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->reserved()
            ->where('handle', '!=', $handle)
            ->delete();

        return $desired->fresh();
    }

    private function purgeExpiredHandle(string $handle): void
    {
        OnlineBookingHandle::query()
            ->where('handle', $handle)
            ->where('status', OnlineBookingHandleStatus::Redirect->value)
            ->where('redirect_until', '<=', now())
            ->delete();
    }

    private function releaseDraftReservations(string $tenantId, string $unitId, string $handle): void
    {
        OnlineBookingHandle::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->reserved()
            ->where('handle', '!=', $handle)
            ->delete();
    }
}
