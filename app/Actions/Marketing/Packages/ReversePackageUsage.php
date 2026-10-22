<?php

namespace App\Actions\Marketing\Packages;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerPackage;
use App\Models\PackageUsage;
use App\Models\PackageUsageReservation;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\OutboxEventStore;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ReversePackageUsage extends OperationalAction
{
    public function __construct(
        AuthorizationService $authorization = new AuthorizationService,
        IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
        private readonly MutateCustomerPackageBalance $packageBalance = new MutateCustomerPackageBalance,
    ) {
        parent::__construct($authorization, $events);
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, PackageUsage $packageUsage, array $data = []): PackageUsage
    {
        $unit = $this->unit($actor, $context, 'package.consume');

        if ($packageUsage->tenant_id !== $context->tenant->getKey() || $packageUsage->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The package usage belongs to another workspace.');
        }

        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '') {
            throw new \InvalidArgumentException('A reversal reason is required.');
        }

        return DB::transaction(function () use ($actor, $context, $unit, $packageUsage, $reason): PackageUsage {
            $reservation = PackageUsageReservation::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->where('package_usage_id', $packageUsage->getKey())
                ->lockForUpdate()
                ->first();

            /** @var PackageUsage $usage */
            $usage = PackageUsage::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($packageUsage->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($usage->reversed_at !== null) {
                throw new ConflictHttpException('Package usage has already been reversed.');
            }

            /** @var CustomerPackage $package */
            $package = CustomerPackage::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($usage->customer_package_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reservation !== null && $reservation->status !== 'consumed') {
                throw new ConflictHttpException('The reservation is not in a consumable state.');
            }

            if ($reservation !== null && (
                $reservation->customer_package_id !== $usage->customer_package_id
                || $reservation->service_id !== $usage->service_id
                || $reservation->sessions_reserved !== $usage->sessions_consumed
            )) {
                throw new ConflictHttpException('The reservation does not match the package usage.');
            }

            if (in_array($package->status, ['cancelled', 'expired'], true)) {
                throw new ConflictHttpException("Package cannot be reactivated from {$package->status} status.");
            }

            if ($package->expires_at !== null && $package->expires_at->endOfDay()->isPast()) {
                throw new ConflictHttpException('Expired packages cannot have usage reversed.');
            }

            $package = $this->packageBalance->restore(
                $package,
                $context->tenant->getKey(),
                $unit->getKey(),
                $usage->service_id,
                $usage->sessions_consumed,
                $package->status === 'review_required' ? 'review_required' : 'active',
                $usage->getKey(),
            );
            $newRemaining = $package->remaining_sessions;

            $usage->forceFill([
                'reversed_at' => now(),
                'reversed_by_user_id' => $actor->getKey(),
                'reversal_reason' => $reason,
            ])->save();

            if ($reservation !== null) {
                $reservation->forceFill([
                    'status' => 'released',
                    'released_at' => now(),
                    'release_reason' => $reason,
                ])->save();
            }

            $metadata = [
                'customer_package_id' => $package->getKey(),
                'package_usage_id' => $usage->getKey(),
                'sessions_consumed' => $usage->sessions_consumed,
                'remaining_sessions' => $newRemaining,
                'status' => $package->status,
                'reason' => $reason,
            ];

            $this->events->record($actor, $context, 'customer_package.usage_reversed', $package, $metadata);
            $this->events->record($actor, $context, 'package_usage.reversed', $usage, $metadata);

            return $usage->fresh(['customerPackage', 'user']);
        }, 5);
    }
}
