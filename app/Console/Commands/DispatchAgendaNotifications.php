<?php

namespace App\Console\Commands;

use App\Support\AgendaNotifications;
use App\Support\WebPushSender;
use Illuminate\Console\Command;

class DispatchAgendaNotifications extends Command
{
    protected $signature = 'agenda:dispatch-notifications';

    protected $description = 'Record due agenda reminders and deliver pending web push notifications';

    public function handle(AgendaNotifications $notifications, WebPushSender $sender): int
    {
        $notifications->dispatchReminders();
        if ($sender->available()) {
            $notifications->dispatchPush();
        } else {
            $this->warn('Web push is not configured; reminders are available in the notification center.');
        }

        return self::SUCCESS;
    }
}
