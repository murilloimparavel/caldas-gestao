<?php

namespace App\Actions\Marketing\Packages;

use App\Actions\Operational\OperationalAction;
use App\Actions\Sales\AddSaleItem;
use App\Models\CustomerPackage;
use App\Models\Sale;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;

final class SellCustomerPackage extends OperationalAction
{
    public function __construct(private readonly AddSaleItem $addSaleItem = new AddSaleItem)
    {
        parent::__construct();
    }

    /** @param array{customer_id: string, package_template_id: string, sale_id?: string|null, source_id?: string|null} $data */
    public function handle(User $actor, TenantContext $context, array $data): CustomerPackage
    {
        $unit = $this->unit($actor, $context, 'package.sell');
        $saleId = trim((string) ($data['sale_id'] ?? ''));

        if ($saleId === '') {
            throw ValidationException::withMessages([
                'sale_id' => 'O pacote precisa ser lançado como item de uma comanda.',
            ]);
        }

        $sale = Sale::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->where('customer_id', $data['customer_id'])
            ->whereKey($saleId)
            ->first();

        if ($sale === null) {
            throw ValidationException::withMessages([
                'sale_id' => 'A comanda deve pertencer ao cliente e à unidade selecionados.',
            ]);
        }

        $item = $this->addSaleItem->handle($actor, $context, $sale, [
            'item_type' => 'package',
            'package_template_id' => $data['package_template_id'],
            'source_id' => $data['source_id'] ?? null,
        ]);

        return CustomerPackage::query()->findOrFail($item->customer_package_id);
    }
}
