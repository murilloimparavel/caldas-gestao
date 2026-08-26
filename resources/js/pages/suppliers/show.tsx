import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    FileText,
    Mail,
    Phone,
    Truck,
} from 'lucide-react';
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
    supplier: Supplier;
};

export default function SupplierShow({ supplier }: Props) {
    const [updateKey] = useState(() => createIdempotencyKey('supplier-update'));
    const [destroyKey] = useState(() => createIdempotencyKey('supplier-destroy'));
    const [reactivateKey] = useState(() =>
        createIdempotencyKey('supplier-reactivate'),
    );
    const [inactivateOpen, setInactivateOpen] = useState(false);
    const [reactivateOpen, setReactivateOpen] = useState(false);
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('supplier.manage');

    return (
        <>
            <Head title={supplier.trade_name || supplier.name} />
            <PageCanvas>
                <div>
                    <Button asChild variant="ghost" className="mb-4 -ml-3">
                        <Link href={suppliers.index()}>
                            <ArrowLeft aria-hidden="true" />
                            Voltar para fornecedores
                        </Link>
                    </Button>
                    <ResourceHeader
                        eyebrow="Cadastro de fornecedor"
                        title={supplier.trade_name || supplier.name}
                        description="Atualize dados cadastrais, informações de contato e condições comerciais do fornecedor."
                        action={<StatusBadge status={supplier.is_active ? 'active' : 'inactive'} />}
                    />
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(18rem,0.85fr)]">
                    <section className="surface-panel p-5 sm:p-6">
                        <div className="mb-6 space-y-1">
                            <h2 className="text-base font-semibold">
                                Dados cadastrais
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Mantenha as informações atualizadas para emissão de pedidos e cotações.
                            </p>
                        </div>
                        <Form
                            {...suppliers.update.form(supplier.id)}
                            headers={{ 'X-Idempotency-Key': updateKey }}
                            className="space-y-5"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <FormErrorSummary errors={errors} />
                                    <input
                                        type="hidden"
                                        name="lock_version"
                                        value={supplier.lock_version}
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
                                                    defaultValue={supplier.name}
                                                    required
                                                    disabled={!canManage}
                                                />
                                            </FormField>
                                        </div>

                                        <div>
                                            <FormField
                                                label="Nome Fantasia"
                                                name="trade_name"
                                                error={errors.trade_name}
                                            >
                                                <Input
                                                    id="trade_name"
                                                    name="trade_name"
                                                    defaultValue={supplier.trade_name ?? ''}
                                                    disabled={!canManage}
                                                />
                                            </FormField>
                                        </div>

                                        <div>
                                            <FormField
                                                label="CNPJ / CPF"
                                                name="document_number"
                                                error={errors.document_number}
                                            >
                                                <Input
                                                    id="document_number"
                                                    name="document_number"
                                                    defaultValue={supplier.document_number ?? ''}
                                                    disabled={!canManage}
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
                                                    defaultValue={supplier.email ?? ''}
                                                    disabled={!canManage}
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
                                                    defaultValue={supplier.phone ?? ''}
                                                    disabled={!canManage}
                                                />
                                            </FormField>
                                        </div>

                                        <div className="sm:col-span-2">
                                            <FormField
                                                label="Status"
                                                name="is_active"
                                                error={errors.is_active}
                                            >
                                                <select
                                                    id="is_active"
                                                    name="is_active"
                                                    defaultValue={supplier.is_active ? '1' : '0'}
                                                    disabled={!canManage}
                                                    className="h-11 w-full rounded-md border border-input bg-transparent px-3 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    <option value="1">Ativo</option>
                                                    <option value="0">Inativo</option>
                                                </select>
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
                                                    rows={4}
                                                    defaultValue={supplier.notes ?? ''}
                                                    disabled={!canManage}
                                                    placeholder="Condições comerciais, prazos de entrega ou dados bancários"
                                                    className="min-h-28 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm disabled:cursor-not-allowed disabled:opacity-50"
                                                />
                                            </FormField>
                                        </div>
                                    </div>

                                    {canManage ? (
                                        <FormActions
                                            processing={processing}
                                            label="Salvar alterações"
                                        />
                                    ) : (
                                        <p
                                            className="rounded-lg border border-dashed border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
                                            role="status"
                                        >
                                            Você tem acesso somente para consulta a este cadastro.
                                        </p>
                                    )}
                                </>
                            )}
                        </Form>
                    </section>

                    <aside className="space-y-5">
                        <section className="surface-panel p-5 sm:p-6">
                            <h2 className="text-base font-semibold">
                                Resumo de contato
                            </h2>
                            <div className="mt-5 grid gap-4">
                                <div className="flex items-start gap-3">
                                    <Building2
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 text-muted-foreground shrink-0"
                                    />
                                    <div className="min-w-0">
                                        <p className="text-xs text-muted-foreground">
                                            Razão Social
                                        </p>
                                        <p className="mt-0.5 truncate text-sm font-medium">
                                            {supplier.name}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-start gap-3">
                                    <FileText
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 text-muted-foreground shrink-0"
                                    />
                                    <div className="min-w-0">
                                        <p className="text-xs text-muted-foreground">
                                            Documento (CNPJ/CPF)
                                        </p>
                                        <p className="mt-0.5 truncate text-sm font-medium">
                                            {supplier.document_number || 'Não informado'}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-start gap-3">
                                    <Phone
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 text-muted-foreground shrink-0"
                                    />
                                    <div className="min-w-0">
                                        <p className="text-xs text-muted-foreground">
                                            Telefone
                                        </p>
                                        <p className="mt-0.5 truncate text-sm font-medium">
                                            {supplier.phone || 'Não informado'}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-start gap-3">
                                    <Mail
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 text-muted-foreground shrink-0"
                                    />
                                    <div className="min-w-0">
                                        <p className="text-xs text-muted-foreground">
                                            E-mail
                                        </p>
                                        <p className="mt-0.5 truncate text-sm font-medium">
                                            {supplier.email || 'Não informado'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {canManage ? (
                            !supplier.is_active ? (
                                <section className="surface-panel border-emerald-500/30 bg-emerald-50/20 p-5 sm:p-6 dark:bg-emerald-950/20">
                                    <div className="flex items-start gap-3">
                                        <Truck
                                            aria-hidden="true"
                                            className="mt-0.5 size-4 text-emerald-600 dark:text-emerald-400 shrink-0"
                                        />
                                        <div>
                                            <h2 className="text-base font-semibold text-foreground">
                                                Reativar fornecedor
                                            </h2>
                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                Este fornecedor está atualmente inativo. Reative o cadastro para utilizá-lo novamente em pedidos, compras e entradas de estoque.
                                            </p>
                                        </div>
                                    </div>
                                    <Dialog
                                        open={reactivateOpen}
                                        onOpenChange={setReactivateOpen}
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                type="button"
                                                className="mt-4 w-full bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                                            >
                                                Reativar cadastro
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Reativar fornecedor?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    O fornecedor voltará a ficar ativo e poderá ser selecionado em novos lançamentos e pedidos.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <Form
                                                {...suppliers.reactivate.form(
                                                    supplier.id,
                                                )}
                                                method="patch"
                                                headers={{
                                                    'X-Idempotency-Key':
                                                        reactivateKey,
                                                }}
                                                onSuccess={() =>
                                                    setReactivateOpen(false)
                                                }
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="lock_version"
                                                            value={
                                                                supplier.lock_version
                                                            }
                                                        />
                                                        <DialogFooter className="mt-4">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    setReactivateOpen(
                                                                        false,
                                                                    )
                                                                }
                                                            >
                                                                Cancelar
                                                            </Button>
                                                            <Button
                                                                type="submit"
                                                                disabled={
                                                                    processing
                                                                }
                                                                className="bg-emerald-600 text-white hover:bg-emerald-700"
                                                            >
                                                                {processing
                                                                    ? 'Reativando…'
                                                                    : 'Confirmar reativação'}
                                                            </Button>
                                                        </DialogFooter>
                                                    </>
                                                )}
                                            </Form>
                                        </DialogContent>
                                    </Dialog>
                                </section>
                            ) : (
                                <section className="surface-panel border-destructive/30 p-5 sm:p-6">
                                    <div className="flex items-start gap-3">
                                        <Truck
                                            aria-hidden="true"
                                            className="mt-0.5 size-4 text-destructive shrink-0"
                                        />
                                        <div>
                                            <h2 className="text-base font-semibold">
                                                Desativar fornecedor
                                            </h2>
                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                O histórico de movimentações e pedidos é preservado. O fornecedor pode ser reativado a qualquer momento.
                                            </p>
                                        </div>
                                    </div>
                                    <Dialog
                                        open={inactivateOpen}
                                        onOpenChange={setInactivateOpen}
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                className="mt-4 w-full"
                                            >
                                                Desativar fornecedor
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Desativar fornecedor?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    O fornecedor deixará de aparecer em novas compras e entradas, preservando o histórico existente.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <Form
                                                {...suppliers.destroy.form(
                                                    supplier.id,
                                                )}
                                                headers={{
                                                    'X-Idempotency-Key':
                                                        destroyKey,
                                                }}
                                                method="delete"
                                                onSuccess={() =>
                                                    setInactivateOpen(false)
                                                }
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="lock_version"
                                                            value={
                                                                supplier.lock_version
                                                            }
                                                        />
                                                        <DialogFooter className="mt-4">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    setInactivateOpen(
                                                                        false,
                                                                    )
                                                                }
                                                            >
                                                                Cancelar
                                                            </Button>
                                                            <Button
                                                                type="submit"
                                                                variant="destructive"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                {processing
                                                                    ? 'Desativando…'
                                                                    : 'Confirmar desativação'}
                                                            </Button>
                                                        </DialogFooter>
                                                    </>
                                                )}
                                            </Form>
                                        </DialogContent>
                                    </Dialog>
                                </section>
                            )
                        ) : null}
                    </aside>
                </div>
            </PageCanvas>
        </>
    );
}

SupplierShow.layout = {
    breadcrumbs: [
        { title: 'Fornecedores', href: suppliers.index() },
        { title: 'Cadastro', href: '#' },
    ],
};
