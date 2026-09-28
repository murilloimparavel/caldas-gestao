export type AdminStatus = 'active' | 'trial' | 'grace' | 'past_due' | 'suspended' | 'expired' | 'cancelled' | 'closed';

export type AdminClient = {
    id: string;
    name: string;
    email: string;
    company?: string | null;
    plan: string;
    status: AdminStatus;
    monthlyValueCents: number;
    members: number;
    renewsAt?: string | null;
    createdAt?: string | null;
};

export type AdminMetric = {
    label: string;
    value: string;
    detail: string;
    trend?: string;
    tone?: 'blue' | 'green' | 'amber' | 'rose';
};

export type AdminAlert = {
    id: string;
    title: string;
    detail: string;
    severity: 'high' | 'medium' | 'low';
    href?: string;
};

export const adminRoutes = {
    dashboard: '/admin',
    clients: '/admin/tenants',
    client: (id: string | number) => `/admin/tenants/${id}`,
    createClient: '/admin/tenants/create',
    plans: '/admin/plans',
};

export const statusLabels: Record<AdminStatus, string> = {
    active: 'Ativo',
    trial: 'Em teste',
    grace: 'Em carência',
    past_due: 'Pagamento pendente',
    suspended: 'Suspenso',
    expired: 'Expirado',
    cancelled: 'Cancelado',
    closed: 'Encerrado',
};

export function formatAdminMoney(cents: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(cents / 100);
}

export function formatAdminDate(value?: string | null): string {
    if (!value) return 'Não informado';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(date);
}
