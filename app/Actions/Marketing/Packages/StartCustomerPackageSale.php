<?php

namespace App\Actions\Marketing\Packages;

use App\Actions\Operational\OperationalAction;
use App\Actions\Sales\AddSaleItem;
use App\Actions\Sales\OpenSale;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StartCustomerPackageSale extends OperationalAction
{
    public function __construct(
        private readonly OpenSale $openSale = new OpenSale,
        private readonly AddSaleItem $addSaleItem = new AddSaleItem,
    ) {
        parent::__construct();
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): Sale
    {
        $unit = $this->unit($actor, $context, 'package.sell');
        $tenantId = $context->tenant->getKey();

        if (! $this->authorization->can($actor, $context, 'sale.manage', $unit)
            || ! $this->authorization->can($actor, $context, 'sale.view', $unit)) {
            throw new AuthorizationException('O operador não pode abrir e visualizar comandas nesta unidade.');
        }

        $professionalId = trim((string) ($data['professional_id'] ?? ''));
        if ($professionalId === '') {
            throw ValidationException::withMessages([
                'professional_id' => 'Selecione o profissional que executará os serviços do pacote.',
            ]);
        }

        $professionalExists = Professional::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unit->getKey())
            ->where('status', 'active')
            ->whereKey($professionalId)
            ->exists();

        if (! $professionalExists) {
            throw ValidationException::withMessages([
                'professional_id' => 'O profissional selecionado não pertence a esta unidade ou está inativo.',
            ]);
        }

        /** @var SaleCategory|null $category */
        $category = SaleCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unit->getKey())
            ->where('type', 'mixed')
            ->where('is_active', true)
            ->whereIn('uniqueness_scope', ['none', 'customer'])
            ->orderBy('name')
            ->orderBy('id')
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'sale_category_id' => 'É necessário ter uma categoria mista ativa para vender pacotes.',
            ]);
        }

        return DB::transaction(function () use ($actor, $context, $category, $data, $professionalId): Sale {
            $sale = $this->openSale->handle($actor, $context, [
                'sale_category_id' => $category->getKey(),
                'customer_id' => $data['customer_id'],
                'source_id' => $data['sale_source_id'] ?? null,
                'source_metadata' => ['created_automatically' => true, 'origin' => 'package_page'],
            ]);

            $this->addSaleItem->handle($actor, $context, $sale, [
                'item_type' => 'package',
                'package_template_id' => $data['package_template_id'],
                'professional_id' => $professionalId,
                'source_id' => $data['item_source_id'] ?? null,
            ]);

            return $sale->fresh();
        }, 5);
    }
}
