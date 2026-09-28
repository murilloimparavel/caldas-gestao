import PlatformDashboard from '@/pages/platform';
import {
    adminRoutes,
    type AdminAlert,
    type AdminMetric,
} from '@/features/admin/types';

type Props = {
    metrics?: {
        tenants?: number;
        active_tenants?: number;
        subscriptions?: number;
        active_subscriptions?: number;
    };
    recentTenants?: unknown[];
    alerts?: AdminAlert[];
    health?: Array<{ label: string; value: string; detail: string }>;
};

export default function AdminDashboard({
    metrics = {},
    alerts = [],
    health = [],
}: Props) {
    const total = metrics.tenants ?? 0;
    const active = metrics.active_tenants ?? 0;
    const subscriptions = metrics.subscriptions ?? 0;
    const activeSubscriptions = metrics.active_subscriptions ?? 0;
    const dashboardMetrics: AdminMetric[] = [
        {
            label: 'Clientes cadastrados',
            value: String(total),
            detail: `${active} contas ativas`,
            tone: 'blue',
        },
        {
            label: 'Assinaturas ativas',
            value: String(activeSubscriptions),
            detail: `${subscriptions} assinaturas no total`,
            tone: 'green',
        },
        {
            label: 'Taxa de ativação',
            value: total ? `${Math.round((active / total) * 100)}%` : '0%',
            detail: 'clientes com acesso ativo',
            tone: 'amber',
        },
        {
            label: 'Atenções abertas',
            value: String(alerts.length),
            detail:
                alerts.length === 0
                    ? 'Nenhuma ação pendente'
                    : 'itens que pedem acompanhamento',
            tone: 'rose',
        },
    ];
    return (
        <PlatformDashboard
            metrics={dashboardMetrics}
            alerts={alerts}
            health={health}
        />
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Administração', href: adminRoutes.dashboard }],
};
