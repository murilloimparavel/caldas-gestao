<?php

namespace App\Actions\Calendar;

use App\Actions\Operational\OperationalAction;
use App\Models\AvailabilityRule;
use App\Models\Professional;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateAvailabilityRule extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): AvailabilityRule
    {
        $unit = $this->unit($actor, $context, 'calendar.configure');
        $tenantId = (string) $context->tenant->getKey();
        $unitId = (string) $unit->getKey();

        Professional::query()
            ->whereKey($data['professional_id'])
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->firstOrFail();
        $this->assertProfessional($context, (string) $data['professional_id']);

        return DB::transaction(function () use ($actor, $context, $data, $tenantId, $unitId): AvailabilityRule {
            $rule = AvailabilityRule::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'professional_id' => $data['professional_id'],
                'weekday' => $data['weekday'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'timezone' => $data['timezone'],
                'status' => $data['status'] ?? 'active',
                'lock_version' => 0,
            ]);
            $this->events->record($actor, $context, 'availability_rule.created', $rule, ['status' => $rule->status]);

            return $rule->fresh('professional');
        }, 5);
    }
}
