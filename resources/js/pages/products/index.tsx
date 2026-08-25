import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Boxes,
    FolderTree,
    Package,
    Plus,
    Tag,
} from 'lucide-react';
import { useState } from 'react';
import {
    createIdempotencyKey,
    EmptyState,
    FormActions,
    FormErrorSummary,
    FormField,
    formatMoney,
    PageCanvas,
    Pagination,
    parseBrazilianCurrency,
    ResourceHeader,
    SearchToolbar,
    StatusBadge,
} from '@/components/operational';
import type { Paginated, ResourceFilters } from '@/components/operational';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
    filters: ResourceFilters & { category_id?: string };
    products: Paginated<Product>;
};

function MoneyPriceField({
    id,
    name,
    label,
    initialCents = 0,
    placeholder = '0,00',
    helpText,
    error,
}: {
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

export default function ProductsIndex({
    products: paginator,
    filters,
    categoryOptions = [],
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [createKey] = useState(() => createIdempotencyKey('product-create'));
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('product.manage');

    return (
        <>
            <Head title="Produtos" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Gestão"
                    title="Produtos"
                    description="Cadastre itens físicos de venda e consumo interno com controle de preço de custo, venda e estoque mínimo."
                    action={
                        canManage ? (
                            <Dialog
                                open={createOpen}
                                onOpenChange={setCreateOpen}
                            >
                                <DialogTrigger asChild>
                                    <Button className="w-full sm:w-auto">
                                        <Plus aria-hidden="true" />
                                        Novo produto
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                                    <DialogHeader>
                                        <DialogTitle>Novo produto</DialogTitle>
                                        <DialogDescription>
                                            Cadastre um produto físico para venda no caixa ou uso nos procedimentos.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form
                                        {...products.store.form()}
                                        headers={{
                                            'X-Idempotency-Key': createKey,
                                        }}
                                        resetOnSuccess
                                        onSuccess={() => setCreateOpen(false)}
                                        className="space-y-5"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <FormErrorSummary errors={errors} />
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
                                                                required
                                                                autoFocus
                                                                placeholder="Ex.: Pomada Modeladora Efeito Matte 100g"
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
                                                                defaultValue=""
                                                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
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
                                                                defaultValue="un"
                                                                required
                                                                placeholder="un, cx, ml, g"
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
                                                                placeholder="Ex.: POM-MATTE-01"
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
                                                                placeholder="789..."
                                                            />
                                                        </FormField>
                                                    </div>

                                                    <MoneyPriceField
                                                        id="sale_price_cents"
                                                        name="sale_price_cents"
                                                        label="Preço de venda"
                                                        helpText="Preço cobrado na comanda ou balcão."
                                                        error={errors.sale_price_cents}
                                                    />

                                                    <MoneyPriceField
                                                        id="cost_price_cents"
                                                        name="cost_price_cents"
                                                        label="Preço de custo"
                                                        helpText="Custo de aquisição do produto."
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
                                                                defaultValue={0}
                                                                required
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
                                                                defaultValue={0}
                                                                min={0}
                                                                required
                                                            />
                                                        </FormField>
                                                    </div>
                                                </div>

                                                <input
                                                    type="hidden"
                                                    name="is_active"
                                                    value="1"
                                                />
                                                <FormActions
                                                    processing={processing}
                                                    onCancel={() => setCreateOpen(false)}
                                                    label="Cadastrar produto"
                                                />
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        ) : null
                    }
                />

                <SearchToolbar
                    action={products.index.url()}
                    defaultValue={filters.search}
                    placeholder="Buscar por nome, SKU ou código de barras"
                    resultLabel={`${paginator.total} ${paginator.total === 1 ? 'produto encontrado' : 'produtos encontrados'}`}
                />

                {paginator.data.length === 0 ? (
                    <EmptyState
                        title={
                            filters.search
                                ? 'Nenhum produto encontrado'
                                : 'Nenhum produto cadastrado'
                        }
                        description={
                            filters.search
                                ? 'Tente outro termo de busca.'
                                : 'Cadastre produtos físicos para venda e controle de estoque da sua unidade.'
                        }
                        action={
                            !filters.search && canManage ? (
                                <Button onClick={() => setCreateOpen(true)}>
                                    <Plus aria-hidden="true" />
                                    Cadastrar primeiro produto
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <section
                        aria-label="Lista de produtos"
                        className="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
                    >
                        {paginator.data.map((product) => {
                            const isLowStock =
                                product.current_stock <= product.min_stock;

                            return (
                                <article
                                    key={product.id}
                                    className="surface-panel flex min-h-56 flex-col gap-4 p-5 transition-colors hover:border-primary/40"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex min-w-0 items-center gap-3">
                                            <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-accent text-accent-foreground">
                                                <Package
                                                    aria-hidden="true"
                                                    className="size-5"
                                                />
                                            </div>
                                            <div className="min-w-0">
                                                <h2 className="truncate font-semibold text-foreground">
                                                    {product.name}
                                                </h2>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {product.category?.name ? (
                                                        <span className="inline-flex items-center gap-1">
                                                            <FolderTree className="size-3" />
                                                            {product.category.name}
                                                        </span>
                                                    ) : (
                                                        'Sem categoria'
                                                    )}
                                                    {product.sku ? ` • SKU: ${product.sku}` : ''}
                                                </p>
                                            </div>
                                        </div>
                                        <StatusBadge status={product.is_active ? 'active' : 'inactive'} />
                                    </div>

                                    <div className="flex items-center justify-between gap-3 border-y border-border py-3">
                                        <div>
                                            <span className="text-xs text-muted-foreground block">
                                                Preço de venda
                                            </span>
                                            <span className="font-semibold text-foreground">
                                                {formatMoney(product.sale_price_cents)}
                                            </span>
                                        </div>
                                        <div className="text-right">
                                            <span className="text-xs text-muted-foreground block">
                                                Estoque
                                            </span>
                                            <span
                                                className={`inline-flex items-center gap-1 text-sm font-medium ${
                                                    isLowStock
                                                        ? 'text-amber-500 font-semibold'
                                                        : 'text-foreground'
                                                }`}
                                            >
                                                {isLowStock ? (
                                                    <AlertTriangle className="size-3.5" />
                                                ) : (
                                                    <Boxes className="size-3.5 text-muted-foreground" />
                                                )}
                                                {product.current_stock} {product.unit_of_measure}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>
                                            Custo: {formatMoney(product.cost_price_cents)}
                                        </span>
                                        <span>
                                            Mín: {product.min_stock} {product.unit_of_measure}
                                        </span>
                                    </div>

                                    <div className="mt-auto flex justify-end">
                                        <Button asChild variant="outline" size="sm">
                                            <Link href={products.show(product.id)}>
                                                Ver cadastro
                                            </Link>
                                        </Button>
                                    </div>
                                </article>
                            );
                        })}
                    </section>
                )}

                <Pagination links={paginator.links} />
            </PageCanvas>
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Produtos', href: products.index() }],
};
