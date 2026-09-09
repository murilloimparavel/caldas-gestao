<?php

namespace App\Console\Commands;

use App\Actions\OnlineBooking\EnsureOnlineBookingSite;
use App\Models\OnlineBookingSetting;
use App\Models\OnlineBookingSite;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('online-booking:backfill {user_id : Authorized user id} {--dry-run : Only report what would be created}')]
#[Description('Provisiona sites e rascunhos do Agendamento Online a partir da configuração legada.')]
final class BackfillOnlineBookingSites extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EnsureOnlineBookingSite $ensure): int
    {
        $actor = User::query()->findOrFail((string) $this->argument('user_id'));
        $units = $actor->memberships()
            ->with(['tenant', 'membershipUnits.unit'])
            ->where('status', 'active')
            ->get()
            ->flatMap(fn ($membership): Collection => $membership->membershipUnits->map(fn ($membershipUnit) => [$membership, $membershipUnit->unit]))
            ->filter(fn (array $pair): bool => $pair[1] !== null && $pair[1]->onlineBookingSetting instanceof OnlineBookingSetting);
        $created = 0;

        foreach ($units as [$membership, $unit]) {
            if (OnlineBookingSite::query()->where('tenant_id', $membership->tenant_id)->where('unit_id', $unit->getKey())->exists()) {
                continue;
            }
            $created++;
            if (! $this->option('dry-run')) {
                $ensure->handle(TenantContext::forUser($actor, $membership->tenant_id, $unit->getKey()));
            }
        }

        $this->info($this->option('dry-run') ? "{$created} site(s) would be provisioned." : "{$created} site(s) provisioned.");

        return self::SUCCESS;
    }
}
