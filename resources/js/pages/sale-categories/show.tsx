import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Receipt,
    ShieldAlert,
    Sparkles,
    Tag,
    Zap,
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
import saleCategories from '@/routes/sale-categories';
import type { SharedPageProps } from '@/types';

type SaleCategoryType = 'service' | 'product' | 'mixed';
type UniquenessScope = 'customer' | 'appointment' | 'reference' | 'none';

type SaleCategory = {
    appointment_automation_enabled?: boolean;
    created_at?: string;
    id: string;
    is_active: boolean;
    is_default_for_appointments?: boolean;
    key: string;
    lock_version: number;
    name: string;
    sales_count?: number;
    type: SaleCategoryType;
    uniqueness_scope: UniquenessScope;
};

type Props = {
    appointment_automation?: {
        appointment_automation_enabled?: boolean;
        default_appointment_category_id?: string | null;
        default_sale_category_id?: string | null;
        enabled?: boolean;
    };
    category: SaleCategory;
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

const uniquenessScopeDescriptions: Record<UniquenessScope, string> = {
    customer:
        'Impede a abertura de mais de uma comanda aberta simultaneamente para o mesmo cliente nesta categoria (ex: Barbearia).',
    appointment:
        'Vincula a comanda estritamente a um agendamento específico, impedindo duplicata.',
    reference:
        'Impede mais de uma comanda aberta com a mesma referência ou mesa (ex: Restaurante mesa 4).',
    none: 'Permite abrir qualquer quantidade de comandas nesta categoria sem restrição de duplicidade.',
};

export default function SaleCategoryShow({
    appointment_automation: automation,
    category,
}: Props) {
    const [updateKey] = useState(() =>
        createIdempotencyKey('sale-category-update'),
    );
    const [destroyKey] = useState(() =>
        createIdempotencyKey('sale-category-destroy'),
    );
    const [reactivateKey] = useState(() =>
        createIdempotencyKey('sale-category-reactivate'),
    );
    const [inactivateOpen, setInactivateOpen] = useState(false);
    const [reactivateOpen, setReactivateOpen] = useState(false);
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('sale_category.manage');
    const defaultCategoryId =
        automation?.default_sale_category_id ??
        automation?.default_appointment_category_id;
    const isDefaultCategory = Boolean(
        category.is_default_for_appointments ??
        (defaultCategoryId && defaultCategoryId === category.id),
    );
    const automationEnabled = Boolean(
        (automation?.enabled ?? category.appointment_automation_enabled) &&
        isDefaultCategory,
    );
    const acceptsServices =
        category.type === 'service' || category.type === 'mixed';
    const canBeAutomationCategory = category.is_active && acceptsServices;
    const canToggleAutomation =
        canManage && canBeAutomationCategory && isDefaultCategory;

    return (
        <>
            <Head title={`Categoria: ${category.name}`} />
            <PageCanvas>
                <div>
                    <Button asChild variant="ghost" className="mb-4 -ml-3">
                        <Link href={saleCategories.index()}>
                            <ArrowLeft aria-hidden="true" />
                            Voltar para categorias de comanda
                        </Link>
                    </Button>
                    <ResourceHeader
                        eyebrow="Configuração de comanda"
                        title={category.name}
                        description="Atualize as regras operacionais, tipos permitidos e unicidade desta área de consumo."
                        action={
                            <StatusBadge
                                status={
                                    category.is_active ? 'active' : 'inactive'
                                }
                            />
                        }
                    />
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(18rem,0.85fr)]">
                    <section className="surface-panel p-5 sm:p-6">
                        <div className="mb-6 space-y-1">
                            <h2 className="text-base font-semibold">
                                Regras da categoria
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                As configurações governam como o operador abre e
                                manipula comandas nesta área.
                            </p>
                        </div>
                        {!category.is_active ? (
                            <div className="mb-6 flex flex-col gap-3 rounded-xl border border-emerald-500/30 bg-emerald-50/40 p-4 text-emerald-950 sm:flex-row sm:items-center sm:justify-between dark:bg-emerald-950/20 dark:text-emerald-200">
                                <div>
                                    <p className="text-sm font-semibold">
                                        Esta categoria de comanda está inativa
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Não é possível abrir novas comandas
                                        nesta categoria até que seja reativada.
                                    </p>
                                </div>
                                {canManage ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={() => setReactivateOpen(true)}
                                        className="shrink-0 bg-emerald-600 text-white hover:bg-emerald-700"
                                    >
                                        Reativar cadastro
                                    </Button>
                                ) : null}
                            </div>
                        ) : null}
                        <Form
                            {...saleCategories.update.form(category.id)}
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
                                                label="Chave estável"
                                                name="key"
                                                error={errors.key}
                                            >
                                                <Input
                                                    id="key"
                                                    name="key"
                                                    defaultValue={category.key}
                                                    required
                                                    disabled={!canManage}
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
                                                    defaultValue={category.type}
                                                    disabled={!canManage}
                                                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                                >
                                                    <option value="service">
                                                        Apenas Serviços
                                                    </option>
                                                    <option value="product">
                                                        Apenas Produtos
                                                    </option>
                                                    <option value="mixed">
                                                        Misto (Serviços e
                                                        Produtos)
                                                    </option>
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
                                                    defaultValue={
                                                        category.uniqueness_scope
                                                    }
                                                    disabled={!canManage}
                                                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                                >
                                                    <option value="none">
                                                        Sem limite de
                                                        duplicidade
                                                    </option>
                                                    <option value="customer">
                                                        1 comanda ativa por
                                                        cliente
                                                    </option>
                                                    <option value="appointment">
                                                        1 comanda ativa por
                                                        agendamento
                                                    </option>
                                                    <option value="reference">
                                                        1 comanda ativa por
                                                        referência (mesa/pedido)
                                                    </option>
                                                </select>
                                            </FormField>
                                        </div>
                                    </div>
                                    <div className="space-y-3 rounded-xl border border-primary/20 bg-primary/5 p-4">
                                        <div className="flex items-start gap-3">
                                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <Zap
                                                    aria-hidden="true"
                                                    className="size-4"
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <h3 className="text-sm font-semibold text-foreground">
                                                    Automação com a Agenda
                                                </h3>
                                                <p className="text-xs leading-5 text-muted-foreground">
                                                    Ao criar um novo agendamento
                                                    com serviço, o sistema pode
                                                    abrir uma comanda nesta
                                                    categoria e incluir o
                                                    serviço automaticamente.
                                                </p>
                                            </div>
                                        </div>
                                        {canToggleAutomation ? (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="appointment_automation_enabled"
                                                    value="0"
                                                />
                                                <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border/70 bg-background/50 p-3">
                                                    <input
                                                        type="checkbox"
                                                        name="appointment_automation_enabled"
                                                        value="1"
                                                        defaultChecked={
                                                            automationEnabled
                                                        }
                                                        className="mt-0.5 size-4 accent-primary"
                                                    />
                                                    <span className="space-y-0.5">
                                                        <span className="block text-sm font-medium text-foreground">
                                                            Criar comandas
                                                            automaticamente
                                                        </span>
                                                        <span className="block text-xs leading-5 text-muted-foreground">
                                                            A automação vale
                                                            para novos
                                                            agendamentos desta
                                                            unidade e não altera
                                                            comandas já
                                                            existentes.
                                                        </span>
                                                    </span>
                                                </label>
                                            </>
                                        ) : (
                                            <div className="rounded-lg border border-border/70 bg-background/50 p-3 text-xs leading-5 text-muted-foreground">
                                                {canBeAutomationCategory
                                                    ? 'Defina esta categoria como padrão para controlar a automação. Depois, você poderá ativar ou desativar a criação automática aqui.'
                                                    : 'Disponível somente para categorias ativas que aceitam serviços.'}
                                            </div>
                                        )}
                                        <input
                                            type="hidden"
                                            name="is_default_for_appointments"
                                            value="0"
                                        />
                                        <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border/70 bg-background/50 p-3 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60">
                                            <input
                                                type="checkbox"
                                                name="is_default_for_appointments"
                                                value="1"
                                                defaultChecked={
                                                    isDefaultCategory
                                                }
                                                disabled={
                                                    !canManage ||
                                                    !canBeAutomationCategory
                                                }
                                                className="mt-0.5 size-4 accent-primary"
                                            />
                                            <span className="space-y-0.5">
                                                <span className="flex items-center gap-1.5 text-sm font-medium text-foreground">
                                                    <CheckCircle2
                                                        aria-hidden="true"
                                                        className="size-4 text-emerald-600"
                                                    />
                                                    Usar esta como categoria
                                                    padrão
                                                </span>
                                                <span className="block text-xs leading-5 text-muted-foreground">
                                                    Apenas uma categoria por
                                                    unidade pode ser padrão.
                                                    Marcar esta substitui a
                                                    anterior.
                                                </span>
                                            </span>
                                        </label>
                                        {isDefaultCategory &&
                                        automationEnabled ? (
                                            <p className="text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                                Esta categoria está ativa como
                                                padrão para novos agendamentos.
                                            </p>
                                        ) : null}
                                    </div>
                                    {canManage ? (
                                        <div className="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                                            {category.is_active ? (
                                                <Dialog
                                                    open={inactivateOpen}
                                                    onOpenChange={
                                                        setInactivateOpen
                                                    }
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
                                                                Inativar
                                                                categoria de
                                                                comanda?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                Comandas abertas
                                                                ou históricas
                                                                permanecerão
                                                                intactas, mas
                                                                não será
                                                                possível abrir
                                                                novas comandas
                                                                nesta categoria.
                                                            </DialogDescription>
                                                        </DialogHeader>
                                                        <Form
                                                            {...saleCategories.destroy.form(
                                                                category.id,
                                                            )}
                                                            headers={{
                                                                'X-Idempotency-Key':
                                                                    destroyKey,
                                                            }}
                                                            method="delete"
                                                            onSuccess={() =>
                                                                setInactivateOpen(
                                                                    false,
                                                                )
                                                            }
                                                        >
                                                            {({
                                                                processing:
                                                                    inactivating,
                                                            }) => (
                                                                <>
                                                                    <input
                                                                        type="hidden"
                                                                        name="lock_version"
                                                                        value={
                                                                            category.lock_version
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
                                                                                inactivating
                                                                            }
                                                                        >
                                                                            {inactivating
                                                                                ? 'Inativando...'
                                                                                : 'Confirmar inativação'}
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
                                                    onOpenChange={
                                                        setReactivateOpen
                                                    }
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
                                                                Reativar
                                                                categoria de
                                                                comanda?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                A categoria
                                                                voltará a ficar
                                                                ativa para
                                                                abertura de
                                                                novas comandas e
                                                                checkouts.
                                                            </DialogDescription>
                                                        </DialogHeader>
                                                        <Form
                                                            {...saleCategories.reactivate.form(
                                                                category.id,
                                                            )}
                                                            headers={{
                                                                'X-Idempotency-Key':
                                                                    reactivateKey,
                                                            }}
                                                            method="patch"
                                                            onSuccess={() =>
                                                                setReactivateOpen(
                                                                    false,
                                                                )
                                                            }
                                                        >
                                                            {({
                                                                processing:
                                                                    reactivating,
                                                            }) => (
                                                                <>
                                                                    <input
                                                                        type="hidden"
                                                                        name="lock_version"
                                                                        value={
                                                                            category.lock_version
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
                                                                                reactivating
                                                                            }
                                                                            className="bg-emerald-600 text-white hover:bg-emerald-700"
                                                                        >
                                                                            {reactivating
                                                                                ? 'Reativando...'
                                                                                : 'Confirmar reativação'}
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
                                Informações operacionais
                            </h2>
                            <div className="space-y-3 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="inline-flex items-center gap-2 text-muted-foreground">
                                        <Tag className="size-4" />
                                        Chave canônica
                                    </span>
                                    <span className="font-mono text-xs font-semibold text-foreground">
                                        {category.key}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="inline-flex items-center gap-2 text-muted-foreground">
                                        <Sparkles className="size-4" />
                                        Tipo de consumo
                                    </span>
                                    <span className="font-medium text-foreground">
                                        {saleCategoryTypeLabels[
                                            category.type
                                        ] ?? category.type}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="inline-flex items-center gap-2 text-muted-foreground">
                                        <ShieldAlert className="size-4" />
                                        Escopo de unicidade
                                    </span>
                                    <span className="font-medium text-foreground">
                                        {uniquenessScopeLabels[
                                            category.uniqueness_scope
                                        ] ?? category.uniqueness_scope}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="inline-flex items-center gap-2 text-muted-foreground">
                                        <Receipt className="size-4" />
                                        Comandas vinculadas
                                    </span>
                                    <span className="font-semibold text-foreground">
                                        {category.sales_count ?? 0}
                                    </span>
                                </div>
                            </div>
                            <div className="rounded-lg border border-border/50 bg-muted/50 p-3 text-xs text-muted-foreground">
                                <p className="mb-1 font-medium text-foreground">
                                    Regra de Unicidade:
                                </p>
                                <p>
                                    {
                                        uniquenessScopeDescriptions[
                                            category.uniqueness_scope
                                        ]
                                    }
                                </p>
                            </div>
                        </section>
                    </aside>
                </div>
            </PageCanvas>
        </>
    );
}

SaleCategoryShow.layout = {
    breadcrumbs: [
        { title: 'Categorias de Comanda', href: saleCategories.index() },
        { title: 'Detalhes da categoria', href: '#' },
    ],
};
