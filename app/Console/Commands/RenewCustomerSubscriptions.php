<?php

namespace App\Console\Commands;

use App\Actions\Marketing\Subscriptions\RenewSubscriptionCycle;
use App\Models\CustomerSubscription;
use App\Models\Membership;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:renew-customer-subscriptions {--limit=500 : Maximum subscriptions to process} {--date= : Processing date in Y-m-d format}')]
#[Description('Close due customer subscription cycles and open their next internal cycle')]
final class RenewCustomerSubscriptions extends Command
{
    public function handle(RenewSubscriptionCycle $renew): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $asOf = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'))->startOfDay()
            : now()->toImmutable();
        $renewed = 0;
        CustomerSubscription::query()->where('status', 'active')->whereDate('next_billing_date', '<=', $asOf->toDateString())->orderBy('id')->limit($limit)->get()->each(function (CustomerSubscription $subscription) use ($renew, $asOf, &$renewed): void {
            $membership = Membership::query()->where('tenant_id', $subscription->tenant_id)->where('status', 'active')->with('user')->first();
            $actor = $membership?->user;
            if (! $actor instanceof User) {
                return;
            }
            try {
                $context = TenantContext::forUser($actor, $subscription->tenant_id, $subscription->unit_id);
                $result = $renew->handle($actor, $context, $subscription, $asOf);
                $renewed += $result !== null && $result->starts_on->isSameDay($asOf) ? 1 : 0;
            } catch (\Throwable $exception) {
                $this->components->warn('Subscription '.$subscription->getKey().' failed: '.$exception->getMessage());
            }
        });
        $this->components->info("Renewed {$renewed} customer subscription cycle(s).");

        return self::SUCCESS;
    }
}
