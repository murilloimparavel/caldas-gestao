import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Layers, Plus, Receipt, ShieldAlert, Sparkles, Tag } from 'lucide-react';
import { useState } from 'react';
import {
    createIdempotencyKey,
    EmptyState,
    FormActions,
    FormErrorSummary,
    FormField,
    PageCanvas,
    Pagination,
    ResourceHeader,
    SearchToolbar,
    StatusBadge,
} from '@/components/operational';
import type { Paginated, ResourceFilters } from '@/components/operational';
import { Badge } from '@/components/ui/badge';
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
import saleCategories from '@/routes/sale-categories';
import type { SharedPageProps } from '@/types';

type SaleCategoryType = 'service' | 'product' | 'mixed';
type UniquenessScope = 'customer' | 'appointment' | 'reference' | 'none';

type SaleCategory = {
    id: string;
    is_active: boolean;
    key: string;
    lock_version: number;
    name: string;
    sales_count?: number;
    type: SaleCategoryType;
    uniqueness_scope: UniquenessScope;
};

type Props = {
    categories: Paginated<SaleCategory>;
    filters: ResourceFilters & { type?: string; uniqueness_scope?: string };
};

const saleCategoryTypeLabels: Record<SaleCategoryType, string> = {
    service: 'Apenas Serviços',
    product: 'Apenas Produtos',
    mixed: 'Misto (Serviços e Produtos)',
};

const uniquenessScopeLabels: Record<UniquenessScope, string> = {
    customer: '1 comanda por cliente',
    appointment: '1 comanda por agendamento',
    reference: '1 comanda por referência (mesa/pedido)',
    none: 'Sem limite de duplicidade',
};

export default function SaleCategoriesIndex({
    categories: paginator,
    filters,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [createKey] = useState(() => createIdempotencyKey('sale-category-create'));
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('sale_category.manage');

    const handleStatusChange = (status: 'active' | 'inactive' | 'all') => {
        router.get(
            saleCategories.index.url(),
            { ...filters, status },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Categorias de Comanda" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Operação & Checkout"
                    title="Categorias de Comanda"
                    description="Configure as áreas de atendimento e consumo da sua unidade, governando unicidade e tipos de item permitidos."
                    action={
                        canManage ? (
                            <Dialog
                                open={createOpen}
                                onOpenChange={setCreateOpen}
                            >
                                <DialogTrigger asChild>
                                    <Button className="w-full sm:w-auto">
                                        <Plus aria-hidden="true" />
                                        Nova categoria de comanda
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-xl">
                                    <DialogHeader>
                                        <DialogTitle>Nova categoria de comanda</DialogTitle>
                                        <DialogDescription>
                                            Defina uma área de consumo para segmentar comandas (ex: Barbearia, Loja, Bar, Restaurante).
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form
                                        {...saleCategories.store.form()}
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
                                                            label="Nome da categoria"
                                                            name="name"
                                                            error={errors.name}
                                                        >
                                                            <Input
                                                                id="name"
                                                                name="name"
                                                                required
                                                                autoFocus
                                                                placeholder="Ex.: Barbearia, Loja, Restaurante, Bar"
                                                            />
                                                        </FormField>
                                                    </div>
                                                    <div className="sm:col-span-2">
                                                        <FormField
                                                            label="Chave estável (opcional)"
                                                            name="key"
                                                            error={errors.key}
                                                        >
                                                            <Input
                                                                id="key"
                                                                name="key"
                                                                placeholder="Ex.: barbearia, loja, bar (deixe em branco para autogerar)"
                                                            />
                                                        </FormField>
                                                    </div>
                                                    <div className="sm:col-span-1">
                                                        <FormField
                                                            label="Tipo de itens permitidos"
                                                            name="type"
                                                            error={errors.type}
                                                        >
                                                            <select
                                                                id="type"
                                                                name="type"
                                                                defaultValue="service"
                                                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                                            >
                                                                <option value="service">Apenas Serviços</option>
                                                                <option value="product">Apenas Produtos</option>
                                                                <option value="mixed">Misto (Serviços e Produtos)</option>
                                                            </select>
                                                        </FormField>
                                                    </div>
                                                    <div className="sm:col-span-1">
                                                        <FormField
                                                            label="Escopo de unicidade"
                                                            name="uniqueness_scope"
                                                            error={errors.uniqueness_scope}
                                                        >
                                                            <select
                                                                id="uniqueness_scope"
                                                                name="uniqueness_scope"
                                                                defaultValue="none"
                                                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                                            >
                                                                <option value="none">Sem limite (múltiplas abertas)</option>
                                                                <option value="customer">Por Cliente (1 ativa por cliente)</option>
                                                                <option value="appointment">Por Agendamento (1 ativa por agenda)</option>
                                                                <option value="reference">Por Referência (1 ativa por mesa/pedido)</option>
                                                            </select>
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
                                                    label="Cadastrar categoria"
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
                    action={saleCategories.index.url()}
                    defaultValue={filters.search}
                    status={filters.status ?? 'active'}
                    onStatusChange={handleStatusChange}
                    placeholder="Buscar por nome ou chave"
                    resultLabel={`${paginator.total} ${paginator.total === 1 ? 'categoria encontrada' : 'categorias encontradas'}`}
                />

                {paginator.data.length === 0 ? (
                    <EmptyState
                        title={
                            filters.search || filters.status === 'inactive'
                                ? 'Nenhuma categoria encontrada'
                                : 'Nenhuma categoria de comanda cadastrada'
                        }
                        description={
                            filters.search || filters.status === 'inactive'
                                ? 'Tente outro termo de busca ou altere o filtro de status.'
                                : 'Cadastre categorias de comanda para iniciar a operação de consumo e atendimento.'
                        }
                        action={
                            !filters.search &&
                            (!filters.status || filters.status === 'active') &&
                            canManage ? (
                                <Button onClick={() => setCreateOpen(true)}>
                                    <Plus aria-hidden="true" />
                                    Cadastrar primeira categoria
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <section
                        aria-label="Lista de categorias de comanda"
                        className="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
                    >
                        {paginator.data.map((category) => (
                            <article
                                key={category.id}
                                className="surface-panel flex min-h-48 flex-col gap-4 p-5 transition-colors hover:border-primary/40"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex min-w-0 items-center gap-3">
                                        <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-accent text-accent-foreground">
                                            <Receipt
                                                aria-hidden="true"
                                                className="size-5"
                                            />
                                        </div>
                                        <div className="min-w-0">
                                            <h2 className="truncate font-semibold text-foreground">
                                                {category.name}
                                            </h2>
                                            <p className="truncate text-xs font-mono text-muted-foreground">
                                                chave: {category.key}
                                            </p>
                                        </div>
                                    </div>
                                    <StatusBadge status={category.is_active ? 'active' : 'inactive'} />
                                </div>
                                <div className="flex flex-col gap-2 border-y border-border py-2 text-sm text-muted-foreground">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="inline-flex items-center gap-1.5 font-medium">
                                            <Tag aria-hidden="true" className="size-4" />
                                            {saleCategoryTypeLabels[category.type] ?? category.type}
                                        </span>
                                        <span className="inline-flex items-center gap-1.5 text-xs">
                                            <Layers aria-hidden="true" className="size-3.5" />
                                            {category.sales_count ?? 0} comandas
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-1.5 text-xs">
                                        <ShieldAlert aria-hidden="true" className="size-3.5 text-muted-foreground shrink-0" />
                                        <span className="truncate">
                                            {uniquenessScopeLabels[category.uniqueness_scope] ?? category.uniqueness_scope}
                                        </span>
                                    </div>
                                </div>
                                <div className="mt-auto flex justify-end">
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={saleCategories.show(category.id)}>
                                            Ver configuração
                                        </Link>
                                    </Button>
                                </div>
                            </article>
                        ))}
                    </section>
                )}

                <Pagination links={paginator.links} />
            </PageCanvas>
        </>
    );
}

SaleCategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categorias de Comanda', href: saleCategories.index() }],
};
