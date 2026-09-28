<?php

namespace App\Rules;

use App\Enums\OnlineBookingHandleStatus;
use App\Models\OnlineBookingHandle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

final class AvailableOnlineBookingHandle implements ValidationRule
{
    public function __construct(private readonly ?string $tenantId, private readonly ?string $unitId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $handle = Str::lower(trim((string) $value));
        $reservedHandles = collect((array) config('online_booking.reserved_handles', []))
            ->map(fn (mixed $reserved): string => Str::lower((string) $reserved));

        if ($reservedHandles->contains($handle)) {
            $fail('Este identificador é reservado pelo sistema.');

            return;
        }

        $existing = OnlineBookingHandle::query()->where('handle', $handle)->first();

        if ($existing === null) {
            return;
        }

        if ($existing->status === OnlineBookingHandleStatus::Redirect) {
            if ($existing->isRedirectActive()) {
                $fail('Este identificador foi usado recentemente e ainda está reservado para redirecionamento.');
            }

            return;
        }

        if ($existing->tenant_id !== $this->tenantId || $existing->unit_id !== $this->unitId) {
            $fail('Este identificador público já está sendo usado por outra barbearia.');
        }
    }
}
