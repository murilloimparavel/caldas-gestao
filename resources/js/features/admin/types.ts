import admin from '@/routes/admin';
import adminMemberships from '@/routes/admin/tenants/memberships';
import adminPlans from '@/routes/admin/plans';
import adminSubscription from '@/routes/admin/tenants/subscription';
import adminTenants from '@/routes/admin/tenants';
import adminUsers from '@/routes/admin/tenants/users';

export type AdminStatus =
    | 'active'
    | 'trial'
    | 'grace'
    | 'past_due'
    | 'suspended'
    | 'expired'
    | 'cancelled'
    | 'closed';

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

export type AdminPlan = {
    id: string;
    key?: string;
    name: string;
    description?: string | null;
    priceCents: number;
    price_cents?: number;
    billingCycle?: 'monthly' | 'quarterly' | 'yearly' | string;
    billing_cycle?: 'monthly' | 'quarterly' | 'yearly' | string;
    trialDays?: number | null;
    trial_days?: number | null;
    isActive?: boolean;
    is_active?: boolean;
    limits?: Record<string, number | string | null>;
    features?: string[];
    subscribers?: number;
};

export type AdminUser = {
    id: string;
    name: string;
    email: string;
    status: string;
    role?: string | null;
    invitedAt?: string | null;
    lastLoginAt?: string | null;
};

export type AdminAuditEvent = {
    id: string;
    action: string;
    description?: string | null;
    actor?: string | null;
    createdAt: string;
    metadata?: Record<string, unknown>;
};

export const adminRoutes = {
    dashboard: admin.dashboard.url(),
    audit: admin.audit.url(),
    clients: admin.tenants.url(),
    client: (id: string | number) => adminTenants.show.url(String(id)),
    clientSuspend: (id: string | number) =>
        adminTenants.suspend.url(String(id)),
    clientActivate: (id: string | number) =>
        adminTenants.activate.url(String(id)),
    createClient: adminTenants.create.url(),
    plans: adminPlans.index.url(),
    plan: (id: string | number) => adminPlans.update.url(String(id)),
    planDeactivate: (id: string | number) =>
        adminPlans.deactivate.url(String(id)),
    clientSubscription: (id: string | number) =>
        adminSubscription.update.url(String(id)),
    clientUsers: (id: string | number) => adminUsers.store.url(String(id)),
    clientUserInvite: (id: string | number) => adminUsers.store.url(String(id)),
    clientUserAccess: (
        tenantId: string | number,
        membershipId: string | number,
    ) =>
        adminUsers.sendAccess.url({
            tenant: String(tenantId),
            membership: String(membershipId),
        }),
    clientMembershipRevoke: (
        tenantId: string | number,
        membershipId: string | number,
    ) =>
        adminMemberships.revoke.url({
            tenant: String(tenantId),
            membership: String(membershipId),
        }),
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
    if (!value) {
        return 'Não informado';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(
              date,
          );
}
