<?php

namespace App\Actions\Calendar;

use App\Actions\Operational\OperationalAction;
use App\Models\Professional;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateScheduleBlock extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): ScheduleBlock
    {
        $unit = $this->unit($actor, $context, 'calendar.manage');
        $tenantId = (string) $context->tenant->getKey();
        $unitId = (string) $unit->getKey();
        if ($data['professional_id'] !== null) {
            Professional::query()->whereKey($data['professional_id'])->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('status', 'active')->firstOrFail();
        }
        $professionalId = $this->assertProfessional($context, $data['professional_id'] === null ? null : (string) $data['professional_id']);
        $data['professional_id'] = $professionalId;

        return DB::transaction(function () use ($actor, $context, $data, $tenantId, $unitId): ScheduleBlock {
            $block = ScheduleBlock::query()->create([
                'id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'unit_id' => $unitId,
                'professional_id' => $data['professional_id'], 'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'], 'timezone' => $data['timezone'],
                'reason' => $data['reason'] ?? null, 'status' => 'active', 'lock_version' => 0,
            ]);
            $this->events->record($actor, $context, 'schedule_block.created', $block, ['status' => $block->status]);

            return $block->fresh('professional');
        }, 5);
    }
}
