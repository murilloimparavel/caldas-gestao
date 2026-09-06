import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { FolderTree, Layers, Plus, Tag } from 'lucide-react';
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
import categories from '@/routes/categories';
import type { SharedPageProps } from '@/types';

type CategoryType = 'service' | 'product' | 'general';

type Category = {
    description: string | null;
    id: string;
    is_active: boolean;
    lock_version: number;
    name: string;
    products_count?: number;
    services_count?: number;
    type: CategoryType;
};

type Props = {
    categories: Paginated<Category>;
    filters: ResourceFilters & { type?: string };
};

const categoryTypeLabels: Record<CategoryType, string> = {
    service: 'Serviços',
    product: 'Produtos',
    general: 'Geral',
};

export default function CategoriesIndex({
    categories: paginator,
    filters,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [createKey] = useState(() => createIdempotencyKey('category-create'));
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('category.manage');

    const handleStatusChange = (status: 'active' | 'inactive' | 'all') => {
        router.get(
            categories.index.url(),
            { ...filters, status },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Categorias" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Gestão"
                    title="Categorias"
                    description="Organize seus serviços e produtos físicos em grupos para facilitar o atendimento, a comanda e o controle de estoque."
                    action={
                        canManage ? (
                            <Dialog
                                open={createOpen}
                                onOpenChange={setCreateOpen}
                            >
                                <DialogTrigger asChild>
                                    <Button className="w-full sm:w-auto">
                                        <Plus aria-hidden="true" />
                                        Nova categoria
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-xl">
                                    <DialogHeader>
                                        <DialogTitle>
                                            Nova categoria
                                        </DialogTitle>
                                        <DialogDescription>
                                            Crie grupos para segmentar o
                                            catálogo da unidade.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form
                                        {...categories.store.form()}
                                        headers={{
                                            'X-Idempotency-Key': createKey,
                                        }}
                                        resetOnSuccess
                                        onSuccess={() => setCreateOpen(false)}
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
                                                            label="Nome da categoria"
                                                            name="name"
                                                            error={errors.name}
                                                        >
                                                            <Input
                                                                id="name"
                                                                name="name"
                                                                required
                                                                autoFocus
                                                                placeholder="Ex.: Cabelo, Barba, Cuidados Diários"
                                                            />
                                                        </FormField>
                                                    </div>
                                                    <div className="sm:col-span-2">
                                                        <FormField
                                                            label="Tipo de aplicação"
                                                            name="type"
                                                            error={errors.type}
                                                        >
                                                            <select
                                                                id="type"
                                                                name="type"
                                                                defaultValue="general"
                                                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                                            >
                                                                <option value="general">
                                                                    Geral
                                                                    (Serviços e
                                                                    Produtos)
                                                                </option>
                                                                <option value="service">
                                                                    Apenas
                                                                    Serviços
                                                                </option>
                                                                <option value="product">
                                                                    Apenas
                                                                    Produtos
                                                                </option>
                                                            </select>
                                                        </FormField>
                                                    </div>
                                                    <div className="sm:col-span-2">
                                                        <FormField
                                                            label="Descrição"
                                                            name="description"
                                                            error={
                                                                errors.description
                                                            }
                                                        >
                                                            <textarea
                                                                id="description"
                                                                name="description"
                                                                rows={3}
                                                                placeholder="Finalidade ou detalhes desta categoria"
                                                                className="min-h-24 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
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
                    action={categories.index.url()}
                    defaultValue={filters.search}
                    status={filters.status ?? 'active'}
                    onStatusChange={handleStatusChange}
                    placeholder="Buscar por nome"
                    resultLabel={`${paginator.total} ${paginator.total === 1 ? 'categoria encontrada' : 'categorias encontradas'}`}
                />

                {paginator.data.length === 0 ? (
                    <EmptyState
                        title={
                            filters.search || filters.status === 'inactive'
                                ? 'Nenhuma categoria encontrada'
                                : 'Nenhuma categoria cadastrada'
                        }
                        description={
                            filters.search || filters.status === 'inactive'
                                ? 'Tente outro termo de busca ou altere o filtro de status.'
                                : 'Cadastre categorias para organizar seus produtos e serviços.'
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
                        aria-label="Lista de categorias"
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
                                            <FolderTree
                                                aria-hidden="true"
                                                className="size-5"
                                            />
                                        </div>
                                        <div className="min-w-0">
                                            <h2 className="truncate font-semibold text-foreground">
                                                {category.name}
                                            </h2>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {category.description ||
                                                    'Sem descrição'}
                                            </p>
                                        </div>
                                    </div>
                                    <StatusBadge
                                        status={
                                            category.is_active
                                                ? 'active'
                                                : 'inactive'
                                        }
                                    />
                                </div>
                                <div className="flex items-center justify-between gap-3 border-y border-border py-2 text-sm text-muted-foreground">
                                    <span className="inline-flex items-center gap-1.5 font-medium">
                                        <Tag
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                        {categoryTypeLabels[category.type] ??
                                            category.type}
                                    </span>
                                    <span className="inline-flex items-center gap-1.5 text-xs">
                                        <Layers
                                            aria-hidden="true"
                                            className="size-3.5"
                                        />
                                        {category.services_count ?? 0} serviços
                                        • {category.products_count ?? 0}{' '}
                                        produtos
                                    </span>
                                </div>
                                <div className="mt-auto flex justify-end">
                                    <Button asChild variant="outline" size="sm">
                                        <Link
                                            href={categories.show(category.id)}
                                        >
                                            Ver cadastro
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

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categorias', href: categories.index() }],
};
