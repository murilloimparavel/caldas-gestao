import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Coins,
    AlertTriangle,
    Percent,
    Plus,
    Receipt,
    Settings2,
    Trash2,
    UserCheck,
    Users,
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
    ResourceHeader,
} from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type {
    CommissionRule,
    ProfessionalCommissionSummary,
    SharedPageProps,
} from '@/types';

type ServiceOption = {
    id: string;
    name: string;
    price_cents: number;
};

type ProductOption = {
    id: string;
    name: string;
    sale_price_cents: number;
};

type Props = {
    professionals: ProfessionalCommissionSummary[];
    rules: CommissionRule[];
    services: ServiceOption[];
    products: ProductOption[];
    metrics: {
        total_pending_cents: number;
        total_settled_month_cents: number;
        rules_count: number;
    };
};

export default function CommissionsIndex({
    professionals,
    rules,
    services,
    products,
    metrics,
}: Props) {
    const { auth } = usePage<SharedPageProps>().props;
    const canManage = auth.permissions.includes('commission.manage');
    const [isRuleModalOpen, setIsRuleModalOpen] = useState(false);
    const [editingRule, setEditingRule] = useState<CommissionRule | null>(null);
    const [selectedServiceIds, setSelectedServiceIds] = useState<string[]>([]);
    const [selectedProductIds, setSelectedProductIds] = useState<string[]>([]);
    const [selectedProfessionalId, setSelectedProfessionalId] = useState('');
    const [itemTargetType, setItemTargetType] = useState<
        'all' | 'service' | 'product'
    >('all');
    const [ruleRateType, setRuleRateType] = useState<'percentage' | 'fixed'>(
        'percentage',
    );

    const openCreateModal = () => {
        setEditingRule(null);
        setSelectedServiceIds([]);
        setSelectedProductIds([]);
        setSelectedProfessionalId('');
        setItemTargetType('all');
        setRuleRateType('percentage');
        setIsRuleModalOpen(true);
    };

    const openEditModal = (rule: CommissionRule) => {
        setEditingRule(rule);
        setSelectedServiceIds(rule.service_id ? [rule.service_id] : []);
        setSelectedProductIds(rule.product_id ? [rule.product_id] : []);
        setSelectedProfessionalId(rule.professional_id ?? '');

        if (rule.service_id) {
            setItemTargetType('service');
        } else if (rule.product_id) {
            setItemTargetType('product');
        } else {
            setItemTargetType('all');
        }

        setRuleRateType(rule.type);
        setIsRuleModalOpen(true);
    };

    return (
        <PageCanvas
            breadcrumbs={[
                { title: 'Painel', href: '/dashboard' },
                { title: 'Financeiro', href: '/finance/cash' },
                { title: 'Comissões', href: '/finance/commissions' },
            ]}
        >
            <Head title="Comissões dos Profissionais" />

            <div className="space-y-6">
                <ResourceHeader
                    title="Comissões dos Profissionais"
                    description="Regras de repasse, apuração automática por atendimento e liquidação de pagamentos."
                    actions={
                        canManage && (
                            <Button onClick={openCreateModal} className="gap-2">
                                <Plus className="h-4 w-4" />
                                Nova Regra
                            </Button>
                        )
                    }
                />

                {/* Métricas Principais */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="rounded-xl border bg-card p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium text-muted-foreground">
                                Total Pendente (A Pagar)
                            </span>
                            <div className="rounded-lg bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                                <Coins className="h-5 w-5" />
                            </div>
                        </div>
                        <div className="mt-3 text-2xl font-bold tracking-tight text-foreground">
                            {formatMoney(metrics.total_pending_cents)}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Comissões apuradas aguardando liquidação
                        </p>
                    </div>

                    <div className="rounded-xl border bg-card p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium text-muted-foreground">
                                Liquidado este Mês
                            </span>
                            <div className="rounded-lg bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                                <Receipt className="h-5 w-5" />
                            </div>
                        </div>
                        <div className="mt-3 text-2xl font-bold tracking-tight text-foreground">
                            {formatMoney(metrics.total_settled_month_cents)}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Total já pago e baixado no período atual
                        </p>
                    </div>

                    <div className="rounded-xl border bg-card p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium text-muted-foreground">
                                Regras de Comissão
                            </span>
                            <div className="rounded-lg bg-blue-500/10 p-2 text-blue-600 dark:text-blue-400">
                                <Settings2 className="h-5 w-5" />
                            </div>
                        </div>
                        <div className="mt-3 text-2xl font-bold tracking-tight text-foreground">
                            {metrics.rules_count}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Regras ativas configuradas no workspace
                        </p>
                    </div>
                </div>

                {/* Seção 1: Extrato e Apuração por Profissional */}
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-foreground">
                                Apuração por Profissional
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Saldo acumulado e histórico de repasses de cada
                                profissional.
                            </p>
                        </div>
                    </div>

                    {professionals.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="Nenhum profissional cadastrado"
                            description="Cadastre profissionais no sistema para iniciar a apuração de comissões."
                        />
                    ) : (
                        <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-6 py-3.5">
                                                Profissional
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Contato
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Atendimentos Pendentes
                                            </th>
                                            <th className="px-6 py-3.5 text-right">
                                                Comissão Pendente
                                            </th>
                                            <th className="px-6 py-3.5 text-right">
                                                Total Liquidado
                                            </th>
                                            <th className="px-6 py-3.5 text-right">
                                                Ações
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {professionals.map((prof) => (
                                            <tr
                                                key={prof.id}
                                                className="transition-colors hover:bg-muted/30"
                                            >
                                                <td className="px-6 py-4">
                                                    <div className="font-medium text-foreground">
                                                        {prof.name}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-muted-foreground">
                                                    <div>
                                                        {prof.phone || '—'}
                                                    </div>
                                                    {prof.email && (
                                                        <div className="text-xs">
                                                            {prof.email}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4">
                                                    {prof.pending_count > 0 ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1 border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-400"
                                                        >
                                                            {prof.pending_count}{' '}
                                                            {prof.pending_count ===
                                                            1
                                                                ? 'item'
                                                                : 'itens'}
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">
                                                            Em dia
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-right font-semibold">
                                                    <span
                                                        className={
                                                            prof.pending_amount_cents >
                                                            0
                                                                ? 'text-amber-600 dark:text-amber-400'
                                                                : 'text-muted-foreground'
                                                        }
                                                    >
                                                        {formatMoney(
                                                            prof.pending_amount_cents,
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right text-muted-foreground">
                                                    {formatMoney(
                                                        prof.settled_amount_cents,
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                        className="gap-1"
                                                    >
                                                        <Link
                                                            href={`/finance/commissions/professionals/${prof.id}`}
                                                        >
                                                            Extrato
                                                            <ArrowRight className="h-3.5 w-3.5" />
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>

                {/* Seção 2: Regras de Comissão */}
                <div className="space-y-4 pt-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-foreground">
                                Regras de Comissionamento
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Defina regras gerais ou específicas por
                                profissional, serviço e produto.
                            </p>
                        </div>
                    </div>

                    {rules.length === 0 ? (
                        <EmptyState
                            icon={Percent}
                            title="Nenhuma regra cadastrada"
                            description="Crie sua primeira regra de comissão para que os atendimentos sejam apurados automaticamente."
                            action={
                                canManage && (
                                    <Button
                                        onClick={openCreateModal}
                                        className="gap-2"
                                    >
                                        <Plus className="h-4 w-4" />
                                        Criar Regra
                                    </Button>
                                )
                            }
                        />
                    ) : (
                        <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-6 py-3.5">
                                                Profissional
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Escopo (Serviço / Produto)
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Tipo
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Taxa / Valor
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Status
                                            </th>
                                            {canManage && (
                                                <th className="px-6 py-3.5 text-right">
                                                    Ações
                                                </th>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {rules.map((rule) => (
                                            <tr
                                                key={rule.id}
                                                className="transition-colors hover:bg-muted/30"
                                            >
                                                <td className="px-6 py-4 font-medium text-foreground">
                                                    {rule.professional ? (
                                                        <div className="flex items-center gap-1.5">
                                                            <UserCheck className="h-4 w-4 text-primary" />
                                                            <span>
                                                                {
                                                                    rule
                                                                        .professional
                                                                        .name
                                                                }
                                                            </span>
                                                        </div>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className="text-xs"
                                                        >
                                                            Todos os
                                                            Profissionais
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-muted-foreground">
                                                    {rule.service ? (
                                                        <Badge
                                                            variant="secondary"
                                                            className="text-xs"
                                                        >
                                                            Serviço:{' '}
                                                            {rule.service.name}
                                                        </Badge>
                                                    ) : rule.product ? (
                                                        <Badge
                                                            variant="secondary"
                                                            className="text-xs"
                                                        >
                                                            Produto:{' '}
                                                            {rule.product.name}
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">
                                                            Geral (Todos os
                                                            Itens)
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4">
                                                    {rule.type ===
                                                    'percentage' ? (
                                                        <Badge className="border-blue-200 bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                                                            Percentual
                                                        </Badge>
                                                    ) : (
                                                        <Badge className="border-emerald-200 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                            Valor Fixo
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 font-semibold text-foreground">
                                                    {rule.type === 'percentage'
                                                        ? `${rule.value_rate}%`
                                                        : formatMoney(
                                                              rule.value_rate,
                                                          )}
                                                </td>
                                                <td className="px-6 py-4">
                                                    {rule.is_active ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-emerald-300 text-emerald-600 dark:border-emerald-800 dark:text-emerald-400"
                                                        >
                                                            Ativa
                                                        </Badge>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className="text-muted-foreground"
                                                        >
                                                            Inativa
                                                        </Badge>
                                                    )}
                                                </td>
                                                {canManage && (
                                                    <td className="px-6 py-4 text-right">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() =>
                                                                    openEditModal(
                                                                        rule,
                                                                    )
                                                                }
                                                            >
                                                                Editar
                                                            </Button>
                                                            <Form
                                                                action={`/finance/commissions/rules/${rule.id}`}
                                                                method="delete"
                                                                onSubmit={(
                                                                    e,
                                                                ) => {
                                                                    if (
                                                                        !confirm(
                                                                            'Deseja realmente excluir esta regra de comissão?',
                                                                        )
                                                                    ) {
                                                                        e.preventDefault();
                                                                    }
                                                                }}
                                                            >
                                                                <Button
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    className="h-8 w-8 text-destructive hover:bg-destructive/10"
                                                                    type="submit"
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            </Form>
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal de Criação / Edição de Regra */}
            <Dialog open={isRuleModalOpen} onOpenChange={setIsRuleModalOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editingRule
                                ? 'Editar Regra de Comissão'
                                : 'Nova Regra de Comissão'}
                        </DialogTitle>
                        <DialogDescription>
                            Configure a porcentagem ou o valor fixo a ser
                            repassado ao profissional.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        action={
                            editingRule
                                ? `/finance/commissions/rules/${editingRule.id}`
                                : '/finance/commissions/rules'
                        }
                        method={editingRule ? 'put' : 'post'}
                        headers={{
                            'X-Idempotency-Key':
                                createIdempotencyKey('save-rule'),
                        }}
                        onSuccess={() => setIsRuleModalOpen(false)}
                        className="space-y-4 pt-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <FormErrorSummary errors={errors} />

                                {editingRule && (
                                    <input
                                        type="hidden"
                                        name="lock_version"
                                        value={editingRule.lock_version}
                                    />
                                )}

                                <FormField
                                    label="Profissional"
                                    error={errors.professional_id}
                                >
                                    <select
                                        name="professional_id"
                                        value={selectedProfessionalId}
                                        onChange={(event) =>
                                            setSelectedProfessionalId(
                                                event.target.value,
                                            )
                                        }
                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <option value="">
                                            Todos os Profissionais (Regra Geral)
                                        </option>
                                        {professionals.map((prof) => (
                                            <option
                                                key={prof.id}
                                                value={prof.id}
                                            >
                                                {prof.name}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>

                                <div className="space-y-2">
                                    <span className="text-sm font-medium">
                                        Aplicar a:
                                    </span>
                                    <div className="grid grid-cols-3 gap-2">
                                        <Button
                                            type="button"
                                            variant={
                                                itemTargetType === 'all'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            onClick={() =>
                                                setItemTargetType('all')
                                            }
                                        >
                                            Todos os itens
                                        </Button>
                                        <Button
                                            type="button"
                                            variant={
                                                itemTargetType === 'service'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            onClick={() =>
                                                setItemTargetType('service')
                                            }
                                        >
                                            Serviço
                                        </Button>
                                        <Button
                                            type="button"
                                            variant={
                                                itemTargetType === 'product'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            onClick={() =>
                                                setItemTargetType('product')
                                            }
                                        >
                                            Produto
                                        </Button>
                                    </div>
                                    {(() => {
                                        const conflict = rules.find(
                                            (rule) =>
                                                rule.id !== editingRule?.id &&
                                                rule.professional_id ===
                                                    (selectedProfessionalId ||
                                                        null) &&
                                                rule.service_id === null &&
                                                rule.product_id === null,
                                        );

                                        return itemTargetType === 'all' &&
                                            conflict ? (
                                            <p className="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                                Já existe uma regra geral de
                                                comissão para este profissional.
                                                Remova a regra existente antes
                                                de salvar.
                                            </p>
                                        ) : null;
                                    })()}
                                </div>

                                {itemTargetType === 'service' && (
                                    <FormField
                                        label="Serviço Específico"
                                        error={
                                            errors.service_ids ??
                                            errors.service_id
                                        }
                                    >
                                        {services.length === 0 ? (
                                            <p className="rounded-lg border border-dashed border-border bg-muted/40 px-3 py-2 text-xs leading-5 text-muted-foreground">
                                                Nenhum serviço ativo disponível
                                                nesta unidade ainda.
                                            </p>
                                        ) : (
                                            <>
                                                <div className="max-h-52 space-y-1 overflow-y-auto rounded-md border border-input bg-background p-2">
                                                    {!editingRule && (
                                                        <label className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm font-medium hover:bg-muted">
                                                            <input
                                                                type="checkbox"
                                                                checked={
                                                                    selectedServiceIds.length ===
                                                                    services.length
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setSelectedServiceIds(
                                                                        event
                                                                            .target
                                                                            .checked
                                                                            ? services.map(
                                                                                  (
                                                                                      service,
                                                                                  ) =>
                                                                                      service.id,
                                                                              )
                                                                            : [],
                                                                    )
                                                                }
                                                                className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                                            />
                                                            Todos os serviços
                                                        </label>
                                                    )}
                                                    {services.map((service) => (
                                                        <label
                                                            key={service.id}
                                                            className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-muted"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                name={
                                                                    editingRule
                                                                        ? undefined
                                                                        : 'service_ids[]'
                                                                }
                                                                value={
                                                                    service.id
                                                                }
                                                                checked={selectedServiceIds.includes(
                                                                    service.id,
                                                                )}
                                                                onChange={() => {
                                                                    if (
                                                                        editingRule
                                                                    ) {
                                                                        return;
                                                                    }

                                                                    setSelectedServiceIds(
                                                                        selectedServiceIds.includes(
                                                                            service.id,
                                                                        )
                                                                            ? selectedServiceIds.filter(
                                                                                  (
                                                                                      id,
                                                                                  ) =>
                                                                                      id !==
                                                                                      service.id,
                                                                              )
                                                                            : [
                                                                                  ...selectedServiceIds,
                                                                                  service.id,
                                                                              ],
                                                                    );
                                                                }}
                                                                className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                                            />
                                                            <span>
                                                                {service.name} (
                                                                {formatMoney(
                                                                    service.price_cents,
                                                                )}
                                                                )
                                                            </span>
                                                        </label>
                                                    ))}
                                                </div>
                                                {editingRule && (
                                                    <input
                                                        type="hidden"
                                                        name="service_id"
                                                        value={
                                                            selectedServiceIds[0] ??
                                                            ''
                                                        }
                                                    />
                                                )}
                                                {!editingRule &&
                                                    selectedServiceIds.length ===
                                                        0 && (
                                                        <p className="text-xs text-destructive">
                                                            Selecione pelo menos
                                                            um serviço.
                                                        </p>
                                                    )}
                                                {(() => {
                                                    const conflicts =
                                                        rules.filter(
                                                            (rule) =>
                                                                rule.id !==
                                                                    editingRule?.id &&
                                                                rule.professional_id ===
                                                                    (selectedProfessionalId ||
                                                                        null) &&
                                                                rule.service_id !==
                                                                    null &&
                                                                selectedServiceIds.includes(
                                                                    rule.service_id,
                                                                ),
                                                        );

                                                    return conflicts.length >
                                                        0 ? (
                                                        <p className="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                                                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                                            Já existe regra
                                                            para:{' '}
                                                            {conflicts
                                                                .map(
                                                                    (rule) =>
                                                                        rule
                                                                            .service
                                                                            ?.name,
                                                                )
                                                                .filter(Boolean)
                                                                .join(', ')}
                                                            . Remova os itens
                                                            repetidos antes de
                                                            salvar.
                                                        </p>
                                                    ) : null;
                                                })()}
                                            </>
                                        )}
                                    </FormField>
                                )}

                                {itemTargetType === 'product' && (
                                    <FormField
                                        label="Produto Específico"
                                        error={
                                            errors.product_ids ??
                                            errors.product_id
                                        }
                                    >
                                        <div className="max-h-52 space-y-1 overflow-y-auto rounded-md border border-input bg-background p-2">
                                            {!editingRule && (
                                                <label className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm font-medium hover:bg-muted">
                                                    <input
                                                        type="checkbox"
                                                        checked={
                                                            selectedProductIds.length ===
                                                            products.length
                                                        }
                                                        onChange={(event) =>
                                                            setSelectedProductIds(
                                                                event.target
                                                                    .checked
                                                                    ? products.map(
                                                                          (
                                                                              product,
                                                                          ) =>
                                                                              product.id,
                                                                      )
                                                                    : [],
                                                            )
                                                        }
                                                        className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                                    />
                                                    Todos os produtos
                                                </label>
                                            )}
                                            {products.map((product) => (
                                                <label
                                                    key={product.id}
                                                    className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-muted"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        name={
                                                            editingRule
                                                                ? undefined
                                                                : 'product_ids[]'
                                                        }
                                                        value={product.id}
                                                        checked={selectedProductIds.includes(
                                                            product.id,
                                                        )}
                                                        onChange={() => {
                                                            if (editingRule) {
                                                                return;
                                                            }

                                                            setSelectedProductIds(
                                                                selectedProductIds.includes(
                                                                    product.id,
                                                                )
                                                                    ? selectedProductIds.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              product.id,
                                                                      )
                                                                    : [
                                                                          ...selectedProductIds,
                                                                          product.id,
                                                                      ],
                                                            );
                                                        }}
                                                        className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                                    />
                                                    <span>
                                                        {product.name} (
                                                        {formatMoney(
                                                            product.sale_price_cents,
                                                        )}
                                                        )
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                        {editingRule && (
                                            <input
                                                type="hidden"
                                                name="product_id"
                                                value={
                                                    selectedProductIds[0] ?? ''
                                                }
                                            />
                                        )}
                                        {!editingRule &&
                                            selectedProductIds.length === 0 && (
                                                <p className="text-xs text-destructive">
                                                    Selecione pelo menos um
                                                    produto.
                                                </p>
                                            )}
                                        {(() => {
                                            const conflicts = rules.filter(
                                                (rule) =>
                                                    rule.id !==
                                                        editingRule?.id &&
                                                    rule.professional_id ===
                                                        (selectedProfessionalId ||
                                                            null) &&
                                                    rule.product_id !== null &&
                                                    selectedProductIds.includes(
                                                        rule.product_id,
                                                    ),
                                            );

                                            return conflicts.length > 0 ? (
                                                <p className="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                                                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                                    Já existe regra para:{' '}
                                                    {conflicts
                                                        .map(
                                                            (rule) =>
                                                                rule.product
                                                                    ?.name,
                                                        )
                                                        .filter(Boolean)
                                                        .join(', ')}
                                                    . Remova os produtos
                                                    repetidos antes de salvar.
                                                </p>
                                            ) : null;
                                        })()}
                                    </FormField>
                                )}

                                <div className="grid grid-cols-2 gap-4">
                                    <FormField
                                        label="Tipo de Comissão"
                                        error={errors.type}
                                    >
                                        <select
                                            name="type"
                                            value={ruleRateType}
                                            onChange={(e) =>
                                                setRuleRateType(
                                                    e.target.value as
                                                        'percentage' | 'fixed',
                                                )
                                            }
                                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <option value="percentage">
                                                Percentual (%)
                                            </option>
                                            <option value="fixed">
                                                Valor Fixo (Centavos)
                                            </option>
                                        </select>
                                    </FormField>

                                    <FormField
                                        label={
                                            ruleRateType === 'percentage'
                                                ? 'Percentual (%)'
                                                : 'Valor Fixo (em centavos)'
                                        }
                                        description={
                                            ruleRateType === 'fixed'
                                                ? 'Ex: 1500 para R$ 15,00'
                                                : 'Ex: 20 para 20%'
                                        }
                                        error={errors.value_rate}
                                    >
                                        <Input
                                            type="number"
                                            name="value_rate"
                                            min="0"
                                            max={
                                                ruleRateType === 'percentage'
                                                    ? 100
                                                    : undefined
                                            }
                                            defaultValue={
                                                editingRule?.value_rate ?? 10
                                            }
                                            required
                                        />
                                    </FormField>
                                </div>

                                <div className="flex items-center gap-2 pt-2">
                                    <input
                                        type="checkbox"
                                        id="is_active"
                                        name="is_active"
                                        value="1"
                                        defaultChecked={
                                            editingRule
                                                ? editingRule.is_active
                                                : true
                                        }
                                        className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                    />
                                    <label
                                        htmlFor="is_active"
                                        className="text-sm font-medium"
                                    >
                                        Regra ativa
                                    </label>
                                </div>

                                <FormActions
                                    onCancel={() => setIsRuleModalOpen(false)}
                                    submitLabel={
                                        editingRule
                                            ? 'Atualizar Regra'
                                            : 'Salvar Regra'
                                    }
                                    isSubmitting={processing}
                                />
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </PageCanvas>
    );
}
