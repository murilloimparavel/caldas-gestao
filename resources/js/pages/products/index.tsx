import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Boxes,
    CircleDollarSign,
    FolderTree,
    Package,
    Plus,
    SlidersHorizontal,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { StockAdjustmentDialog } from '@/components/inventory/stock-adjustment-dialog';
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
import { QuickCreateCategoryModal } from '@/components/operational/quick-create-dialogs';
import type { CreatedEntity } from '@/components/operational/quick-create-dialogs';
import { RemoteOptionPicker } from '@/components/remote-option-picker';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { ImageUploader } from '@/components/ui/image-uploader';
import { Input } from '@/components/ui/input';
import {
    ResourceViewToggle,
    useResourceView,
} from '@/components/resource-view-toggle';
import inventory from '@/routes/inventory';
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

type InventorySummary = {
    categories: {
        cost_value_cents: number;
        id: string | null;
        name: string;
        sale_value_cents: number;
    }[];
    total_cost_cents: number;
    total_sale_cents: number;
};

type Props = {
    categoryOptions?: CategoryOption[];
    filters: ResourceFilters & { category_id?: string };
    inventorySummary: InventorySummary;
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
        initialCents > 0
            ? (initialCents / 100).toFixed(2).replace('.', ',')
            : '',
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
    categoryOptions: initialCategoryOptions = [],
    inventorySummary,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const { view, setView } = useResourceView('caldas-gestao:products-view');
    const [selectedPhoto, setSelectedPhoto] = useState<File | null>(null);
    const [createKey] = useState(() => createIdempotencyKey('product-create'));
    const [adjustingProduct, setAdjustingProduct] = useState<Product | null>(
        null,
    );
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('product.manage');
    const canAdjustStock =
        props.auth.permissions.includes('inventory.manage') || canManage;
    const canViewInventory =
        props.auth.permissions.includes('inventory.view') ||
        props.auth.permissions.includes('product.view');

    const [categories, setCategories] = useState<CategoryOption[]>(
        initialCategoryOptions,
    );
    const [selectedCategoryId, setSelectedCategoryId] = useState('');
    const [quickCategoryOpen, setQuickCategoryOpen] = useState(false);

    const handleCategoryCreated = (created: CreatedEntity) => {
        const newOpt: CategoryOption = { id: created.id, name: created.name };
        setCategories((prev) => [
            ...prev.filter((c) => c.id !== created.id),
            newOpt,
        ]);
        setSelectedCategoryId(created.id);
    };

    const handleStatusChange = (status: 'active' | 'inactive' | 'all') => {
        router.get(
            products.index.url(),
            { ...filters, status },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Produtos" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Gestão"
                    title="Produtos"
                    description="Cadastre itens físicos de venda e consumo interno com controle de preço de custo, venda e estoque mínimo."
                    action={
                        <div className="flex flex-wrap items-center gap-2">
                            {canViewInventory ? (
                                <Button asChild variant="outline">
                                    <Link href={inventory.index()}>
                                        <Boxes aria-hidden="true" />
                                        Extrato de estoque
                                    </Link>
                                </Button>
                            ) : null}
                            {canManage ? (
                                <Dialog
                                    open={createOpen}
                                    onOpenChange={(open) => {
                                        setCreateOpen(open);

                                        if (!open) {
                                            setSelectedPhoto(null);
                                        }
                                    }}
                                >
                                    <DialogTrigger asChild>
                                        <Button className="w-full sm:w-auto">
                                            <Plus aria-hidden="true" />
                                            Novo produto
                                        </Button>
                                    </DialogTrigger>

                                    <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                                        <DialogHeader>
                                            <DialogTitle>
                                                Novo produto
                                            </DialogTitle>
                                            <DialogDescription>
                                                Cadastre um produto físico para
                                                venda no caixa ou uso nos
                                                procedimentos.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <Form
                                            {...products.store.form()}
                                            headers={{
                                                'X-Idempotency-Key': createKey,
                                            }}
                                            resetOnSuccess
                                            onSuccess={() => {
                                                setCreateOpen(false);
                                                setSelectedPhoto(null);
                                                setSelectedCategoryId('');
                                            }}
                                            className="space-y-5"
                                        >
                                            {({ errors, processing }) => (
                                                <>
                                                    <FormErrorSummary
                                                        errors={errors}
                                                    />
                                                    <div className="grid gap-4 sm:grid-cols-2">
                                                        <div className="sm:col-span-2">
                                                            <FormField
                                                                label="Foto do produto"
                                                                name="photo"
                                                                error={
                                                                    errors.photo
                                                                }
                                                            >
                                                                <ImageUploader
                                                                    value={
                                                                        selectedPhoto
                                                                    }
                                                                    onChange={
                                                                        setSelectedPhoto
                                                                    }
                                                                    error={
                                                                        errors.photo
                                                                    }
                                                                    aspectRatio="auto"
                                                                    previewHeight="120px"
                                                                />
                                                            </FormField>
                                                        </div>
                                                        <div className="sm:col-span-2">
                                                            <FormField
                                                                label="Nome do produto"
                                                                name="name"
                                                                error={
                                                                    errors.name
                                                                }
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
                                                                error={
                                                                    errors.category_id
                                                                }
                                                                action={
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            setQuickCategoryOpen(
                                                                                true,
                                                                            )
                                                                        }
                                                                        className="rounded-xs text-xs font-semibold text-primary hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                                                                    >
                                                                        + Nova
                                                                        Categoria
                                                                    </button>
                                                                }
                                                            >
                                                                <RemoteOptionPicker
                                                                    id="category_id"
                                                                    name="category_id"
                                                                    options={
                                                                        categories
                                                                    }
                                                                    placeholder="Sem categoria"
                                                                    resource="categories"
                                                                    value={
                                                                        selectedCategoryId
                                                                    }
                                                                    onChange={(
                                                                        value,
                                                                    ) =>
                                                                        setSelectedCategoryId(
                                                                            value,
                                                                        )
                                                                    }
                                                                />
                                                            </FormField>
                                                        </div>

                                                        <div>
                                                            <FormField
                                                                label="Unidade de medida"
                                                                name="unit_of_measure"
                                                                error={
                                                                    errors.unit_of_measure
                                                                }
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
                                                                error={
                                                                    errors.sku
                                                                }
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
                                                                error={
                                                                    errors.barcode
                                                                }
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
                                                            error={
                                                                errors.sale_price_cents
                                                            }
                                                        />

                                                        <MoneyPriceField
                                                            id="cost_price_cents"
                                                            name="cost_price_cents"
                                                            label="Preço de custo"
                                                            helpText="Custo de aquisição do produto."
                                                            error={
                                                                errors.cost_price_cents
                                                            }
                                                        />

                                                        <div>
                                                            <FormField
                                                                label="Estoque atual"
                                                                name="current_stock"
                                                                error={
                                                                    errors.current_stock
                                                                }
                                                            >
                                                                <Input
                                                                    id="current_stock"
                                                                    name="current_stock"
                                                                    type="number"
                                                                    defaultValue={
                                                                        0
                                                                    }
                                                                    required
                                                                />
                                                            </FormField>
                                                        </div>

                                                        <div>
                                                            <FormField
                                                                label="Estoque mínimo"
                                                                name="min_stock"
                                                                error={
                                                                    errors.min_stock
                                                                }
                                                            >
                                                                <Input
                                                                    id="min_stock"
                                                                    name="min_stock"
                                                                    type="number"
                                                                    defaultValue={
                                                                        0
                                                                    }
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
                                                        onCancel={() =>
                                                            setCreateOpen(false)
                                                        }
                                                        label="Cadastrar produto"
                                                    />
                                                </>
                                            )}
                                        </Form>

                                        <QuickCreateCategoryModal
                                            open={quickCategoryOpen}
                                            onOpenChange={setQuickCategoryOpen}
                                            onSuccess={handleCategoryCreated}
                                        />
                                    </DialogContent>
                                </Dialog>
                            ) : null}
                        </div>
                    }
                />

                <SearchToolbar
                    action={products.index.url()}
                    defaultValue={filters.search}
                    status={filters.status ?? 'active'}
                    onStatusChange={handleStatusChange}
                    placeholder="Buscar por nome, SKU ou código de barras"
                    resultLabel={`${paginator.total} ${paginator.total === 1 ? 'produto encontrado' : 'produtos encontrados'}`}
                >
                    <ResourceViewToggle value={view} onChange={setView} />
                </SearchToolbar>

                <section
                    aria-labelledby="inventory-summary-title"
                    className="surface-panel overflow-hidden"
                >
                    <div className="flex flex-col gap-4 p-4 sm:p-5">
                        <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between sm:gap-4">
                            <div>
                                <h2
                                    id="inventory-summary-title"
                                    className="flex items-center gap-2 text-sm font-semibold text-foreground"
                                >
                                    <Boxes className="size-4 text-primary" />
                                    Valor do estoque atual
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Considera todo o estoque da unidade, mesmo
                                    produtos inativos com saldo.
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg border border-border/70 bg-muted/30 p-3">
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <CircleDollarSign className="size-3.5" />
                                    Valor investido (custo)
                                </div>
                                <p className="mt-1 text-xl font-semibold tracking-tight text-foreground">
                                    {formatMoney(
                                        inventorySummary.total_cost_cents,
                                    )}
                                </p>
                            </div>
                            <div className="rounded-lg border border-border/70 bg-muted/30 p-3">
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <TrendingUp className="size-3.5" />
                                    Valor potencial de venda
                                </div>
                                <p className="mt-1 text-xl font-semibold tracking-tight text-foreground">
                                    {formatMoney(
                                        inventorySummary.total_sale_cents,
                                    )}
                                </p>
                            </div>
                        </div>

                        {inventorySummary.categories.length > 0 ? (
                            <div className="overflow-x-auto rounded-lg border border-border/70">
                                <table className="w-full min-w-[520px] text-sm">
                                    <thead className="bg-muted/30 text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-medium">
                                                Categoria
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Custo
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Venda potencial
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/70">
                                        {inventorySummary.categories.map(
                                            (category) => (
                                                <tr
                                                    key={
                                                        category.id ??
                                                        'uncategorized'
                                                    }
                                                >
                                                    <td className="px-3 py-2 font-medium text-foreground">
                                                        {category.name}
                                                    </td>
                                                    <td className="px-3 py-2 text-right text-muted-foreground">
                                                        {formatMoney(
                                                            category.cost_value_cents,
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-right text-muted-foreground">
                                                        {formatMoney(
                                                            category.sale_value_cents,
                                                        )}
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        ) : null}
                    </div>
                </section>

                {paginator.data.length === 0 ? (
                    <EmptyState
                        title={
                            filters.search || filters.status === 'inactive'
                                ? 'Nenhum produto encontrado'
                                : 'Nenhum produto cadastrado'
                        }
                        description={
                            filters.search || filters.status === 'inactive'
                                ? 'Tente outro termo de busca ou altere o filtro de status.'
                                : 'Cadastre produtos físicos para venda e controle de estoque da sua unidade.'
                        }
                        action={
                            !filters.search &&
                            (!filters.status || filters.status === 'active') &&
                            canManage ? (
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
                        className={
                            view === 'cards'
                                ? 'grid gap-3 md:grid-cols-2 xl:grid-cols-3'
                                : 'grid gap-3 md:gap-0 md:divide-y md:overflow-hidden md:rounded-xl md:border md:border-border'
                        }
                    >
                        {paginator.data.map((product) => {
                            const isLowStock =
                                product.current_stock <= product.min_stock;

                            return (
                                <article
                                    key={product.id}
                                    className={`surface-panel flex min-h-56 flex-col gap-4 p-5 transition-colors hover:border-primary/40 ${view === 'list' ? 'max-md:min-h-56 md:min-h-0 md:flex-row md:items-center md:gap-4 md:rounded-none md:border-0 md:border-b md:p-4 md:last:border-b-0' : ''}`}
                                >
                                    <div
                                        className={
                                            view === 'list'
                                                ? 'flex items-start justify-between gap-3 md:w-1/3'
                                                : 'flex items-start justify-between gap-3'
                                        }
                                    >
                                        <div className="flex min-w-0 items-center gap-3">
                                            {product.photo_url ||
                                            product.image_url ? (
                                                <img
                                                    src={
                                                        product.photo_url ||
                                                        product.image_url ||
                                                        undefined
                                                    }
                                                    alt={product.name}
                                                    className="size-11 shrink-0 rounded-2xl border border-border object-cover"
                                                />
                                            ) : (
                                                <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-accent text-accent-foreground">
                                                    <Package
                                                        aria-hidden="true"
                                                        className="size-5"
                                                    />
                                                </div>
                                            )}
                                            <div className="min-w-0">
                                                <h2 className="truncate font-semibold text-foreground">
                                                    {product.name}
                                                </h2>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {product.category?.name ? (
                                                        <span className="inline-flex items-center gap-1">
                                                            <FolderTree className="size-3" />
                                                            {
                                                                product.category
                                                                    .name
                                                            }
                                                        </span>
                                                    ) : (
                                                        'Sem categoria'
                                                    )}
                                                    {product.sku
                                                        ? ` • SKU: ${product.sku}`
                                                        : ''}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex flex-col items-end gap-1">
                                            <StatusBadge
                                                status={
                                                    product.is_active
                                                        ? 'active'
                                                        : 'inactive'
                                                }
                                            />
                                            {isLowStock ? (
                                                <span className="inline-flex items-center gap-1 rounded-md bg-amber-500/10 px-2 py-0.5 text-3xs font-semibold text-amber-500">
                                                    <AlertTriangle className="size-3" />
                                                    Estoque baixo
                                                </span>
                                            ) : null}
                                        </div>
                                    </div>

                                    <div
                                        className={
                                            view === 'list'
                                                ? 'flex flex-1 items-center justify-between gap-3 border-y border-border py-3 md:border-y-0 md:py-0'
                                                : 'flex items-center justify-between gap-3 border-y border-border py-3'
                                        }
                                    >
                                        <div>
                                            <span className="block text-xs text-muted-foreground">
                                                Preço de venda
                                            </span>
                                            <span className="font-semibold text-foreground">
                                                {formatMoney(
                                                    product.sale_price_cents,
                                                )}
                                            </span>
                                        </div>
                                        <div className="text-right">
                                            <span className="block text-xs text-muted-foreground">
                                                Estoque
                                            </span>
                                            <span
                                                className={`inline-flex items-center gap-1 text-sm font-medium ${
                                                    isLowStock
                                                        ? 'font-semibold text-amber-500'
                                                        : 'text-foreground'
                                                }`}
                                            >
                                                {isLowStock ? (
                                                    <AlertTriangle className="size-3.5" />
                                                ) : (
                                                    <Boxes className="size-3.5 text-muted-foreground" />
                                                )}
                                                {product.current_stock}{' '}
                                                {product.unit_of_measure}
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        className={
                                            view === 'list'
                                                ? 'flex items-center justify-between text-xs text-muted-foreground md:w-44 md:gap-3 md:border-l md:border-border md:pl-4'
                                                : 'flex items-center justify-between text-xs text-muted-foreground'
                                        }
                                    >
                                        <span>
                                            Custo:{' '}
                                            {formatMoney(
                                                product.cost_price_cents,
                                            )}
                                        </span>
                                        <span>
                                            Mín: {product.min_stock}{' '}
                                            {product.unit_of_measure}
                                        </span>
                                    </div>

                                    <div
                                        className={
                                            view === 'list'
                                                ? 'mt-auto flex items-center justify-between gap-2 md:mt-0 md:w-auto md:border-l md:border-border md:pl-4'
                                                : 'mt-auto flex items-center justify-between gap-2'
                                        }
                                    >
                                        {canAdjustStock ? (
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                size="sm"
                                                onClick={() =>
                                                    setAdjustingProduct(product)
                                                }
                                            >
                                                <SlidersHorizontal className="size-3.5" />
                                                Ajustar
                                            </Button>
                                        ) : (
                                            <div />
                                        )}
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={products.show(product.id)}
                                            >
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

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Produtos', href: products.index() }],
};
