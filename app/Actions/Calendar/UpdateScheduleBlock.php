<?php

namespace App\Actions\Calendar;

use App\Actions\Operational\OperationalAction;
use App\Models\Professional;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateScheduleBlock extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, ScheduleBlock $block, array $data): ScheduleBlock
    {
        $unit = $this->unit($actor, $context, 'calendar.manage');
        if ($block->tenant_id !== $context->tenant->getKey() || $block->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The schedule block belongs to another workspace.');
        }
        if (! $this->professionalScope->ownsScheduleBlock($context, $block)) {
            throw new AuthorizationException('O bloqueio não pertence ao profissional vinculado.');
        }
        if ($data['professional_id'] !== null) {
            Professional::query()->whereKey($data['professional_id'])->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('status', 'active')->firstOrFail();
        }
        $data['professional_id'] = $this->assertProfessional($context, $data['professional_id'] === null ? null : (string) $data['professional_id']);

        return DB::transaction(function () use ($actor, $context, $block, $data): ScheduleBlock {
            $locked = ScheduleBlock::query()->whereKey($block->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== (int) $data['lock_version']) {
                throw new ConflictHttpException('The schedule block was modified concurrently.');
            }
            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages(['schedule_block' => 'Cancelled schedule blocks cannot be edited.']);
            }
            $locked->forceFill([
                'professional_id' => $data['professional_id'], 'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'], 'timezone' => $data['timezone'],
                'reason' => $data['reason'] ?? null, 'status' => $data['status'] ?? $locked->status,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->events->record($actor, $context, 'schedule_block.updated', $locked, ['status' => $locked->status, 'lock_version' => $locked->lock_version]);

            return $locked->fresh('professional');
        }, 5);
    }
}
