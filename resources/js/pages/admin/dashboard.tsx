import PlatformDashboard from '@/pages/platform';
import type { AdminMetric } from '@/features/admin/types';

type Props = {
    metrics?: { tenants?: number; active_tenants?: number; subscriptions?: number; active_subscriptions?: number };
    recentTenants?: unknown[];
};

export default function AdminDashboard({ metrics = {} }: Props) {
    const total = metrics.tenants ?? 0;
    const active = metrics.active_tenants ?? 0;
    const subscriptions = metrics.subscriptions ?? 0;
    const activeSubscriptions = metrics.active_subscriptions ?? 0;
    const dashboardMetrics: AdminMetric[] = [
        { label: 'Clientes cadastrados', value: String(total), detail: `${active} contas ativas`, tone: 'blue' },
        { label: 'Assinaturas ativas', value: String(activeSubscriptions), detail: `${subscriptions} assinaturas no total`, tone: 'green' },
        { label: 'Taxa de ativação', value: total ? `${Math.round((active / total) * 100)}%` : '0%', detail: 'clientes com acesso ativo', tone: 'amber' },
        { label: 'Atenções abertas', value: '—', detail: 'dados de cobrança em breve', tone: 'rose' },
    ];
    return <PlatformDashboard metrics={dashboardMetrics} />;
}

AdminDashboard.layout = { breadcrumbs: [{ title: 'Administração', href: '/admin' }] };
