import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, FolderTree, Layers, Package, Scissors, Tag } from 'lucide-react';
import { useState } from 'react';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    PageCanvas,
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
import services from '@/routes/services';
import type { SharedPageProps } from '@/types';

type CategoryType = 'service' | 'product' | 'general';

type LinkedService = {
    duration_minutes: number;
    id: string;
    name: string;
    price_cents: number;
    status: 'active' | 'inactive';
};

type LinkedProduct = {
    current_stock: number;
    id: string;
    is_active: boolean;
    name: string;
    sale_price_cents: number;
};

type Category = {
    created_at?: string;
    description: string | null;
    id: string;
    is_active: boolean;
    lock_version: number;
    name: string;
    products: LinkedProduct[];
    services: LinkedService[];
    type: CategoryType;
};

type Props = {
    category: Category;
};

const categoryTypeLabels: Record<CategoryType, string> = {
    service: 'Apenas Serviços',
    product: 'Apenas Produtos',
    general: 'Geral (Serviços e Produtos)',
};

export default function CategoryShow({ category }: Props) {
    const [updateKey] = useState(() => createIdempotencyKey('category-update'));
    const [destroyKey] = useState(() => createIdempotencyKey('category-destroy'));
    const [reactivateKey] = useState(() =>
        createIdempotencyKey('category-reactivate'),
    );
    const [inactivateOpen, setInactivateOpen] = useState(false);
    const [reactivateOpen, setReactivateOpen] = useState(false);
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('category.manage');

    return (
        <>
            <Head title={category.name} />
            <PageCanvas>
                <div>
                    <Button asChild variant="ghost" className="mb-4 -ml-3">
                        <Link href={categories.index()}>
                            <ArrowLeft aria-hidden="true" />
                            Voltar para categorias
                        </Link>
                    </Button>
                    <ResourceHeader
                        eyebrow="Cadastro de categoria"
                        title={category.name}
                        description="Atualize o escopo, nome e descrição desta categoria no catálogo."
                        action={<StatusBadge status={category.is_active ? 'active' : 'inactive'} />}
                    />
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(18rem,0.85fr)]">
                    <section className="surface-panel p-5 sm:p-6">
                        <div className="mb-6 space-y-1">
                            <h2 className="text-base font-semibold">Dados da categoria</h2>
                            <p className="text-sm text-muted-foreground">
                                Categorias ativas organizam serviços e produtos para agenda e comanda.
                            </p>
                        </div>
                        {!category.is_active ? (
                            <div className="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-50/40 p-4 text-emerald-950 dark:bg-emerald-950/20 dark:text-emerald-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <p className="font-semibold text-sm">Esta categoria está inativa</p>
                                    <p className="text-xs text-muted-foreground">Ela não pode ser selecionada em novos cadastros de serviços ou produtos até que seja reativada.</p>
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
                            {...categories.update.form(category.id)}
                            headers={{ 'X-Idempotency-Key': updateKey }}
                            className="space-y-5"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <FormErrorSummary errors={errors} />
                                    <input
                                        type="hidden"
                                        name="lock_version"
                                        value={category.lock_version}
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
                                                    defaultValue={category.name}
                                                    required
                                                    disabled={!canManage}
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
                                                    defaultValue={category.type}
                                                    disabled={!canManage}
                                                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    <option value="general">Geral (Serviços e Produtos)</option>
                                                    <option value="service">Apenas Serviços</option>
                                                    <option value="product">Apenas Produtos</option>
                                                </select>
                                            </FormField>
                                        </div>
                                        <div className="sm:col-span-2">
                                            <FormField
                                                label="Descrição"
                                                name="description"
                                                error={errors.description}
                                            >
                                                <textarea
                                                    id="description"
                                                    name="description"
                                                    rows={3}
                                                    defaultValue={category.description ?? ''}
                                                    disabled={!canManage}
                                                    className="min-h-24 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm disabled:cursor-not-allowed disabled:opacity-50"
                                                />
                                            </FormField>
                                        </div>
                                    </div>
                                    {canManage ? (
                                        <div className="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                                            {category.is_active ? (
                                                <Dialog
                                                    open={inactivateOpen}
                                                    onOpenChange={setInactivateOpen}
                                                >
                                                    <DialogTrigger asChild>
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                        >
                                                            Inativar categoria
                                                        </Button>
                                                    </DialogTrigger>
                                                    <DialogContent>
                                                        <DialogHeader>
                                                            <DialogTitle>
                                                                Inativar categoria?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                Itens já vinculados permanecerão intactos, mas a categoria não poderá ser selecionada para novos cadastros.
                                                            </DialogDescription>
                                                        </DialogHeader>
                                                        <Form
                                                            {...categories.destroy.form(category.id)}
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
                                                                        value={category.lock_version}
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
                                                                Reativar categoria?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                A categoria voltará a ficar ativa e poderá ser vinculada a produtos e serviços.
                                                            </DialogDescription>
                                                        </DialogHeader>
                                                        <Form
                                                            {...categories.reactivate.form(category.id)}
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
                                                                        value={category.lock_version}
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
                                Resumo do catálogo
                            </h2>
                            <div className="space-y-3 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="inline-flex items-center gap-2 text-muted-foreground">
                                        <Tag className="size-4" />
                                        Aplicação
                                    </span>
                                    <span className="font-medium text-foreground">
                                        {categoryTypeLabels[category.type] ?? category.type}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="inline-flex items-center gap-2 text-muted-foreground">
                                        <Scissors className="size-4" />
                                        Serviços associados
                                    </span>
                                    <span className="font-semibold text-foreground">
                                        {category.services?.length ?? 0}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="inline-flex items-center gap-2 text-muted-foreground">
                                        <Package className="size-4" />
                                        Produtos associados
                                    </span>
                                    <span className="font-semibold text-foreground">
                                        {category.products?.length ?? 0}
                                    </span>
                                </div>
                            </div>
                        </section>

                        {(category.services?.length ?? 0) > 0 ? (
                            <section className="surface-panel space-y-3 p-5">
                                <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                    Serviços nesta categoria
                                </h2>
                                <div className="space-y-2">
                                    {category.services.map((svc) => (
                                        <div key={svc.id} className="flex items-center justify-between text-sm py-1.5 border-b border-border/50 last:border-0">
                                            <Link href={services.show(svc.id)} className="font-medium text-foreground hover:underline truncate">
                                                {svc.name}
                                            </Link>
                                            <span className="text-xs text-muted-foreground shrink-0 ml-2">
                                                {svc.duration_minutes} min
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        ) : null}

                        {(category.products?.length ?? 0) > 0 ? (
                            <section className="surface-panel space-y-3 p-5">
                                <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                    Produtos nesta categoria
                                </h2>
                                <div className="space-y-2">
                                    {category.products.map((prd) => (
                                        <div key={prd.id} className="flex items-center justify-between text-sm py-1.5 border-b border-border/50 last:border-0">
                                            <Link href={products.show(prd.id)} className="font-medium text-foreground hover:underline truncate">
                                                {prd.name}
                                            </Link>
                                            <span className="text-xs text-muted-foreground shrink-0 ml-2">
                                                Estoque: {prd.current_stock}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        ) : null}
                    </aside>
                </div>
            </PageCanvas>
        </>
    );
}

CategoryShow.layout = {
    breadcrumbs: [
        { title: 'Categorias', href: categories.index() },
        { title: 'Detalhes da categoria', href: '#' },
    ],
};
