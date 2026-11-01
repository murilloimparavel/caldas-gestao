<?php

namespace App\Support;

use App\Models\PushSubscription;
use Carbon\CarbonInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushSender
{
    public function available(): bool
    {
        return (bool) config('webpush.enabled') && (bool) config('webpush.vapid.public_key') && (bool) config('webpush.vapid.private_key') && (bool) config('webpush.vapid.subject') && class_exists(WebPush::class);
    }

    /** @param array{title: string, body: string, url: string, tag: string} $payload */
    public function send(PushSubscription $subscription, array $payload, ?CarbonInterface $expiresAt = null): bool
    {
        if (! $this->available()) {
            return false;
        }
        try {
            $push = new WebPush(['VAPID' => ['subject' => config('webpush.vapid.subject'), 'publicKey' => config('webpush.vapid.public_key'), 'privateKey' => config('webpush.vapid.private_key')]], [], new Client(['timeout' => 5, 'connect_timeout' => 3, 'allow_redirects' => false]));
            $report = $push->sendOneNotification(Subscription::create(['endpoint' => $subscription->endpoint, 'publicKey' => $subscription->public_key, 'authToken' => $subscription->auth_token, 'contentEncoding' => $subscription->content_encoding]), json_encode($payload, JSON_THROW_ON_ERROR), ['TTL' => $expiresAt ? max(0, (int) now()->diffInSeconds($expiresAt, false)) : 300]);
            if ($report->isSubscriptionExpired()) {
                $subscription->delete();
            }

            return $report->isSuccess();
        } catch (Throwable $exception) {
            Log::warning('Web push delivery failed.', ['subscription_id' => $subscription->getKey(), 'exception_class' => $exception::class]);

            return false;
        }
    }
}
