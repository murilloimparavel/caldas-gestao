<?php

namespace App\Actions\Marketing\Packages;

use App\Actions\Finance\Transactions\CreateFinancialObligation;
use App\Actions\Finance\Transactions\SettleFinancialObligation;
use App\Actions\Operational\OperationalAction;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\PackageTemplate;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SellCustomerPackage extends OperationalAction
{
    public function __construct(
        private readonly CreateFinancialObligation $createFinancialObligation,
        private readonly SettleFinancialObligation $settleFinancialObligation,
    ) {
        parent::__construct();
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): CustomerPackage
    {
        $unit = $this->unit($actor, $context, 'package.sell');
        $this->unit($actor, $context, 'financial.manage');
        $this->unit($actor, $context, 'financial.settle');

        $customerId = (string) ($data['customer_id'] ?? '');
        $templateId = (string) ($data['package_template_id'] ?? '');
        $saleId = isset($data['sale_id']) && $data['sale_id'] !== '' ? (string) $data['sale_id'] : null;

        $customer = Customer::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->whereKey($customerId)
            ->first();

        if ($customer === null) {
            throw new \InvalidArgumentException('The selected customer was not found in this unit.');
        }

        $template = PackageTemplate::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->whereKey($templateId)
            ->with('services:id,name')
            ->first();

        if ($template === null) {
            throw new \InvalidArgumentException('The selected package template was not found in this unit.');
        }

        if (! $template->is_active) {
            throw new \InvalidArgumentException('The selected package template is not active.');
        }

        if ($saleId !== null) {
            $saleExists = Sale::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->where('customer_id', $customer->getKey())
                ->whereKey($saleId)
                ->exists();

            if (! $saleExists) {
                throw new \InvalidArgumentException('The associated sale was not found in this unit.');
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

        $serviceAllocations = $template->services
            ->map(static fn (Service $service): array => [
                'id' => (string) $service->getKey(),
                'name' => (string) $service->name,
                'quantity' => (int) ($service->pivot->included_quantity ?? 1),
            ])
            ->values()
            ->all();

        $paymentMethod = trim((string) ($data['payment_method'] ?? ''));

        return DB::transaction(function () use ($actor, $context, $unit, $customer, $template, $saleId, $totalSessions, $expiresAt, $serviceAllocations, $paymentMethod): CustomerPackage {
            $customerPackage = CustomerPackage::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'customer_id' => $customer->getKey(),
                'package_template_id' => $template->getKey(),
                'sale_id' => $saleId,
                'name_snapshot' => $template->name,
                'price_cents_snapshot' => $template->price_cents,
                'total_sessions_snapshot' => $totalSessions,
                'validity_days_snapshot' => $template->validity_days,
                'eligible_services_snapshot' => $serviceAllocations,
                'total_sessions' => $totalSessions,
                'remaining_sessions' => $totalSessions,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'lock_version' => 0,
            ]);

            foreach ($serviceAllocations as $allocation) {
                CustomerPackageService::query()->create([
                    'tenant_id' => $context->tenant->getKey(),
                    'unit_id' => $unit->getKey(),
                    'customer_package_id' => $customerPackage->getKey(),
                    'service_id' => $allocation['id'],
                    'allocated_quantity' => $allocation['quantity'],
                    'remaining_quantity' => $allocation['quantity'],
                ]);
            }

            $financialObligation = null;
            $priceCents = (int) ($customerPackage->price_cents_snapshot ?? 0);

            if ($priceCents > 0) {
                $financialObligation = $this->createFinancialObligation->handle($actor, $context, [
                    'type' => 'receivable',
                    'customer_id' => $customer->getKey(),
                    'customer_package_id' => $customerPackage->getKey(),
                    'description' => 'Venda de pacote: '.($customerPackage->name_snapshot ?? $template->name),
                    'amount_cents' => $priceCents,
                    'due_date' => now()->toDateString(),
                    'notes' => 'Pagamento informado pelo operador na atribuição do pacote.',
                ]);

                $financialObligation = $this->settleFinancialObligation->handle($actor, $context, $financialObligation, [
                    'paid_date' => now()->toDateString(),
                    'payment_method' => $paymentMethod,
                    'lock_version' => $financialObligation->lock_version,
                ]);
            }

            $this->events->record($actor, $context, 'customer_package.sold', $customerPackage, [
                'customer_id' => $customer->getKey(),
                'package_template_id' => $template->getKey(),
                'sale_id' => $saleId,
                'price_cents' => $template->price_cents,
                'service_ids' => $template->services->modelKeys(),
                'total_sessions' => $totalSessions,
                'validity_days' => $template->validity_days,
                'remaining_sessions' => $totalSessions,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'financial_obligation_id' => $financialObligation?->getKey(),
                'payment_method' => $financialObligation?->payment_method,
            ]);

            return $customerPackage->load(['packageTemplate.services', 'customer', 'financialObligation']);
        }, 5);
    }
}
