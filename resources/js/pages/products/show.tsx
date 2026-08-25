import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    Boxes,
    CheckCircle2,
    DollarSign,
    FolderTree,
    Package,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    formatMoney,
    PageCanvas,
    parseBrazilianCurrency,
    ResourceHeader,
    StatusBadge,
} from '@/components/operational';
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
import { Input } from '@/components/ui/input';
import categories from '@/routes/categories';
import products from '@/routes/products';
import type { SharedPageProps } from '@/types';

type CategoryOption = {
    id: string;
    name: string;
};

type Product = {
    barcode: string | null;
    category?: CategoryOption | null;
    category_id: string | null;
    cost_price_cents: number;
    current_stock: number;
    id: string;
    is_active: boolean;
    lock_version: number;
    min_stock: number;
    name: string;
    sale_price_cents: number;
    sku: string | null;
    unit_of_measure: string;
};

type Props = {
    categoryOptions: CategoryOption[];
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

export default function ProductShow({ product, categoryOptions = [] }: Props) {
    const [updateKey] = useState(() => createIdempotencyKey('product-update'));
    const [destroyKey] = useState(() => createIdempotencyKey('product-destroy'));
    const [inactivateOpen, setInactivateOpen] = useState(false);
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('product.manage');

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
                        action={<StatusBadge status={product.is_active ? 'active' : 'inactive'} />}
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
                                                <span className="text-sm text-muted-foreground">
                                                    Este produto está inativo.
                                                </span>
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
