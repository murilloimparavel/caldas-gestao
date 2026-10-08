<?php

namespace App\Actions\Sales;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\PackageTemplate;
use App\Models\PackageUsageReservation;
use App\Models\Product;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\LegacyPackageReconciliation;
use App\Support\OutboxEventStore;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AddSaleItem extends OperationalAction
{
    public function __construct(
        AuthorizationService $authorization = new AuthorizationService,
        IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
        private readonly LegacyPackageReconciliation $packageEvidence = new LegacyPackageReconciliation,
    ) {
        parent::__construct($authorization, $events);
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, Sale $sale, array $data, string $permission = 'sale.manage'): SaleItem
    {
        $unit = $this->unit($actor, $context, $permission);
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        if (($data['item_type'] ?? null) === 'service' && ! empty($data['customer_package_id'])
            && ! $this->authorization->can($actor, $context, 'package.consume', $unit)
            && ! $this->authorization->can($actor, $context, 'package.manage', $unit)) {
            throw new AuthorizationException('O ator não pode consumir saldo de pacotes nesta unidade.');
        }

        if (($data['item_type'] ?? null) === 'package'
            && ! $this->authorization->can($actor, $context, 'package.sell', $unit)
            && ! $this->authorization->can($actor, $context, 'package.manage', $unit)) {
            throw new AuthorizationException('O ator não pode vender pacotes nesta unidade.');
        }

        if ($sale->tenant_id !== $tenantId || $sale->unit_id !== $unitId) {
            throw new AuthorizationException('A comanda pertence a outra unidade ou workspace.');
        }

        if (! in_array($sale->status, ['draft', 'open', 'ready_to_bill'], true)) {
            throw ValidationException::withMessages([
                'sale' => 'Apenas comandas em aberto ou em rascunho podem receber novos itens.',
            ]);
        }

        $itemType = (string) ($data['item_type'] ?? 'custom');
        if (! in_array($itemType, ['service', 'product', 'package', 'custom'], true)) {
            throw ValidationException::withMessages([
                'item_type' => 'Tipo de item inválido.',
            ]);
        }

        $sourceId = isset($data['source_id']) && trim((string) $data['source_id']) !== ''
            ? trim((string) $data['source_id'])
            : null;

        return DB::transaction(function () use ($actor, $context, $sale, $data, $itemType, $sourceId, $tenantId, $unitId): SaleItem {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if (isset($data['lock_version']) && $lockedSale->lock_version !== (int) $data['lock_version']) {
                throw new ConflictHttpException('A comanda foi modificada concorrentemente.');
            }

            if (! in_array($lockedSale->status, ['draft', 'open', 'ready_to_bill'], true)) {
                throw ValidationException::withMessages([
                    'sale' => 'Apenas comandas em aberto ou em rascunho podem receber novos itens.',
                ]);
            }

            if ($sourceId !== null) {
                /** @var SaleItem|null $existingSourceItem */
                $existingSourceItem = SaleItem::withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('source_id', $sourceId)
                    ->lockForUpdate()
                    ->first();

                if ($existingSourceItem !== null) {
                    if ((string) $existingSourceItem->sale_id !== (string) $lockedSale->getKey()) {
                        throw ValidationException::withMessages([
                            'source_id' => 'A identidade do item já está vinculada a outra comanda nesta unidade.',
                        ]);
                    }

                    if ($existingSourceItem->trashed()) {
                        $existingSourceItem->restore();
                    }

                    return $existingSourceItem;
                }
            }

            /** @var SaleCategory $category */
            $category = SaleCategory::query()->whereKey($lockedSale->sale_category_id)->firstOrFail();

            if ($category->type === 'service' && $itemType !== 'service') {
                throw ValidationException::withMessages([
                    'item_type' => 'Esta categoria aceita apenas itens do tipo serviço.',
                ]);
            }

            if ($category->type === 'product' && $itemType !== 'product') {
                throw ValidationException::withMessages([
                    'item_type' => 'Esta categoria aceita apenas itens do tipo produto.',
                ]);
            }

            if ($itemType === 'package' && ($category->type !== 'mixed' || $lockedSale->customer_id === null)) {
                throw ValidationException::withMessages([
                    'item_type' => 'Pacotes só podem ser vendidos em comandas mistas vinculadas a um cliente.',
                ]);
            }

            $serviceId = null;
            $productId = null;
            $packageTemplateId = null;
            $customerPackageId = null;
            $packageTemplate = null;
            $nameSnapshot = null;
            $unitPriceCents = null;

            if ($itemType === 'service') {
                $serviceId = (string) ($data['service_id'] ?? '');
                /** @var Service|null $service */
                $service = Service::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('status', 'active')
                    ->whereKey($serviceId)
                    ->first();

                if ($service === null) {
                    throw ValidationException::withMessages([
                        'service_id' => 'O serviço selecionado não foi encontrado ou está inativo.',
                    ]);
                }

                $nameSnapshot = isset($data['name_snapshot']) && trim((string) $data['name_snapshot']) !== ''
                    ? trim((string) $data['name_snapshot'])
                    : $service->name;
                $unitPriceCents = isset($data['unit_price_cents'])
                    ? (int) $data['unit_price_cents']
                    : (int) $service->price_cents;
            } elseif ($itemType === 'product') {
                $productId = (string) ($data['product_id'] ?? '');
                /** @var Product|null $product */
                $product = Product::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('is_active', true)
                    ->whereKey($productId)
                    ->first();

                if ($product === null) {
                    throw ValidationException::withMessages([
                        'product_id' => 'O produto selecionado não foi encontrado ou está inativo.',
                    ]);
                }

                $nameSnapshot = isset($data['name_snapshot']) && trim((string) $data['name_snapshot']) !== ''
                    ? trim((string) $data['name_snapshot'])
                    : $product->name;
                $unitPriceCents = isset($data['unit_price_cents'])
                    ? (int) $data['unit_price_cents']
                    : (int) $product->sale_price_cents;
            } elseif ($itemType === 'package') {
                $packageTemplateId = (string) ($data['package_template_id'] ?? '');
                /** @var PackageTemplate|null $packageTemplate */
                $packageTemplate = PackageTemplate::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('is_active', true)
                    ->whereKey($packageTemplateId)
                    ->with('services:id,name')
                    ->lockForUpdate()
                    ->first();

                if ($packageTemplate === null) {
                    throw ValidationException::withMessages([
                        'package_template_id' => 'O pacote selecionado não foi encontrado ou está inativo.',
                    ]);
                }

                $nameSnapshot = $packageTemplate->name;
                $unitPriceCents = (int) $packageTemplate->price_cents;
            } else {
                if (! isset($data['name_snapshot']) || trim((string) $data['name_snapshot']) === '') {
                    throw ValidationException::withMessages([
                        'name_snapshot' => 'A descrição do item customizado é obrigatória.',
                    ]);
                }
                if (! isset($data['unit_price_cents'])) {
                    throw ValidationException::withMessages([
                        'unit_price_cents' => 'O valor unitário do item customizado é obrigatório.',
                    ]);
                }
                $nameSnapshot = trim((string) $data['name_snapshot']);
                $unitPriceCents = (int) $data['unit_price_cents'];
            }

            if ($unitPriceCents < 0) {
                throw ValidationException::withMessages([
                    'unit_price_cents' => 'O valor unitário não pode ser negativo.',
                ]);
            }

            $professionalId = isset($data['professional_id']) && ! empty($data['professional_id'])
                ? (string) $data['professional_id']
                : null;

            if ($itemType === 'package' && $professionalId === null) {
                throw ValidationException::withMessages([
                    'professional_id' => 'Selecione o profissional que executará os serviços do pacote.',
                ]);
            }

            if ($professionalId !== null) {
                $professionalExists = Professional::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('status', 'active')
                    ->whereKey($professionalId)
                    ->exists();

                if (! $professionalExists) {
                    throw ValidationException::withMessages([
                        'professional_id' => 'O profissional selecionado não pertence a esta unidade ou está inativo.',
                    ]);
                }
            }

            $sellerProfessionalId = isset($data['seller_professional_id']) && ! empty($data['seller_professional_id'])
                ? (string) $data['seller_professional_id']
                : null;

            if ($itemType === 'package' && $sellerProfessionalId !== null) {
                throw ValidationException::withMessages([
                    'seller_professional_id' => 'Pacotes usam o profissional executor, não o profissional vendedor.',
                ]);
            }

            if ($sellerProfessionalId !== null) {
                $sellerProfessionalExists = Professional::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('status', 'active')
                    ->whereKey($sellerProfessionalId)
                    ->exists();

                if (! $sellerProfessionalExists) {
                    throw ValidationException::withMessages([
                        'seller_professional_id' => 'O profissional vendedor selecionado não pertence a esta unidade ou está inativo.',
                    ]);
                }
            }

            $quantity = max(1, (int) ($data['quantity'] ?? 1));
            if ($itemType === 'package' && $quantity !== 1) {
                throw ValidationException::withMessages([
                    'quantity' => 'Cada pacote deve ser lançado em uma linha própria.',
                ]);
            }
            $coveredQuantity = 0;
            $customerPackage = null;

            if ($itemType === 'package') {
                $requestedPackageId = trim((string) ($data['customer_package_id'] ?? ''));
                if ($requestedPackageId !== '') {
                    /** @var CustomerPackage|null $customerPackage */
                    $customerPackage = CustomerPackage::query()
                        ->where('tenant_id', $tenantId)
                        ->where('unit_id', $unitId)
                        ->where('customer_id', $lockedSale->customer_id)
                        ->whereKey($requestedPackageId)
                        ->lockForUpdate()
                        ->first();

                    if ($customerPackage === null || $customerPackage->status !== 'pending' || $customerPackage->package_template_id !== $packageTemplate->getKey()) {
                        throw ValidationException::withMessages([
                            'customer_package_id' => 'O pacote pendente não pertence ao cliente, já foi faturado ou não corresponde ao modelo selecionado.',
                        ]);
                    }
                    if ($customerPackage->sale_id !== null && $customerPackage->sale_id !== $lockedSale->getKey()) {
                        throw ValidationException::withMessages([
                            'customer_package_id' => 'Este pacote pendente já está vinculado a outra comanda.',
                        ]);
                    }
                } else {
                    $serviceAllocations = $packageTemplate->services
                        ->map(static fn (Service $service): array => [
                            'id' => (string) $service->getKey(),
                            'name' => (string) $service->name,
                            'quantity' => (int) ($service->pivot->included_quantity ?? 1),
                        ])
                        ->values()
                        ->all();
                    $customerPackage = CustomerPackage::query()->create([
                        'id' => (string) Str::uuid7(),
                        'tenant_id' => $tenantId,
                        'unit_id' => $unitId,
                        'customer_id' => $lockedSale->customer_id,
                        'package_template_id' => $packageTemplate->getKey(),
                        'sale_id' => $lockedSale->getKey(),
                        'name_snapshot' => $packageTemplate->name,
                        'price_cents_snapshot' => $packageTemplate->price_cents,
                        'total_sessions_snapshot' => $packageTemplate->total_sessions,
                        'validity_days_snapshot' => $packageTemplate->validity_days,
                        'eligible_services_snapshot' => $serviceAllocations,
                        'total_sessions' => $packageTemplate->total_sessions,
                        'remaining_sessions' => $packageTemplate->total_sessions,
                        'expires_at' => null,
                        'activated_at' => null,
                        'status' => 'pending',
                        'lock_version' => 0,
                    ]);
                    foreach ($serviceAllocations as $allocation) {
                        CustomerPackageService::query()->create([
                            'tenant_id' => $tenantId,
                            'unit_id' => $unitId,
                            'customer_package_id' => $customerPackage->getKey(),
                            'service_id' => $allocation['id'],
                            'allocated_quantity' => $allocation['quantity'],
                            'remaining_quantity' => $allocation['quantity'],
                        ]);
                    }
                }
                $customerPackageId = $customerPackage->getKey();
                if ($customerPackage->sale_id === null) {
                    $customerPackage->forceFill(['sale_id' => $lockedSale->getKey()])->save();
                }

                if (SaleItem::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('sale_id', $lockedSale->getKey())
                    ->where('customer_package_id', $customerPackageId)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'customer_package_id' => 'Este pacote já possui uma linha nesta comanda.',
                    ]);
                }
            }

            if ($itemType === 'service' && ! empty($data['customer_package_id'])) {
                if ($lockedSale->customer_id === null) {
                    throw ValidationException::withMessages([
                        'customer_package_id' => 'Pacotes só podem cobrir serviços de uma comanda vinculada a um cliente.',
                    ]);
                }

                /** @var CustomerPackage|null $customerPackage */
                $customerPackage = CustomerPackage::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('customer_id', $lockedSale->customer_id)
                    ->whereKey((string) $data['customer_package_id'])
                    ->lockForUpdate()
                    ->first();

                if ($customerPackage === null || $customerPackage->status !== 'active' || ($customerPackage->expires_at !== null && $customerPackage->expires_at->endOfDay()->isPast())) {
                    throw ValidationException::withMessages([
                        'customer_package_id' => 'O pacote não está ativo, venceu ou não pertence ao cliente da comanda.',
                    ]);
                }

                $linkedItems = SaleItem::query()
                    ->where('customer_package_id', $customerPackage->getKey())
                    ->with('sale.closingSessions.payments')
                    ->get();
                if (! $this->packageEvidence->hasStrictPaidSaleEvidence(
                    $customerPackage,
                    $linkedItems,
                    Schema::hasColumn('financial_obligations', 'customer_package_id'),
                )) {
                    throw ValidationException::withMessages([
                        'customer_package_id' => 'O pacote não possui comprovação válida de venda paga e precisa de revisão.',
                    ]);
                }

                $eligibleServiceIds = collect($customerPackage->eligible_services_snapshot ?? [])
                    ->pluck('id')
                    ->map(static fn (mixed $eligibleServiceId): string => (string) $eligibleServiceId)
                    ->all();

                if (! in_array($serviceId, $eligibleServiceIds, true)) {
                    throw ValidationException::withMessages([
                        'customer_package_id' => 'Este pacote não inclui o serviço selecionado.',
                    ]);
                }

                /** @var CustomerPackageService|null $serviceBalance */
                $serviceBalance = CustomerPackageService::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('customer_package_id', $customerPackage->getKey())
                    ->where('service_id', $serviceId)
                    ->lockForUpdate()
                    ->first();

                $reservedServiceQuantity = (int) PackageUsageReservation::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('customer_package_id', $customerPackage->getKey())
                    ->where('service_id', $serviceId)
                    ->where('status', 'reserved')
                    ->sum('sessions_reserved');
                $reservedPackageQuantity = (int) PackageUsageReservation::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('customer_package_id', $customerPackage->getKey())
                    ->where('status', 'reserved')
                    ->sum('sessions_reserved');
                $serviceAvailable = max(0, (int) ($serviceBalance->remaining_quantity ?? 0) - $reservedServiceQuantity);
                $packageAvailable = max(0, $customerPackage->remaining_sessions - $reservedPackageQuantity);
                $coveredQuantity = min($quantity, $serviceAvailable, $packageAvailable);

                if ($coveredQuantity < 1) {
                    throw ValidationException::withMessages([
                        'customer_package_id' => 'Este pacote não tem saldo disponível para o serviço selecionado.',
                    ]);
                }
            }

            $discountCents = max(0, (int) ($data['discount_cents'] ?? 0));
            $grossCents = $unitPriceCents * ($quantity - $coveredQuantity);

            if ($discountCents > $grossCents) {
                throw ValidationException::withMessages([
                    'discount_cents' => 'O desconto não pode exceder o valor total do item.',
                ]);
            }

            $totalCents = $grossCents - $discountCents;

            /** @var SaleItem $item */
            $item = SaleItem::query()->create([
                'id' => (string) Str::uuid7(),
                'source_id' => $sourceId,
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'sale_id' => $lockedSale->getKey(),
                'item_type' => $itemType,
                'service_id' => $serviceId,
                'product_id' => $productId,
                'package_template_id' => $packageTemplateId,
                'customer_package_id' => $customerPackageId,
                'professional_id' => $professionalId,
                'seller_professional_id' => $sellerProfessionalId,
                'name_snapshot' => $nameSnapshot,
                'unit_price_cents' => $unitPriceCents,
                'quantity' => $quantity,
                'covered_quantity' => $coveredQuantity,
                'discount_cents' => $discountCents,
                'total_cents' => $totalCents,
                'source_metadata' => $itemType === 'package'
                    ? ['package_template_lock_version' => $packageTemplate->lock_version]
                    : ($data['source_metadata'] ?? null),
            ]);

            if ($customerPackage !== null && $itemType === 'service') {
                PackageUsageReservation::query()->create([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenantId,
                    'unit_id' => $unitId,
                    'customer_package_id' => $customerPackage->getKey(),
                    'service_id' => $serviceId,
                    'sale_id' => $lockedSale->getKey(),
                    'sale_item_id' => $item->getKey(),
                    'sessions_reserved' => $coveredQuantity,
                    'status' => 'reserved',
                    'user_id' => $actor->getKey(),
                ]);
            }

            $totalAmountCents = (int) $lockedSale->items()->sum('total_cents');
            $finalAmountCents = max(0, $totalAmountCents - (int) $lockedSale->discount_amount_cents);

            $lockedSale->forceFill([
                'total_amount_cents' => $totalAmountCents,
                'final_amount_cents' => $finalAmountCents,
                'lock_version' => $lockedSale->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'sale.item_added', $lockedSale, [
                'sale_item_id' => $item->getKey(),
                'item_type' => $item->item_type,
                'total_cents' => $item->total_cents,
                'total_amount_cents' => $lockedSale->total_amount_cents,
                'lock_version' => $lockedSale->lock_version,
            ]);

            return $item;
        }, 5);
    }
}
