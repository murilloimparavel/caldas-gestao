<?php

namespace App\Console\Commands;

use App\Support\SaaSBillingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:reconcile')]
#[Description('Expire SaaS trials and grace periods that are past due')]
final class ReconcileSaaSBilling extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SaaSBillingService $billing): int
    {
        $this->components->info('Expired '.$billing->expireDue().' SaaS subscription(s).');

        return self::SUCCESS;
    }
}
