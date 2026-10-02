import { Head } from '@inertiajs/react';
import { PageCanvas } from '@/components/operational';
import { Button } from '@/components/ui/button';
import billing from '@/routes/billing';

type Props = {
    checkoutUrl: string | null;
    onlineBookingCount: number;
    subscription: {
        status: string;
        starts_at: string | null;
        ends_at: string | null;
        grace_ends_at: string | null;
        plan: { name: string | null; price_cents: number | null };
    };
};

const labels: Record<string, string> = {
    trial: 'Período de teste',
    active: 'Ativa',
    grace: 'Em carência',
    suspended: 'Suspensa',
    expired: 'Expirada',
    cancelled: 'Cancelada',
};

export default function BillingIndex({
    checkoutUrl,
    onlineBookingCount,
    subscription,
}: Props) {
    const accessBlocked = !['trial', 'active', 'grace'].includes(
        subscription.status,
    );
    const endsAt = subscription.ends_at ?? subscription.grace_ends_at;
    const onlineBookingLabel =
        onlineBookingCount === 1
            ? 'agendamento online ativo'
            : 'agendamentos online ativos';

    return (
        <PageCanvas>
            <Head title="Assinatura" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6">
                <div>
                    <p className="text-sm text-muted-foreground">
                        Caldas Gestão
                    </p>
                    <h1 className="text-3xl font-semibold">Sua assinatura</h1>
                </div>
                <section className="rounded-xl border bg-card p-6 shadow-sm">
                    <p className="text-sm text-muted-foreground">Plano atual</p>
                    <h2 className="mt-1 text-xl font-semibold">
                        {subscription.plan.name ?? 'Gratuito'}
                    </h2>
                    <p className="mt-3">
                        Situação:{' '}
                        <strong>
                            {labels[subscription.status] ?? subscription.status}
                        </strong>
                    </p>
                    {endsAt && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            Válido até{' '}
                            {new Date(endsAt).toLocaleDateString('pt-BR')}
                        </p>
                    )}
                    {accessBlocked && (
                        <p className="mt-4 rounded-md bg-destructive/10 p-3 text-sm">
                            O acesso operacional está suspenso. Renove sua
                            assinatura para continuar usando o sistema.
                        </p>
                    )}
                    {accessBlocked && onlineBookingCount > 0 && (
                        <p
                            className="mt-3 rounded-md border border-border bg-muted/40 p-3 text-sm text-muted-foreground"
                            role="status"
                        >
                            Há {onlineBookingCount} {onlineBookingLabel} nesta
                            conta. O acesso aos detalhes está indisponível
                            enquanto a assinatura não for regularizada.
                        </p>
                    )}
                    {checkoutUrl && (
                        <Button asChild className="mt-6">
                            <a
                                href={checkoutUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Assinar / renovar
                            </a>
                        </Button>
                    )}
                </section>
            </div>
        </PageCanvas>
    );
}

BillingIndex.layout = {
    breadcrumbs: [{ title: 'Assinatura', href: billing.index() }],
};
