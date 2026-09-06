<?php

namespace App\Console\Commands;

use App\Actions\TenantDomains\ReconcileTenantDomain;
use App\Enums\TenantDomainStatus;
use App\Models\TenantDomain;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:reconcile-tenant-domains')]
#[Description('Verifies, provisions and activates pending tenant domains')]
class ReconcileTenantDomains extends Command
{
    public function handle(ReconcileTenantDomain $reconcile): int
    {
        $domains = TenantDomain::query()
            ->whereIn('status', [TenantDomainStatus::Pending, TenantDomainStatus::Verified])
            ->where('kind', 'management')
            ->cursor();

        foreach ($domains as $domain) {
            try {
                $result = $reconcile->handle($domain);
                $this->line($domain->hostname.': '.$result->status->value.' (SSL '.$result->ssl_status.')');
            } catch (Throwable $exception) {
                report($exception);
                $this->warn($domain->hostname.': '.$exception->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
