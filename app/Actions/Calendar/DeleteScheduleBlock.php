<?php

namespace App\Actions\Calendar;

use App\Actions\Operational\OperationalAction;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DeleteScheduleBlock extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, ScheduleBlock $block, int $lockVersion): ScheduleBlock
    {
        $unit = $this->unit($actor, $context, 'calendar.manage');
        if ($block->tenant_id !== $context->tenant->getKey() || $block->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The schedule block belongs to another workspace.');
        }
        if (! $this->professionalScope->ownsScheduleBlock($context, $block)) {
            throw new AuthorizationException('O bloqueio não pertence ao profissional vinculado.');
        }

        return DB::transaction(function () use ($actor, $context, $block, $lockVersion): ScheduleBlock {
            $locked = ScheduleBlock::query()->whereKey($block->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $lockVersion) {
                throw new ConflictHttpException('The schedule block was modified concurrently.');
            }
            if ($locked->status === 'cancelled') {
                return $locked;
            }
            $locked->forceFill(['status' => 'cancelled', 'lock_version' => $locked->lock_version + 1])->save();
            $this->events->record($actor, $context, 'schedule_block.cancelled', $locked, ['status' => $locked->status, 'lock_version' => $locked->lock_version]);

            return $locked->fresh('professional');
        }, 5);
    }
}
