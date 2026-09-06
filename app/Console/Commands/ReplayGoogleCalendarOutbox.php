<?php

namespace App\Console\Commands;

use App\Enums\OutboxStatus;
use App\Jobs\SyncGoogleCalendarAppointment;
use App\Models\OutboxEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:replay-google-calendar-outbox {--limit=100}')]
#[Description('Replay appointment outbox events for Google Calendar synchronization')]
class ReplayGoogleCalendarOutbox extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $statuses = [OutboxStatus::Pending, OutboxStatus::Available, OutboxStatus::Retryable];
        $events = OutboxEvent::query()
            ->where('aggregate_type', 'appointment')
            ->whereIn('status', $statuses)
            ->whereIn('event_type', ['appointment.created', 'appointment.updated', 'appointment.cancelled'])
            ->orderBy('id')
            ->limit($limit)
            ->get(['aggregate_id']);

        foreach ($events as $event) {
            SyncGoogleCalendarAppointment::dispatch((string) $event->aggregate_id);
        }

        $this->info(sprintf('Dispatched %d Google Calendar synchronization job(s).', $events->count()));

        return self::SUCCESS;
    }
}
