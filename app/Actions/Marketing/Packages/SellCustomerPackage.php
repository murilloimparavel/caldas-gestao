<?php

namespace App\Actions\Marketing\Packages;

use App\Actions\Operational\OperationalAction;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\PackageTemplate;
use App\Models\Sale;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SellCustomerPackage extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): CustomerPackage
    {
        $unit = $this->unit($actor, $context, 'package.sell');

        $customerId = (string) ($data['customer_id'] ?? '');
        $templateId = (string) ($data['package_template_id'] ?? '');
        $saleId = isset($data['sale_id']) && $data['sale_id'] !== '' ? (string) $data['sale_id'] : null;

        $customer = Customer::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->whereKey($customerId)
            ->first();

        if ($customer === null) {
            throw new InvalidArgumentException('The selected customer was not found in this unit.');
        }

        $template = PackageTemplate::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->whereKey($templateId)
            ->first();

        if ($template === null) {
            throw new InvalidArgumentException('The selected package template was not found in this unit.');
        }

        if (! $template->is_active) {
            throw new InvalidArgumentException('The selected package template is not active.');
        }

        if ($saleId !== null) {
            $saleExists = Sale::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($saleId)
                ->exists();

            if (! $saleExists) {
                throw new InvalidArgumentException('The associated sale was not found in this unit.');
            }
        }

        $totalSessions = isset($data['total_sessions']) && (int) $data['total_sessions'] > 0
            ? (int) $data['total_sessions']
            : $template->total_sessions;

        $expiresAt = null;
        if (! empty($data['expires_at'])) {
            $expiresAt = Carbon::parse($data['expires_at'])->toDateString();
        } elseif ($template->validity_days > 0) {
            $expiresAt = now()->addDays($template->validity_days)->toDateString();
        }

        return DB::transaction(function () use ($actor, $context, $unit, $customer, $template, $saleId, $totalSessions, $expiresAt): CustomerPackage {
            $customerPackage = CustomerPackage::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'customer_id' => $customer->getKey(),
                'package_template_id' => $template->getKey(),
                'sale_id' => $saleId,
                'total_sessions' => $totalSessions,
                'remaining_sessions' => $totalSessions,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'lock_version' => 0,
            ]);

            $this->events->record($actor, $context, 'customer_package.sold', $customerPackage, [
                'customer_id' => $customer->getKey(),
                'package_template_id' => $template->getKey(),
                'sale_id' => $saleId,
                'total_sessions' => $totalSessions,
                'remaining_sessions' => $totalSessions,
                'expires_at' => $expiresAt,
                'status' => 'active',
            ]);

            return $customerPackage->load(['packageTemplate.services', 'customer']);
        }, 5);
    }
}
