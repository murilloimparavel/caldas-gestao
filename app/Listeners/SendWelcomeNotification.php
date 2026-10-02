<?php

namespace App\Listeners;

use App\Notifications\WelcomeUser;
use Illuminate\Auth\Events\Registered;

final class SendWelcomeNotification
{
    public function handle(Registered $event): void
    {
        $event->user->notify(new WelcomeUser);
    }
}
