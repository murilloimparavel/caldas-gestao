import { Form, Head, Link } from '@inertiajs/react';
import { Check, Pencil, Plus, Power, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { AdminPageHeader } from '@/features/admin/components/admin-page-header';
import {
    adminRoutes,
    formatAdminMoney,
    type AdminPlan,
} from '@/features/admin/types';
import {
    EmptyState,
    FormActions,
    FormErrorSummary,
    FormField,
    PageCanvas,
    parseBrazilianCurrency,
} from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    plans?: AdminPlan[] | { data?: AdminPlan[] };
    filters?: { search?: string; status?: string };
};

const cycleLabels: Record<string, string> = {
    monthly: 'mês',
    quarterly: 'trimestre',
    yearly: 'ano',
};
const planPrice = (plan: AdminPlan): number =>
    plan.priceCents ?? plan.price_cents ?? 0;
const planCycle = (plan: AdminPlan): string =>
    plan.billingCycle ?? plan.billing_cycle ?? 'monthly';
const planActive = (plan: AdminPlan): boolean =>
    plan.isActive ?? plan.is_active ?? false;

function PlanForm({ plan, onDone }: { plan?: AdminPlan; onDone?: () => void }) {
    const [cycle, setCycle] = useState(plan ? planCycle(plan) : 'monthly');
    const [price, setPrice] = useState(
        plan ? (planPrice(plan) / 100).toFixed(2).replace('.', ',') : '',
    );
    const action = plan ? adminRoutes.plan(plan.id) : adminRoutes.plans;

    return (
        <Form
            method={plan ? 'put' : 'post'}
            action={action}
            onSuccess={onDone}
            className="space-y-5"
        >
            {({ errors, processing }) => (
                <>
                    <FormErrorSummary errors={errors} />
                    <FormField
                        name="key"
                        label="Chave técnica"
                        required
                        error={errors.key}
                        description="Use letras, números, hífen ou sublinhado."
                    >
                        <Input
                            name="key"
                            defaultValue={plan?.key}
                            placeholder="essencial"
                            required
                        />
                    </FormField>
                    <FormField
                        name="name"
                        label="Nome"
                        required
                        error={errors.name}
                    >
                        <Input
                            name="name"
                            defaultValue={plan?.name}
                            placeholder="Plano Essencial"
                            required
                        />
                    </FormField>
                    <FormField
                        name="description"
                        label="Descrição"
                        error={errors.description}
                    >
                        <Textarea
                            name="description"
                            defaultValue={plan?.description ?? ''}
                            placeholder="O que está incluído neste plano?"
                            rows={3}
                        />
                    </FormField>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            name="price_cents"
                            label="Preço por ciclo"
                            required
                            error={errors.price_cents}
                        >
                            <Input
                                inputMode="decimal"
                                value={price}
                                onChange={(event) =>
                                    setPrice(event.target.value)
                                }
                                placeholder="0,00"
                                required
                            />
                            <input
                                type="hidden"
                                name="price_cents"
                                value={parseBrazilianCurrency(price)}
                            />
                        </FormField>
                        <FormField
                            name="billing_cycle"
                            label="Ciclo"
                            required
                            error={errors.billing_cycle}
                        >
                            <select
                                name="billing_cycle"
                                value={cycle}
                                onChange={(event) =>
                                    setCycle(event.target.value)
                                }
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="monthly">Mensal</option>
                                <option value="quarterly">Trimestral</option>
                                <option value="yearly">Anual</option>
                            </select>
                        </FormField>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            name="trial_days"
                            label="Dias de teste"
                            error={errors.trial_days}
                        >
                            <Input
                                name="trial_days"
                                type="number"
                                min={0}
                                defaultValue={
                                    plan?.trialDays ?? plan?.trial_days ?? 0
                                }
                            />
                        </FormField>
                        <FormField
                            name="limits[users]"
                            label="Limite de usuários"
                            error={errors['limits.users']}
                        >
                            <Input
                                name="limits[users]"
                                type="number"
                                min={0}
                                defaultValue={plan?.limits?.users ?? ''}
                            />
                        </FormField>
                    </div>
                    <FormField
                        name="features[]"
                        label="Recursos incluídos"
                        description="Informe um recurso por campo; campos vazios são ignorados."
                    >
                        <Input
                            name="features[]"
                            defaultValue={plan?.features?.[0] ?? ''}
                            placeholder="Agenda e clientes"
                        />
                        <Input
                            name="features[]"
                            defaultValue={plan?.features?.[1] ?? ''}
                            placeholder="Relatórios"
                        />
                    </FormField>
                    <FormActions
                        processing={processing}
                        label={plan ? 'Salvar plano' : 'Criar plano'}
                    />
                </>
            )}
        </Form>
    );
}

export default function PlatformPlans({
    plans: inputPlans = [],
    filters = {},
}: Props) {
    const plans = Array.isArray(inputPlans)
        ? inputPlans
        : (inputPlans.data ?? []);
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<AdminPlan | null>(null);

    return (
        <>
            <Head title="Planos da plataforma" />
            <PageCanvas className="gap-8">
                <AdminPageHeader
                    title="Planos da plataforma"
                    description="Defina preços, ciclos, limites e recursos que controlam o acesso de cada cliente."
                />
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <form
                        action={adminRoutes.plans}
                        method="get"
                        className="flex w-full max-w-xl gap-2"
                    >
                        <Input
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Buscar plano"
                            aria-label="Buscar plano"
                        />
                        <Button type="submit">
                            <SlidersHorizontal className="size-4" />
                            Buscar
                        </Button>
                    </form>
                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus className="size-4" />
                                Novo plano
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Criar plano</DialogTitle>
                                <DialogDescription>
                                    O plano ficará disponível para novas
                                    assinaturas após o cadastro.
                                </DialogDescription>
                            </DialogHeader>
                            <PlanForm onDone={() => setCreateOpen(false)} />
                        </DialogContent>
                    </Dialog>
                </div>
                {plans.length === 0 ? (
                    <EmptyState
                        title="Nenhum plano cadastrado"
                        description="Crie o primeiro plano para começar a oferecer assinaturas na plataforma."
                        action={
                            <Button onClick={() => setCreateOpen(true)}>
                                <Plus className="size-4" />
                                Criar plano
                            </Button>
                        }
                    />
                ) : (
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {plans.map((plan) => (
                            <Card
                                key={plan.id}
                                className={
                                    !planActive(plan) ? 'opacity-70' : undefined
                                }
                            >
                                <CardHeader className="flex flex-row items-start justify-between gap-3">
                                    <div>
                                        <CardTitle>{plan.name}</CardTitle>
                                        {plan.description ? (
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {plan.description}
                                            </p>
                                        ) : null}
                                    </div>
                                    <Badge
                                        variant={
                                            planActive(plan)
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {planActive(plan)
                                            ? 'Ativo'
                                            : 'Desativado'}
                                    </Badge>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <p className="text-2xl font-semibold">
                                        {formatAdminMoney(planPrice(plan))}{' '}
                                        <span className="text-sm font-normal text-muted-foreground">
                                            /{' '}
                                            {cycleLabels[planCycle(plan)] ??
                                                planCycle(plan)}
                                        </span>
                                    </p>
                                    {plan.features?.length ? (
                                        <ul className="space-y-2 text-sm text-muted-foreground">
                                            {plan.features.map((feature) => (
                                                <li
                                                    key={feature}
                                                    className="flex gap-2"
                                                >
                                                    <Check className="mt-0.5 size-4 text-emerald-600" />
                                                    {feature}
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Nenhum recurso informado.
                                        </p>
                                    )}
                                    <div className="flex gap-2 border-t pt-4">
                                        <Dialog
                                            open={editing?.id === plan.id}
                                            onOpenChange={(open) =>
                                                setEditing(open ? plan : null)
                                            }
                                        >
                                            <DialogTrigger asChild>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Pencil className="size-4" />
                                                    Editar
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <DialogHeader>
                                                    <DialogTitle>
                                                        Editar {plan.name}
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        Atualize as regras para
                                                        novas cobranças e
                                                        acessos.
                                                    </DialogDescription>
                                                </DialogHeader>
                                                <PlanForm
                                                    plan={plan}
                                                    onDone={() =>
                                                        setEditing(null)
                                                    }
                                                />
                                            </DialogContent>
                                        </Dialog>
                                        {planActive(plan) ? (
                                            <Form
                                                method="post"
                                                action={adminRoutes.planDeactivate(
                                                    plan.id,
                                                )}
                                            >
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    type="submit"
                                                >
                                                    <Power className="size-4" />
                                                    Desativar
                                                </Button>
                                            </Form>
                                        ) : null}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </PageCanvas>
        </>
    );
}

PlatformPlans.layout = {
    breadcrumbs: [
        { title: 'Administração', href: adminRoutes.dashboard },
        { title: 'Planos', href: adminRoutes.plans },
    ],
};
