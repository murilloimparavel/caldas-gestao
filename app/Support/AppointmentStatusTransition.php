<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class AppointmentStatusTransition
{
    /** @var list<string> */
    private const CREATABLE = ['draft', 'scheduled', 'confirmed'];

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['scheduled', 'confirmed', 'cancelled'],
        'scheduled' => ['confirmed', 'checked_in', 'no_show', 'cancelled'],
        'confirmed' => ['checked_in', 'no_show', 'cancelled'],
        'checked_in' => ['in_service', 'cancelled'],
        'in_service' => ['completed'],
        'completed' => [],
        'no_show' => [],
        'cancelled' => [],
    ];

    public function assertCanCreate(string $status): void
    {
        if (! in_array($status, self::CREATABLE, true)) {
            throw ValidationException::withMessages([
                'status' => 'An appointment can only be created as draft, scheduled, or confirmed.',
            ]);
        }
    }

    public function assertCanTransition(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "The appointment cannot transition from {$from} to {$to}.",
            ]);
        }
    }
}
