import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Coins,
    Percent,
    Plus,
    Receipt,
    Settings2,
    Trash2,
    UserCheck,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { CommissionRuleBuilder } from '@/components/commissions/commission-rule-builder';
import type {
    CommissionCategoryOption,
    CommissionProductOption,
    CommissionServiceOption,
} from '@/components/commissions/commission-rule-types';
import {
    EmptyState,
    PageCanvas,
    ResourceHeader,
    formatMoney,
} from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type {
    CommissionRule,
    ProfessionalCommissionSummary,
    SharedPageProps,
} from '@/types';

type Props = {
    professionals: ProfessionalCommissionSummary[];
    rules: CommissionRule[];
    services: CommissionServiceOption[];
    products: CommissionProductOption[];
    categories?: CommissionCategoryOption[];
    service_categories?: CommissionCategoryOption[];
    product_categories?: CommissionCategoryOption[];
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
    categories = [],
    service_categories = [],
    product_categories = [],
    metrics,
}: Props) {
    const { auth } = usePage<SharedPageProps>().props;
    const canManage = auth.permissions.includes('commission.manage');
    const [editingRule, setEditingRule] = useState<CommissionRule | null>(null);
    const [isBuilderOpen, setIsBuilderOpen] = useState(false);
    const serviceCategories = categories.filter(
        (category) =>
            category.type === 'service' || category.type === 'general',
    );
    const productCategories = categories.filter(
        (category) =>
            category.type === 'product' || category.type === 'general',
    );

    const openCreate = () => {
        setEditingRule(null);
        setIsBuilderOpen(true);
    };
    const openEdit = (rule: CommissionRule) => {
        setEditingRule(rule);
        setIsBuilderOpen(true);
    };

    return (
        <PageCanvas
            breadcrumbs={[
                { title: 'Painel', href: '/dashboard' },
                { title: 'Financeiro', href: '/finance/cash' },
                { title: 'Comissões', href: '/finance/commissions' },
            ]}
        >
            <Head
                title={
                    isBuilderOpen
                        ? editingRule
                            ? 'Editar regra de comissão'
                            : 'Nova regra de comissão'
                        : 'Comissões dos Profissionais'
                }
            />
            {isBuilderOpen ? (
                <CommissionRuleBuilder
                    professionals={professionals}
                    rules={rules}
                    services={services}
                    products={products}
                    categories={categories}
                    serviceCategories={
                        serviceCategories.length > 0
                            ? serviceCategories
                            : service_categories
                    }
                    productCategories={
                        productCategories.length > 0
                            ? productCategories
                            : product_categories
                    }
                    editingRule={editingRule}
                    onCancel={() => setIsBuilderOpen(false)}
                />
            ) : (
                <div className="space-y-7">
                    <ResourceHeader
                        title="Comissões dos profissionais"
                        description="Controle as regras de repasse e acompanhe o que já foi apurado."
                        actions={
                            canManage && (
                                <Button onClick={openCreate} className="gap-2">
                                    <Plus className="h-4 w-4" />
                                    Nova regra
                                </Button>
                            )
                        }
                    />
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Metric
                            icon={Coins}
                            tone="amber"
                            label="Total pendente"
                            value={formatMoney(metrics.total_pending_cents)}
                            caption="Aguardando liquidação"
                        />
                        <Metric
                            icon={Receipt}
                            tone="emerald"
                            label="Liquidado este mês"
                            value={formatMoney(
                                metrics.total_settled_month_cents,
                            )}
                            caption="Total pago no período"
                        />
                        <Metric
                            icon={Settings2}
                            tone="blue"
                            label="Regras de comissão"
                            value={String(metrics.rules_count)}
                            caption="Regras ativas no workspace"
                        />
                    </div>
                    <section className="space-y-4">
                        <div>
                            <h2 className="text-lg font-semibold text-foreground">
                                Apuração por profissional
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Saldo acumulado e histórico de repasses.
                            </p>
                        </div>
                        {professionals.length === 0 ? (
                            <EmptyState
                                icon={Users}
                                title="Nenhum profissional cadastrado"
                                description="Cadastre profissionais para iniciar a apuração."
                            />
                        ) : (
                            <div className="overflow-hidden rounded-2xl border bg-card shadow-sm">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-b bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-5 py-3.5">
                                                    Profissional
                                                </th>
                                                <th className="px-5 py-3.5">
                                                    Contato
                                                </th>
                                                <th className="px-5 py-3.5">
                                                    Pendências
                                                </th>
                                                <th className="px-5 py-3.5 text-right">
                                                    Comissão pendente
                                                </th>
                                                <th className="px-5 py-3.5 text-right">
                                                    Total liquidado
                                                </th>
                                                <th className="px-5 py-3.5 text-right">
                                                    Ações
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {professionals.map(
                                                (professional) => (
                                                    <tr
                                                        key={professional.id}
                                                        className="transition-colors hover:bg-muted/30"
                                                    >
                                                        <td className="px-5 py-4 font-medium text-foreground">
                                                            {professional.name}
                                                        </td>
                                                        <td className="px-5 py-4 text-muted-foreground">
                                                            {professional.phone ||
                                                                professional.email ||
                                                                '—'}
                                                        </td>
                                                        <td className="px-5 py-4">
                                                            {professional.pending_count >
                                                            0 ? (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="border-amber-300 text-amber-700 dark:border-amber-800 dark:text-amber-400"
                                                                >
                                                                    {
                                                                        professional.pending_count
                                                                    }{' '}
                                                                    {professional.pending_count ===
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
                                                        <td className="px-5 py-4 text-right font-semibold text-amber-600 dark:text-amber-400">
                                                            {formatMoney(
                                                                professional.pending_amount_cents,
                                                            )}
                                                        </td>
                                                        <td className="px-5 py-4 text-right text-muted-foreground">
                                                            {formatMoney(
                                                                professional.settled_amount_cents,
                                                            )}
                                                        </td>
                                                        <td className="px-5 py-4 text-right">
                                                            <Button
                                                                asChild
                                                                size="sm"
                                                                variant="outline"
                                                                className="gap-1"
                                                            >
                                                                <Link
                                                                    href={`/finance/commissions/professionals/${professional.id}`}
                                                                >
                                                                    Extrato
                                                                    <ArrowRight className="h-3.5 w-3.5" />
                                                                </Link>
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </section>
                    <section className="space-y-4 pt-2">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold text-foreground">
                                    Regras de comissionamento
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Regras gerais, por categoria ou por item
                                    específico.
                                </p>
                            </div>
                            {rules.length > 0 && canManage && (
                                <Button
                                    variant="outline"
                                    onClick={openCreate}
                                    className="gap-2"
                                >
                                    <Plus className="h-4 w-4" />
                                    Adicionar regra
                                </Button>
                            )}
                        </div>
                        {rules.length === 0 ? (
                            <EmptyState
                                icon={Percent}
                                title="Nenhuma regra cadastrada"
                                description="Crie sua primeira regra para automatizar os repasses."
                                action={
                                    canManage && (
                                        <Button
                                            onClick={openCreate}
                                            className="gap-2"
                                        >
                                            <Plus className="h-4 w-4" />
                                            Criar regra
                                        </Button>
                                    )
                                }
                            />
                        ) : (
                            <div className="overflow-hidden rounded-2xl border bg-card shadow-sm">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-b bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-5 py-3.5">
                                                    Profissional
                                                </th>
                                                <th className="px-5 py-3.5">
                                                    Abrangência
                                                </th>
                                                <th className="px-5 py-3.5">
                                                    Tipo
                                                </th>
                                                <th className="px-5 py-3.5">
                                                    Taxa / valor
                                                </th>
                                                <th className="px-5 py-3.5">
                                                    Status
                                                </th>
                                                {canManage && (
                                                    <th className="px-5 py-3.5 text-right">
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
                                                    <td className="px-5 py-4 font-medium text-foreground">
                                                        {rule.professional ? (
                                                            <span className="flex items-center gap-1.5">
                                                                <UserCheck className="h-4 w-4 text-primary" />
                                                                {
                                                                    rule
                                                                        .professional
                                                                        .name
                                                                }
                                                            </span>
                                                        ) : (
                                                            <Badge variant="outline">
                                                                Todos os
                                                                profissionais
                                                            </Badge>
                                                        )}
                                                    </td>
                                                    <td className="px-5 py-4 text-muted-foreground">
                                                        {rule.service ? (
                                                            <Badge variant="secondary">
                                                                Serviço:{' '}
                                                                {
                                                                    rule.service
                                                                        .name
                                                                }
                                                            </Badge>
                                                        ) : rule.product ? (
                                                            <Badge variant="secondary">
                                                                Produto:{' '}
                                                                {
                                                                    rule.product
                                                                        .name
                                                                }
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-xs">
                                                                Todos os itens ·
                                                                inclui novos
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        {rule.type ===
                                                        'percentage' ? (
                                                            <Badge className="border-blue-200 bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                                                                Percentual
                                                            </Badge>
                                                        ) : (
                                                            <Badge className="border-emerald-200 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                                Valor fixo
                                                            </Badge>
                                                        )}
                                                    </td>
                                                    <td className="px-5 py-4 font-semibold text-foreground">
                                                        {rule.type ===
                                                        'percentage'
                                                            ? `${rule.value_rate}%`
                                                            : formatMoney(
                                                                  rule.value_rate,
                                                              )}
                                                    </td>
                                                    <td className="px-5 py-4">
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
                                                        <td className="px-5 py-4 text-right">
                                                            <div className="flex justify-end gap-1">
                                                                <Button
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    onClick={() =>
                                                                        openEdit(
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
                                                                        event,
                                                                    ) => {
                                                                        if (
                                                                            !confirm(
                                                                                'Deseja realmente excluir esta regra de comissão?',
                                                                            )
                                                                        ) {
                                                                            event.preventDefault();
                                                                        }
                                                                    }}
                                                                >
                                                                    <Button
                                                                        size="icon"
                                                                        variant="ghost"
                                                                        type="submit"
                                                                        className="h-8 w-8 text-destructive hover:bg-destructive/10"
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
                    </section>
                </div>
            )}
        </PageCanvas>
    );
}

function Metric({
    icon: Icon,
    tone,
    label,
    value,
    caption,
}: {
    icon: typeof Coins;
    tone: 'amber' | 'emerald' | 'blue';
    label: string;
    value: string;
    caption: string;
}) {
    const colors = {
        amber: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        emerald: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        blue: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    };

    return (
        <div className="rounded-2xl border bg-card p-5 shadow-sm">
            <div className="flex items-center justify-between gap-3">
                <span className="text-sm font-medium text-muted-foreground">
                    {label}
                </span>
                <div className={`rounded-lg p-2 ${colors[tone]}`}>
                    <Icon className="h-5 w-5" />
                </div>
            </div>
            <div className="mt-3 text-2xl font-bold tracking-tight text-foreground">
                {value}
            </div>
            <p className="mt-1 text-xs text-muted-foreground">{caption}</p>
        </div>
    );
}
