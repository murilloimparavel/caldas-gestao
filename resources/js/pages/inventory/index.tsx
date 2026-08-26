import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    Filter,
    RotateCcw,
    SlidersHorizontal,
} from 'lucide-react';
import { useState } from 'react';
import { StockAdjustmentDialog } from '@/components/inventory/stock-adjustment-dialog';
import type { StockAdjustableProduct } from '@/components/inventory/stock-adjustment-dialog';
import {
    EmptyState,
    formatMoney,
    PageCanvas,
    Pagination,
    ResourceHeader,
} from '@/components/operational';
import type { Paginated } from '@/components/operational';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import inventory from '@/routes/inventory';
import productsRoute from '@/routes/products';
import type { SharedPageProps } from '@/types';

type InventoryMovementItem = {
    created_at: string;
    id: string;
    previous_stock: number;
    product: {
        current_stock: number;
        id: string;
        min_stock: number;
        name: string;
        sku: string | null;
        unit_of_measure: string;
    };
    quantity: number;
    reason: string;
    reference_id: string | null;
    reference_type: string | null;
    resulting_stock: number;
    type:
        | 'sale_outflow'
        | 'purchase_inflow'
        | 'adjustment_loss'
        | 'adjustment_gain'
        | 'manual_count';
    unit_cost_cents: number;
    user?: { id: string; name: string } | null;
};

type Props = {
    filters: {
        date?: string;
        product_id?: string;
        type?: string;
    };
    movements: Paginated<InventoryMovementItem>;
    products: StockAdjustableProduct[];
};

function MovementTypeBadge({ type }: { type: InventoryMovementItem['type'] }) {
    switch (type) {
        case 'purchase_inflow':
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    <ArrowUpRight className="size-3" />
                    Compra / Entrada
                </span>
            );
        case 'adjustment_gain':
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    <ArrowUpRight className="size-3" />
                    Ajuste (Ganho)
                </span>
            );
        case 'sale_outflow':
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-blue-500/15 px-2.5 py-0.5 text-xs font-medium text-blue-600 dark:text-blue-400">
                    <ArrowDownRight className="size-3" />
                    Venda / Saída
                </span>
            );
        case 'adjustment_loss':
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-destructive/15 px-2.5 py-0.5 text-xs font-medium text-destructive">
                    <ArrowDownRight className="size-3" />
                    Ajuste (Perda)
                </span>
            );
        case 'manual_count':
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-purple-500/15 px-2.5 py-0.5 text-xs font-medium text-purple-600 dark:text-purple-400">
                    <RotateCcw className="size-3" />
                    Contagem física
                </span>
            );
    }
}

export default function InventoryIndex({
    movements: paginator,
    products = [],
    filters,
}: Props) {
    const { props } = usePage<SharedPageProps>();
    const canManage =
        props.auth.permissions.includes('inventory.manage') ||
        props.auth.permissions.includes('product.manage');
    const [adjustingProduct, setAdjustingProduct] =
        useState<StockAdjustableProduct | null>(null);

    return (
        <>
            <Head title="Extrato de Estoque" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Gestão"
                    title="Extrato de Estoque"
                    description="Acompanhe o fluxo de entradas por compra, saídas automáticas por comanda e ajustes físicos da unidade."
                    action={
                        canManage && products.length > 0 ? (
                            <Button
                                onClick={() => setAdjustingProduct(products[0])}
                                className="w-full sm:w-auto"
                            >
                                <SlidersHorizontal aria-hidden="true" />
                                Lançar movimentação
                            </Button>
                        ) : undefined
                    }
                />

                <form
                    method="get"
                    action={inventory.index.url()}
                    className="surface-panel grid gap-3 p-4 sm:grid-cols-3 lg:grid-cols-4 items-end"
                >
                    <div>
                        <label
                            htmlFor="product_id"
                            className="block text-xs font-medium text-muted-foreground mb-1"
                        >
                            Produto
                        </label>
                        <select
                            id="product_id"
                            name="product_id"
                            defaultValue={filters.product_id ?? ''}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Todos os produtos</option>
                            {products.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="type"
                            className="block text-xs font-medium text-muted-foreground mb-1"
                        >
                            Tipo de movimentação
                        </label>
                        <select
                            id="type"
                            name="type"
                            defaultValue={filters.type ?? ''}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Todos os tipos</option>
                            <option value="sale_outflow">Venda / Saída</option>
                            <option value="purchase_inflow">Compra / Entrada</option>
                            <option value="adjustment_gain">Ajuste (Ganho)</option>
                            <option value="adjustment_loss">Ajuste (Perda)</option>
                            <option value="manual_count">Contagem física</option>
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="date"
                            className="block text-xs font-medium text-muted-foreground mb-1"
                        >
                            Data
                        </label>
                        <Input
                            id="date"
                            name="date"
                            type="date"
                            defaultValue={filters.date ?? ''}
                        />
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" variant="secondary" className="w-full">
                            <Filter className="size-3.5" />
                            Filtrar
                        </Button>
                        {filters.product_id || filters.type || filters.date ? (
                            <Button asChild variant="outline">
                                <Link href={inventory.index()}>Limpar</Link>
                            </Button>
                        ) : null}
                    </div>
                </form>

                {paginator.data.length === 0 ? (
                    <EmptyState
                        title="Nenhuma movimentação encontrada"
                        description={
                            filters.product_id || filters.type || filters.date
                                ? 'Nenhuma movimentação de estoque corresponde aos filtros aplicados.'
                                : 'Lance movimentações de compra ou finalize vendas de produtos para alimentar o extrato.'
                        }
                    />
                ) : (
                    <div className="surface-panel overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="py-3 px-4">Data / Hora</th>
                                        <th className="py-3 px-4">Produto</th>
                                        <th className="py-3 px-4">Tipo</th>
                                        <th className="py-3 px-4">Qtd</th>
                                        <th className="py-3 px-4">Custo Un.</th>
                                        <th className="py-3 px-4">Anterior</th>
                                        <th className="py-3 px-4">Resultante</th>
                                        <th className="py-3 px-4">Motivo / Ref</th>
                                        <th className="py-3 px-4">Operador</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {paginator.data.map((m) => {
                                        const isInflow =
                                            m.type === 'purchase_inflow' ||
                                            m.type === 'adjustment_gain';
                                        const isOutflow =
                                            m.type === 'sale_outflow' ||
                                            m.type === 'adjustment_loss';

                                        const date = new Date(m.created_at);
                                        const formattedDate = date.toLocaleString('pt-BR', {
                                            day: '2-digit',
                                            month: '2-digit',
                                            year: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        });

                                        return (
                                            <tr key={m.id} className="hover:bg-muted/30">
                                                <td className="py-3.5 px-4 whitespace-nowrap text-xs text-muted-foreground">
                                                    {formattedDate}
                                                </td>
                                                <td className="py-3.5 px-4 whitespace-nowrap font-medium text-foreground">
                                                    <Link
                                                        href={productsRoute.show(m.product.id)}
                                                        className="hover:underline text-primary"
                                                    >
                                                        {m.product.name}
                                                    </Link>
                                                    {m.product.sku ? (
                                                        <span className="block text-[11px] text-muted-foreground">
                                                            SKU: {m.product.sku}
                                                        </span>
                                                    ) : null}
                                                </td>
                                                <td className="py-3.5 px-4 whitespace-nowrap">
                                                    <MovementTypeBadge type={m.type} />
                                                </td>
                                                <td className="py-3.5 px-4 whitespace-nowrap font-semibold">
                                                    <span
                                                        className={
                                                            isInflow
                                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                                : isOutflow
                                                                  ? 'text-destructive'
                                                                  : 'text-foreground'
                                                        }
                                                    >
                                                        {isInflow ? '+' : isOutflow ? '-' : ''}
                                                        {m.quantity} {m.product.unit_of_measure}
                                                    </span>
                                                </td>
                                                <td className="py-3.5 px-4 whitespace-nowrap text-xs text-muted-foreground">
                                                    {m.unit_cost_cents > 0
                                                        ? formatMoney(m.unit_cost_cents)
                                                        : '-'}
                                                </td>
                                                <td className="py-3.5 px-4 whitespace-nowrap text-muted-foreground">
                                                    {m.previous_stock} {m.product.unit_of_measure}
                                                </td>
                                                <td className="py-3.5 px-4 whitespace-nowrap font-semibold text-foreground">
                                                    {m.resulting_stock} {m.product.unit_of_measure}
                                                </td>
                                                <td className="py-3.5 px-4 text-xs text-foreground max-w-xs truncate">
                                                    {m.reason}
                                                    {m.reference_type ? (
                                                        <span className="block text-[11px] text-muted-foreground">
                                                            Ref: {m.reference_type}
                                                            {m.reference_id
                                                                ? ` #${m.reference_id.slice(0, 8)}`
                                                                : ''}
                                                        </span>
                                                    ) : null}
                                                </td>
                                                <td className="py-3.5 px-4 whitespace-nowrap text-xs text-muted-foreground">
                                                    {m.user?.name ?? 'Sistema'}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                <Pagination links={paginator.links} />

                {adjustingProduct ? (
                    <StockAdjustmentDialog
                        product={adjustingProduct}
                        open={adjustingProduct !== null}
                        onOpenChange={(open) => {
                            if (!open) {
                                setAdjustingProduct(null);
                            }
                        }}
                    />
                ) : null}
            </PageCanvas>
        </>
    );
}

InventoryIndex.layout = {
    breadcrumbs: [
        { title: 'Produtos', href: productsRoute.index() },
        { title: 'Extrato de estoque', href: inventory.index() },
    ],
};
