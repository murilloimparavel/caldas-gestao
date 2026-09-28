import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    CircleDollarSign,
    Lightbulb,
    UsersRound,
} from 'lucide-react';
import { AdminMetricCard } from '@/features/admin/components/admin-metric-card';
import { AdminPageHeader } from '@/features/admin/components/admin-page-header';
import {
    adminRoutes,
    type AdminAlert,
    type AdminMetric,
} from '@/features/admin/types';
import { PageCanvas } from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    metrics?: AdminMetric[];
    alerts?: AdminAlert[];
    health?: Array<{ label: string; value: string; detail: string }>;
};

export default function PlatformDashboard({
    metrics = [],
    alerts = [],
    health = [],
}: Props) {
    return (
        <>
            <Head title="Painel administrativo" />
            <PageCanvas className="gap-8">
                <AdminPageHeader
                    title="Painel administrativo"
                    description="Uma visão inteligente da saúde da sua base, receita e próximos atendimentos."
                    actionHref={adminRoutes.createClient}
                    actionLabel="Novo cliente"
                />
                <section
                    className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    aria-label="Indicadores da plataforma"
                >
                    {metrics.map((metric, index) => (
                        <AdminMetricCard
                            key={metric.label}
                            metric={metric}
                            icon={
                                [
                                    UsersRound,
                                    CircleDollarSign,
                                    Lightbulb,
                                    AlertTriangle,
                                ][index] ?? UsersRound
                            }
                        />
                    ))}
                </section>
                <section className="grid gap-6 xl:grid-cols-[1.35fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center justify-between gap-3">
                                <span>Fila inteligente</span>
                                <Badge variant="secondary">
                                    Atualizada agora
                                </Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {alerts.map((alert) => (
                                <Link
                                    key={alert.id}
                                    href={alert.href ?? adminRoutes.clients}
                                    className="flex items-start gap-3 rounded-xl border border-border p-4 transition-colors hover:border-primary/40 hover:bg-muted/30"
                                >
                                    <span
                                        className={`mt-1 size-2 shrink-0 rounded-full ${alert.severity === 'high' ? 'bg-rose-500' : alert.severity === 'medium' ? 'bg-amber-500' : 'bg-blue-500'}`}
                                        aria-hidden="true"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <strong className="block text-sm font-semibold">
                                            {alert.title}
                                        </strong>
                                        <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                                            {alert.detail}
                                        </span>
                                    </span>
                                    <span className="text-xs font-medium text-primary">
                                        Ver
                                    </span>
                                </Link>
                            ))}
                            {alerts.length === 0 ? (
                                <p className="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
                                    Nenhum alerta requer atenção agora.
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Saúde da operação</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {health.length > 0 ? (
                                health.map((item) => (
                                    <div
                                        key={item.label}
                                        className="flex items-center justify-between gap-4"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">
                                                {item.label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.detail}
                                            </p>
                                        </div>
                                        <span className="text-lg font-semibold">
                                            {item.value}
                                        </span>
                                    </div>
                                ))
                            ) : (
                                <p className="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
                                    Os indicadores de saúde estarão disponíveis
                                    quando houver dados suficientes.
                                </p>
                            )}
                            <Button
                                variant="outline"
                                asChild
                                className="w-full"
                            >
                                <Link href={adminRoutes.clients}>
                                    Explorar clientes
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </section>
            </PageCanvas>
        </>
    );
}

PlatformDashboard.layout = {
    breadcrumbs: [{ title: 'Administração', href: adminRoutes.dashboard }],
};
