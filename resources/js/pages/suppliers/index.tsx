import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, FileText, Mail, Phone, Plus, Truck } from 'lucide-react';
import { useState } from 'react';
import {
    useIdempotencyKey,
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
import {
    ResourceViewToggle,
    useResourceView,
} from '@/components/resource-view-toggle';
import suppliers from '@/routes/suppliers';
import type { SharedPageProps } from '@/types';

type Supplier = {
    document_number: string | null;
    email: string | null;
    id: string;
    is_active: boolean;
    lock_version: number;
    name: string;
    notes: string | null;
    phone: string | null;
    trade_name: string | null;
};

type Props = {
    filters: ResourceFilters;
    suppliers: Paginated<Supplier>;
};

export default function SuppliersIndex({
    suppliers: paginator,
    filters,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const { view, setView } = useResourceView('caldas-gestao:suppliers-view');
    const [createKey, rotateCreateKey] = useIdempotencyKey('supplier-store');
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('supplier.manage');

    const handleStatusChange = (status: 'active' | 'inactive' | 'all') => {
        router.get(
            suppliers.index.url(),
            { ...filters, status },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Fornecedores" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Gestão"
                    title="Fornecedores"
                    description="Cadastre e gerencie os parceiros e fornecedores de produtos e insumos da sua unidade."
                    action={
                        canManage ? (
                            <Dialog
                                open={createOpen}
                                onOpenChange={(open) => {
                                    if (open) {
                                        rotateCreateKey();
                                    }

                                    setCreateOpen(open);
                                }}
                            >
                                <DialogTrigger asChild>
                                    <Button className="w-full sm:w-auto">
                                        <Plus aria-hidden="true" />
                                        Novo fornecedor
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                                    <DialogHeader>
                                        <DialogTitle>
                                            Novo fornecedor
                                        </DialogTitle>
                                        <DialogDescription>
                                            Cadastre os dados cadastrais e de
                                            contato do fornecedor.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form
                                        {...suppliers.store.form()}
                                        headers={{
                                            'X-Idempotency-Key': createKey,
                                        }}
                                        onChange={rotateCreateKey}
                                        resetOnSuccess
                                        onSuccess={() => {
                                            rotateCreateKey();
                                            setCreateOpen(false);
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
                                                            label="Razão Social / Nome"
                                                            name="name"
                                                            error={errors.name}
                                                        >
                                                            <Input
                                                                id="name"
                                                                name="name"
                                                                required
                                                                autoFocus
                                                                placeholder="Ex.: Distribuidora de Cosméticos Silva Ltda"
                                                            />
                                                        </FormField>
                                                    </div>

                                                    <div>
                                                        <FormField
                                                            label="Nome Fantasia"
                                                            name="trade_name"
                                                            error={
                                                                errors.trade_name
                                                            }
                                                        >
                                                            <Input
                                                                id="trade_name"
                                                                name="trade_name"
                                                                placeholder="Ex.: Silva Cosméticos"
                                                            />
                                                        </FormField>
                                                    </div>

                                                    <div>
                                                        <FormField
                                                            label="CNPJ / CPF"
                                                            name="document_number"
                                                            error={
                                                                errors.document_number
                                                            }
                                                        >
                                                            <Input
                                                                id="document_number"
                                                                name="document_number"
                                                                placeholder="00.000.000/0001-00"
                                                            />
                                                        </FormField>
                                                    </div>

                                                    <div>
                                                        <FormField
                                                            label="E-mail"
                                                            name="email"
                                                            error={errors.email}
                                                        >
                                                            <Input
                                                                id="email"
                                                                name="email"
                                                                type="email"
                                                                placeholder="contato@fornecedor.com.br"
                                                            />
                                                        </FormField>
                                                    </div>

                                                    <div>
                                                        <FormField
                                                            label="Telefone"
                                                            name="phone"
                                                            error={errors.phone}
                                                        >
                                                            <Input
                                                                id="phone"
                                                                name="phone"
                                                                inputMode="tel"
                                                                placeholder="(00) 00000-0000"
                                                            />
                                                        </FormField>
                                                    </div>

                                                    <div className="sm:col-span-2">
                                                        <FormField
                                                            label="Observações"
                                                            name="notes"
                                                            error={errors.notes}
                                                        >
                                                            <textarea
                                                                id="notes"
                                                                name="notes"
                                                                rows={3}
                                                                placeholder="Prazos de entrega, condições comerciais ou contatos de vendedores"
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
                                                    label="Cadastrar fornecedor"
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
                    action={suppliers.index.url()}
                    defaultValue={filters.search}
                    status={filters.status ?? 'active'}
                    onStatusChange={handleStatusChange}
                    placeholder="Buscar por razão social, nome fantasia ou documento"
                    resultLabel={`${paginator.total} ${paginator.total === 1 ? 'fornecedor encontrado' : 'fornecedores encontrados'}`}
                >
                    <ResourceViewToggle value={view} onChange={setView} />
                </SearchToolbar>

                {paginator.data.length === 0 ? (
                    <EmptyState
                        title={
                            filters.search || filters.status === 'inactive'
                                ? 'Nenhum fornecedor encontrado'
                                : 'Nenhum fornecedor cadastrado'
                        }
                        description={
                            filters.search || filters.status === 'inactive'
                                ? 'Tente outro termo de busca ou altere o filtro de status.'
                                : 'Cadastre fornecedores e parceiros de produtos para gerenciar compras e insumos da sua unidade.'
                        }
                        action={
                            !filters.search &&
                            (!filters.status || filters.status === 'active') &&
                            canManage ? (
                                <Button onClick={() => setCreateOpen(true)}>
                                    <Plus aria-hidden="true" />
                                    Cadastrar primeiro fornecedor
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <section
                        aria-label="Lista de fornecedores"
                        className={
                            view === 'cards'
                                ? 'grid gap-3 md:grid-cols-2 xl:grid-cols-3'
                                : 'grid gap-3 md:gap-0 md:divide-y md:overflow-hidden md:rounded-xl md:border md:border-border'
                        }
                    >
                        {paginator.data.map((supplier) => (
                            <article
                                key={supplier.id}
                                className={`surface-panel flex min-h-52 flex-col gap-4 p-5 transition-colors hover:border-primary/40 ${view === 'list' ? 'max-md:min-h-52 md:min-h-0 md:flex-row md:items-center md:gap-4 md:rounded-none md:border-0 md:border-b md:p-4 md:last:border-b-0' : ''}`}
                            >
                                <div
                                    className={
                                        view === 'list'
                                            ? 'flex items-start justify-between gap-3 md:w-1/3'
                                            : 'flex items-start justify-between gap-3'
                                    }
                                >
                                    <div className="flex min-w-0 items-center gap-3">
                                        <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-accent text-accent-foreground">
                                            <Truck
                                                aria-hidden="true"
                                                className="size-5"
                                            />
                                        </div>
                                        <div className="min-w-0">
                                            <h2 className="truncate font-semibold text-foreground">
                                                {supplier.name}
                                            </h2>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {supplier.trade_name ? (
                                                    <span className="inline-flex items-center gap-1">
                                                        <Building2 className="size-3" />
                                                        {supplier.trade_name}
                                                    </span>
                                                ) : (
                                                    'Nome fantasia não informado'
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <StatusBadge
                                        status={
                                            supplier.is_active
                                                ? 'active'
                                                : 'inactive'
                                        }
                                    />
                                </div>

                                <div
                                    className={
                                        view === 'list'
                                            ? 'grid flex-1 gap-2 border-y border-border py-3 text-xs text-muted-foreground md:border-y-0 md:border-l md:py-0 md:pl-4'
                                            : 'grid gap-2 border-y border-border py-3 text-xs text-muted-foreground'
                                    }
                                >
                                    <div className="flex items-center gap-2">
                                        <FileText className="size-3.5 shrink-0 text-muted-foreground" />
                                        <span className="truncate">
                                            {supplier.document_number
                                                ? `Doc: ${supplier.document_number}`
                                                : 'Documento não informado'}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Phone className="size-3.5 shrink-0 text-muted-foreground" />
                                        <span className="truncate">
                                            {supplier.phone ||
                                                'Telefone não informado'}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Mail className="size-3.5 shrink-0 text-muted-foreground" />
                                        <span className="truncate">
                                            {supplier.email ||
                                                'E-mail não informado'}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    className={
                                        view === 'list'
                                            ? 'mt-auto flex justify-end md:mt-0 md:border-l md:border-border md:pl-4'
                                            : 'mt-auto flex justify-end'
                                    }
                                >
                                    <Button asChild variant="outline" size="sm">
                                        <Link
                                            href={suppliers.show(supplier.id)}
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

SuppliersIndex.layout = {
    breadcrumbs: [{ title: 'Fornecedores', href: suppliers.index() }],
};
