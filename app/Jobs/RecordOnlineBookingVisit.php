<?php

namespace App\Jobs;

use App\Models\OnlineBookingVisit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RecordOnlineBookingVisit implements ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $attributes */
    public function __construct(public readonly array $attributes)
    {
        $this->onQueue('analytics');
    }

    public function handle(): void
    {
        OnlineBookingVisit::query()->create($this->attributes);
    }
}
