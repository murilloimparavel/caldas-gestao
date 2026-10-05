<?php

namespace App\Actions\Sales;

use App\Actions\Operational\OperationalAction;
use App\Models\Product;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AddSaleItem extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, Sale $sale, array $data, string $permission = 'sale.manage'): SaleItem
    {
        $unit = $this->unit($actor, $context, $permission);
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        if ($sale->tenant_id !== $tenantId || $sale->unit_id !== $unitId) {
            throw new AuthorizationException('A comanda pertence a outra unidade ou workspace.');
        }

        $this->assertOwnSale($context, $sale);

        if (! in_array($sale->status, ['draft', 'open', 'ready_to_bill'], true)) {
            throw ValidationException::withMessages([
                'sale' => 'Apenas comandas em aberto ou em rascunho podem receber novos itens.',
            ]);
        }

        $itemType = (string) ($data['item_type'] ?? 'custom');
        if (! in_array($itemType, ['service', 'product', 'custom'], true)) {
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

            $serviceId = null;
            $productId = null;
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
            $linkedProfessionalId = $this->assertProfessional($context, $professionalId);

            if ($linkedProfessionalId !== null && $professionalId === null && $itemType !== 'product') {
                $professionalId = $linkedProfessionalId;
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

            $sellerProfessionalId = $this->assertProfessional($context, $sellerProfessionalId);

            if ($linkedProfessionalId !== null && $sellerProfessionalId === null && $itemType === 'product') {
                $sellerProfessionalId = $linkedProfessionalId;
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
            $discountCents = max(0, (int) ($data['discount_cents'] ?? 0));
            $grossCents = $unitPriceCents * $quantity;

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
                'professional_id' => $professionalId,
                'seller_professional_id' => $sellerProfessionalId,
                'name_snapshot' => $nameSnapshot,
                'unit_price_cents' => $unitPriceCents,
                'quantity' => $quantity,
                'discount_cents' => $discountCents,
                'total_cents' => $totalCents,
                'source_metadata' => $data['source_metadata'] ?? null,
            ]);

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
