import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowDownRight,
    ArrowLeft,
    ArrowUpRight,
    Boxes,
    CheckCircle2,
    DollarSign,
    FolderTree,
    History,
    RotateCcw,
    SlidersHorizontal,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { StockAdjustmentDialog } from '@/components/inventory/stock-adjustment-dialog';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    formatMoney,
    PageCanvas,
    Pagination,
    parseBrazilianCurrency,
    ResourceHeader,
    StatusBadge,
} from '@/components/operational';
import type { Paginated } from '@/components/operational';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { ImageUploader } from '@/components/ui/image-uploader';
import { Input } from '@/components/ui/input';
import categories from '@/routes/categories';
import products from '@/routes/products';
import type { SharedPageProps } from '@/types';

type CategoryOption = {
    id: string;
    name: string;
};

type InventoryMovementItem = {
    created_at: string;
    id: string;
    previous_stock: number;
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

type Product = {
    barcode: string | null;
    category?: CategoryOption | null;
    category_id: string | null;
    cost_price_cents: number;
    current_stock: number;
    id: string;
    image_url?: string | null;
    is_active: boolean;
    lock_version: number;
    min_stock: number;
    name: string;
    photo_url?: string | null;
    sale_price_cents: number;
    sku: string | null;
    unit_of_measure: string;
};

type Props = {
    categoryOptions: CategoryOption[];
    movements?: Paginated<InventoryMovementItem>;
    product: Product;
};

function MoneyPriceField({
    id,
    name,
    label,
    initialCents = 0,
    disabled = false,
    placeholder = '0,00',
    helpText,
    error,
}: {
    disabled?: boolean;
    error?: string;
    helpText?: string;
    id: string;
    initialCents?: number;
    label: string;
    name: string;
    placeholder?: string;
}) {
    const [displayValue, setDisplayValue] = useState(
        initialCents > 0 ? (initialCents / 100).toFixed(2).replace('.', ',') : '',
    );
    const cents = parseBrazilianCurrency(displayValue);

    return (
        <FormField label={label} name={name} error={error}>
            <Input
                id={id}
                name={`${name}_display`}
                inputMode="decimal"
                value={displayValue}
                disabled={disabled}
                onChange={(event) => setDisplayValue(event.target.value)}
                placeholder={placeholder}
                aria-describedby={`${id}-help`}
            />
            <input
                type="hidden"
                name={name}
                value={Number.isFinite(cents) ? cents : 0}
            />
            {helpText ? (
                <p id={`${id}-help`} className="text-xs text-muted-foreground">
                    {helpText}
                </p>
            ) : null}
        </FormField>
    );
}

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

export default function ProductShow({
    product,
    categoryOptions = [],
    movements,
}: Props) {
    const [updateKey] = useState(() => createIdempotencyKey('product-update'));
    const [destroyKey] = useState(() => createIdempotencyKey('product-destroy'));
    const [reactivateKey] = useState(() => createIdempotencyKey('product-reactivate'));
    const [inactivateOpen, setInactivateOpen] = useState(false);
    const [reactivateOpen, setReactivateOpen] = useState(false);
    const [adjustOpen, setAdjustOpen] = useState(false);
    const [selectedPhoto, setSelectedPhoto] = useState<File | string | null>(
        product.photo_url || product.image_url || null,
    );
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('product.manage');
    const canAdjustStock =
        props.auth.permissions.includes('inventory.manage') || canManage;

    const profitCents = product.sale_price_cents - product.cost_price_cents;
    const profitMargin =
        product.sale_price_cents > 0
            ? Math.round((profitCents / product.sale_price_cents) * 100)
            : 0;

    const isOutOfStock = product.current_stock <= 0;
    const isLowStock =
        !isOutOfStock && product.current_stock <= product.min_stock;

    return (
        <>
            <Head title={product.name} />
            <PageCanvas>
                <div>
                    <Button asChild variant="ghost" className="mb-4 -ml-3">
                        <Link href={products.index()}>
                            <ArrowLeft aria-hidden="true" />
                            Voltar para produtos
                        </Link>
                    </Button>
                    <ResourceHeader
                        eyebrow="Cadastro de produto"
                        title={product.name}
                        description="Atualize preços, estoque mínimo e referências de código de barras ou SKU."
                        action={
                            <div className="flex flex-wrap items-center gap-2">
                                {canAdjustStock ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setAdjustOpen(true)}
                                    >
                                        <SlidersHorizontal aria-hidden="true" />
                                        Ajustar estoque
                                    </Button>
                                ) : null}
                                <StatusBadge
                                    status={product.is_active ? 'active' : 'inactive'}
                                />
                            </div>
                        }
                    />
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(18rem,0.85fr)]">
                    <section className="surface-panel p-5 sm:p-6">
                        <div className="mb-6 space-y-1">
                            <h2 className="text-base font-semibold">
                                Dados principais do produto
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Mantenha o estoque e os preços sincronizados para apuração de resultados.
                            </p>
                        </div>
                        {!product.is_active ? (
                            <div className="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-50/40 p-4 text-emerald-950 dark:bg-emerald-950/20 dark:text-emerald-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <p className="font-semibold text-sm">Este produto está inativo</p>
                                    <p className="text-xs text-muted-foreground">Ele não pode ser adicionado a novas comandas até que seja reativado.</p>
                                </div>
                                {canManage ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={() => setReactivateOpen(true)}
                                        className="bg-emerald-600 text-white hover:bg-emerald-700 shrink-0"
                                    >
                                        Reativar cadastro
                                    </Button>
                                ) : null}
                            </div>
                        ) : null}
                        <Form
                            {...products.update.form(product.id)}
                            headers={{ 'X-Idempotency-Key': updateKey }}
                            className="space-y-5"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <FormErrorSummary errors={errors} />
                                    <input
                                        type="hidden"
                                        name="lock_version"
                                        value={product.lock_version}
                                    />
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="sm:col-span-2">
                                            <FormField
                                                label="Foto do produto"
                                                name="photo"
                                                error={errors.photo}
                                            >
                                                <ImageUploader
                                                    value={selectedPhoto}
                                                    onChange={setSelectedPhoto}
                                                    disabled={!canManage}
                                                    error={errors.photo}
                                                    aspectRatio="auto"
                                                    previewHeight="140px"
                                                />
                                            </FormField>
                                        </div>
                                        <div className="sm:col-span-2">
                                            <FormField
                                                label="Nome do produto"
                                                name="name"
                                                error={errors.name}
                                            >
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    defaultValue={product.name}
                                                    required
                                                    disabled={!canManage}
                                                />
                                            </FormField>
                                        </div>

                                        <div>
                                            <FormField
                                                label="Categoria"
                                                name="category_id"
                                                error={errors.category_id}
                                            >
                                                <select
                                                    id="category_id"
                                                    name="category_id"
                                                    defaultValue={product.category_id ?? ''}
                                                    disabled={!canManage}
                                                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    <option value="">Sem categoria</option>
                                                    {categoryOptions.map((cat) => (
                                                        <option key={cat.id} value={cat.id}>
                                                            {cat.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </FormField>
                                        </div>

                                        <div>
                                            <FormField
                                                label="Unidade de medida"
                                                name="unit_of_measure"
                                                error={errors.unit_of_measure}
                                            >
                                                <Input
                                                    id="unit_of_measure"
                                                    name="unit_of_measure"
                                                    defaultValue={product.unit_of_measure}
                                                    required
                                                    disabled={!canManage}
                                                />
                                            </FormField>
                                        </div>

                                        <div>
                                            <FormField
                                                label="Código SKU"
                                                name="sku"
                                                error={errors.sku}
                                            >
                                                <Input
                                                    id="sku"
                                                    name="sku"
                                                    defaultValue={product.sku ?? ''}
                                                    disabled={!canManage}
                                                />
                                            </FormField>
                                        </div>

                                        <div>
                                            <FormField
                                                label="Código de barras (EAN)"
                                                name="barcode"
                                                error={errors.barcode}
                                            >
                                                <Input
                                                    id="barcode"
                                                    name="barcode"
                                                    defaultValue={product.barcode ?? ''}
                                                    disabled={!canManage}
                                                />
                                            </FormField>
                                        </div>

                                        <MoneyPriceField
                                            id="sale_price_cents"
                                            name="sale_price_cents"
                                            label="Preço de venda"
                                            initialCents={product.sale_price_cents}
                                            disabled={!canManage}
                                            error={errors.sale_price_cents}
                                        />

                                        <MoneyPriceField
                                            id="cost_price_cents"
                                            name="cost_price_cents"
                                            label="Preço de custo"
                                            initialCents={product.cost_price_cents}
                                            disabled={!canManage}
                                            error={errors.cost_price_cents}
                                        />

                                        <div>
                                            <FormField
                                                label="Estoque atual"
                                                name="current_stock"
                                                error={errors.current_stock}
                                            >
                                                <Input
                                                    id="current_stock"
                                                    name="current_stock"
                                                    type="number"
                                                    defaultValue={product.current_stock}
                                                    required
                                                    disabled={!canManage}
                                                />
                                            </FormField>
                                        </div>

                                        <div>
                                            <FormField
                                                label="Estoque mínimo"
                                                name="min_stock"
                                                error={errors.min_stock}
                                            >
                                                <Input
                                                    id="min_stock"
                                                    name="min_stock"
                                                    type="number"
                                                    defaultValue={product.min_stock}
                                                    min={0}
                                                    required
                                                    disabled={!canManage}
                                                />
                                            </FormField>
                                        </div>
                                    </div>

                                    {canManage ? (
                                        <div className="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                                            {product.is_active ? (
                                                <Dialog
                                                    open={inactivateOpen}
                                                    onOpenChange={setInactivateOpen}
                                                >
                                                    <DialogTrigger asChild>
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                        >
                                                            Inativar produto
                                                        </Button>
                                                    </DialogTrigger>
                                                    <DialogContent>
                                                        <DialogHeader>
                                                            <DialogTitle>
                                                                Inativar produto?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                O histórico de vendas e movimentações passadas será preservado, mas o produto não poderá ser adicionado a novas comandas.
                                                            </DialogDescription>
                                                        </DialogHeader>
                                                        <Form
                                                            {...products.destroy.form(product.id)}
                                                            headers={{
                                                                'X-Idempotency-Key': destroyKey,
                                                            }}
                                                            method="delete"
                                                            onSuccess={() => setInactivateOpen(false)}
                                                        >
                                                            {({ processing: inactivating }) => (
                                                                <>
                                                                    <input
                                                                        type="hidden"
                                                                        name="lock_version"
                                                                        value={product.lock_version}
                                                                    />
                                                                    <DialogFooter className="mt-4">
                                                                        <Button
                                                                            type="button"
                                                                            variant="outline"
                                                                            onClick={() => setInactivateOpen(false)}
                                                                        >
                                                                            Cancelar
                                                                        </Button>
                                                                        <Button
                                                                            type="submit"
                                                                            variant="destructive"
                                                                            disabled={inactivating}
                                                                        >
                                                                            {inactivating ? 'Inativando...' : 'Confirmar inativação'}
                                                                        </Button>
                                                                    </DialogFooter>
                                                                </>
                                                            )}
                                                        </Form>
                                                    </DialogContent>
                                                </Dialog>
                                            ) : (
                                                <Dialog
                                                    open={reactivateOpen}
                                                    onOpenChange={setReactivateOpen}
                                                >
                                                    <DialogTrigger asChild>
                                                        <Button
                                                            type="button"
                                                            className="bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                                                        >
                                                            Reativar cadastro
                                                        </Button>
                                                    </DialogTrigger>
                                                    <DialogContent>
                                                        <DialogHeader>
                                                            <DialogTitle>
                                                                Reativar produto?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                O produto voltará a ficar ativo para venda e movimentações de estoque.
                                                            </DialogDescription>
                                                        </DialogHeader>
                                                        <Form
                                                            {...products.reactivate.form(product.id)}
                                                            headers={{
                                                                'X-Idempotency-Key': reactivateKey,
                                                            }}
                                                            method="patch"
                                                            onSuccess={() => setReactivateOpen(false)}
                                                        >
                                                            {({ processing: reactivating }) => (
                                                                <>
                                                                    <input
                                                                        type="hidden"
                                                                        name="lock_version"
                                                                        value={product.lock_version}
                                                                    />
                                                                    <DialogFooter className="mt-4">
                                                                        <Button
                                                                            type="button"
                                                                            variant="outline"
                                                                            onClick={() => setReactivateOpen(false)}
                                                                        >
                                                                            Cancelar
                                                                        </Button>
                                                                        <Button
                                                                            type="submit"
                                                                            disabled={reactivating}
                                                                            className="bg-emerald-600 text-white hover:bg-emerald-700"
                                                                        >
                                                                            {reactivating ? 'Reativando...' : 'Confirmar reativação'}
                                                                        </Button>
                                                                    </DialogFooter>
                                                                </>
                                                            )}
                                                        </Form>
                                                    </DialogContent>
                                                </Dialog>
                                            )}
                                            <FormActions
                                                processing={processing}
                                                label="Salvar alterações"
                                            />
                                        </div>
                                    ) : null}
                                </>
                            )}
                        </Form>
                    </section>

                    <aside className="space-y-5">
                        <section className="surface-panel space-y-4 p-5">
                            <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Análise financeira e estoque
                            </h2>

                            {product.photo_url || product.image_url ? (
                                <div className="overflow-hidden rounded-xl border border-border">
                                    <img
                                        src={product.photo_url || product.image_url || undefined}
                                        alt={product.name}
                                        className="h-40 w-full object-cover"
                                    />
                                </div>
                            ) : null}

                            <div className="space-y-3 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="inline-flex items-center gap-2 text-muted-foreground">
                                        <TrendingUp className="size-4" />
                                        Margem bruta
                                    </span>
                                    <span className="font-semibold text-foreground">
                                        {profitMargin}% ({formatMoney(profitCents)})
                                    </span>
                                </div>

                                <div className="flex items-center justify-between">
                                    <span className="inline-flex items-center gap-2 text-muted-foreground">
                                        <DollarSign className="size-4" />
                                        Valor em estoque (custo)
                                    </span>
                                    <span className="font-semibold text-foreground">
                                        {formatMoney(
                                            Math.max(0, product.current_stock) *
                                                product.cost_price_cents,
                                        )}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between border-t border-border pt-3">
                                    <span className="inline-flex items-center gap-2 text-muted-foreground">
                                        <Boxes className="size-4" />
                                        Status do estoque
                                    </span>
                                    <span
                                        className={`inline-flex items-center gap-1 font-medium text-xs rounded-full px-2.5 py-0.5 ${
                                            isOutOfStock
                                                ? 'bg-destructive/15 text-destructive'
                                                : isLowStock
                                                  ? 'bg-amber-500/15 text-amber-500'
                                                  : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                        }`}
                                    >
                                        {isOutOfStock ? (
                                            <>
                                                <AlertCircle className="size-3" />
                                                Esgotado
                                            </>
                                        ) : isLowStock ? (
                                            <>
                                                <AlertTriangle className="size-3" />
                                                Abaixo do mínimo
                                            </>
                                        ) : (
                                            <>
                                                <CheckCircle2 className="size-3" />
                                                Estoque regular
                                            </>
                                        )}
                                    </span>
                                </div>
                            </div>
                        </section>

                        {product.category ? (
                            <section className="surface-panel space-y-3 p-5">
                                <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                    Categoria vinculada
                                </h2>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="inline-flex items-center gap-2 font-medium text-foreground">
                                        <FolderTree className="size-4 text-muted-foreground" />
                                        {product.category.name}
                                    </span>
                                    <Button asChild variant="ghost" size="sm">
                                        <Link href={categories.show(product.category.id)}>
                                            Ver categoria
                                        </Link>
                                    </Button>
                                </div>
                            </section>
                        ) : null}
                    </aside>
                </div>

                <section className="surface-panel mt-6 p-5 sm:p-6 space-y-5">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-base font-semibold flex items-center gap-2">
                                <History className="size-5 text-primary" />
                                Histórico de Movimentações de Estoque
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Registro cronológico de vendas, compras, perdas e ajustes físicos deste item.
                            </p>
                        </div>
                        {canAdjustStock ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setAdjustOpen(true)}
                            >
                                <SlidersHorizontal className="size-3.5" />
                                Novo lançamento
                            </Button>
                        ) : null}
                    </div>

                    {!movements || movements.data.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border py-8 text-center text-sm text-muted-foreground">
                            <Boxes className="mx-auto size-8 text-muted-foreground/50 mb-2" />
                            Nenhuma movimentação de estoque registrada para este produto ainda.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-border text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="py-3 px-3">Data / Hora</th>
                                        <th className="py-3 px-3">Tipo</th>
                                        <th className="py-3 px-3">Quantidade</th>
                                        <th className="py-3 px-3">Custo Unitário</th>
                                        <th className="py-3 px-3">Saldo Anterior</th>
                                        <th className="py-3 px-3">Saldo Resultante</th>
                                        <th className="py-3 px-3">Motivo / Referência</th>
                                        <th className="py-3 px-3">Operador</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {movements.data.map((m) => {
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
                                                <td className="py-3 px-3 whitespace-nowrap text-xs text-muted-foreground">
                                                    {formattedDate}
                                                </td>
                                                <td className="py-3 px-3 whitespace-nowrap">
                                                    <MovementTypeBadge type={m.type} />
                                                </td>
                                                <td className="py-3 px-3 whitespace-nowrap font-semibold">
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
                                                        {m.quantity} {product.unit_of_measure}
                                                    </span>
                                                </td>
                                                <td className="py-3 px-3 whitespace-nowrap text-muted-foreground text-xs">
                                                    {m.unit_cost_cents > 0
                                                        ? formatMoney(m.unit_cost_cents)
                                                        : '-'}
                                                </td>
                                                <td className="py-3 px-3 whitespace-nowrap text-muted-foreground">
                                                    {m.previous_stock} {product.unit_of_measure}
                                                </td>
                                                <td className="py-3 px-3 whitespace-nowrap font-medium text-foreground">
                                                    {m.resulting_stock} {product.unit_of_measure}
                                                </td>
                                                <td className="py-3 px-3 text-xs text-foreground max-w-xs truncate">
                                                    {m.reason}
                                                    {m.reference_type ? (
                                                        <span className="block text-[11px] text-muted-foreground">
                                                            Ref: {m.reference_type}
                                                            {m.reference_id ? ` #${m.reference_id.slice(0, 8)}` : ''}
                                                        </span>
                                                    ) : null}
                                                </td>
                                                <td className="py-3 px-3 whitespace-nowrap text-xs text-muted-foreground">
                                                    {m.user?.name ?? 'Sistema'}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>

                            {movements.links && movements.links.length > 3 ? (
                                <div className="pt-4">
                                    <Pagination links={movements.links} />
                                </div>
                            ) : null}
                        </div>
                    )}
                </section>

                <StockAdjustmentDialog
                    product={product}
                    open={adjustOpen}
                    onOpenChange={setAdjustOpen}
                />
            </PageCanvas>
        </>
    );
}

ProductShow.layout = {
    breadcrumbs: [
        { title: 'Produtos', href: products.index() },
        { title: 'Detalhes do produto', href: '#' },
    ],
};
