<?php

namespace App\Actions\Marketing\Packages;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\PackageUsage;
use App\Models\PackageUsageReservation;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ReversePackageUsage extends OperationalAction
{
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

            $reservation = PackageUsageReservation::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->where('package_usage_id', $usage->getKey())
                ->lockForUpdate()
                ->first();

            if ($reservation !== null && $reservation->status !== 'consumed') {
                throw new ConflictHttpException('The reservation is not in a consumable state.');
            }

            if (in_array($package->status, ['cancelled', 'expired'], true)) {
                throw new ConflictHttpException("Package cannot be reactivated from {$package->status} status.");
            }

            if ($package->expires_at !== null && $package->expires_at->endOfDay()->isPast()) {
                throw new ConflictHttpException('Expired packages cannot have usage reversed.');
            }

            $newRemaining = $package->remaining_sessions + $usage->sessions_consumed;

            if ($newRemaining > $package->total_sessions) {
                throw new ConflictHttpException('Package session balance is inconsistent.');
            }

            $package->forceFill([
                'remaining_sessions' => $newRemaining,
                'status' => 'active',
                'lock_version' => $package->lock_version + 1,
            ])->save();

            if ($usage->service_id !== null) {
                $serviceBalance = CustomerPackageService::query()
                    ->where('tenant_id', $context->tenant->getKey())
                    ->where('unit_id', $unit->getKey())
                    ->where('customer_package_id', $package->getKey())
                    ->where('service_id', $usage->service_id)
                    ->lockForUpdate()
                    ->first();

                if ($serviceBalance !== null) {
                    if ($serviceBalance->remaining_quantity + $usage->sessions_consumed > $serviceBalance->allocated_quantity) {
                        throw new ConflictHttpException('Package service balance is inconsistent.');
                    }

                    $serviceBalance->increment('remaining_quantity', $usage->sessions_consumed);
                }
            }

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
                'status' => 'active',
                'reason' => $reason,
            ];

            $this->events->record($actor, $context, 'customer_package.usage_reversed', $package, $metadata);
            $this->events->record($actor, $context, 'package_usage.reversed', $usage, $metadata);

            return $usage->fresh(['customerPackage', 'user']);
        }, 5);
    }
}
