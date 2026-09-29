<?php

namespace App\Actions\OnlineBooking;

use App\Enums\OnlineBookingHandleStatus;
use App\Models\OnlineBookingHandle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ManageOnlineBookingHandle
{
    public function reserveForDraft(string $tenantId, string $unitId, string $handle): OnlineBookingHandle
    {
        $handle = self::normalizeHandle($handle);
        $this->purgeExpiredHandle($handle);

        $this->releaseDraftReservations($tenantId, $unitId, $handle);

        $existing = OnlineBookingHandle::query()
            ->where('handle', $handle)
            ->lockForUpdate()
            ->first();

        $existing ??= $this->createHandle($tenantId, $unitId, $handle, OnlineBookingHandleStatus::Reserved);

        if ($existing->unit_id !== $unitId || $existing->tenant_id !== $tenantId) {
            throw new ConflictHttpException('Este identificador público já está sendo usado por outra barbearia.');
        }

        if ($existing->status === OnlineBookingHandleStatus::Redirect) {
            throw new ConflictHttpException('Este identificador foi usado recentemente e ainda está reservado para redirecionamento.');
        }

        return $existing;
    }

    public function activateForPublication(string $tenantId, string $unitId, ?string $previousHandle, string $handle): OnlineBookingHandle
    {
        $previousHandle = $previousHandle !== null ? self::normalizeHandle($previousHandle) : null;
        $handle = self::normalizeHandle($handle);
        $this->purgeExpiredHandle($handle);

        $desired = OnlineBookingHandle::query()
            ->where('handle', $handle)
            ->lockForUpdate()
            ->first();

        $desired ??= $this->createHandle($tenantId, $unitId, $handle, OnlineBookingHandleStatus::Current);

        if ($desired->tenant_id !== $tenantId || $desired->unit_id !== $unitId) {
            throw new ConflictHttpException('Este identificador público já está sendo usado por outra barbearia.');
        }

        if ($desired->status === OnlineBookingHandleStatus::Redirect) {
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

            $previous ??= $this->createHandle($tenantId, $unitId, $previousHandle, OnlineBookingHandleStatus::Current);

            if ($previous->tenant_id !== $tenantId || $previous->unit_id !== $unitId) {
                throw new ConflictHttpException('O identificador da publicação anterior pertence a outra barbearia.');
            }

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

    private static function normalizeHandle(string $handle): string
    {
        return Str::slug(trim($handle));
    }

    private function createHandle(string $tenantId, string $unitId, string $handle, OnlineBookingHandleStatus $status): OnlineBookingHandle
    {
        try {
            return DB::transaction(fn (): OnlineBookingHandle => OnlineBookingHandle::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'handle' => $handle,
                'status' => $status,
            ]));
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = OnlineBookingHandle::query()
                ->where('handle', $handle)
                ->lockForUpdate()
                ->first();

            if (! $existing instanceof OnlineBookingHandle) {
                throw $exception;
            }

            return $existing;
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($sqlState, ['23000', '23505'], true);
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
