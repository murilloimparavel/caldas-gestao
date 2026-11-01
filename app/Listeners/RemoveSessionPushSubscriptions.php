<?php

namespace App\Listeners;

use App\Models\PushSubscription;
use Illuminate\Auth\Events\Logout;

class RemoveSessionPushSubscriptions
{
    public function handle(Logout $event): void
    {
        if ($event->user === null || ! request()->hasSession() || ! request()->session()->has('push_session_key')) {
            return;
        }

        PushSubscription::query()->where('user_id', $event->user->getAuthIdentifier())
            ->where('session_hash', hash('sha256', request()->session()->get('push_session_key')))->delete();
    }
}
