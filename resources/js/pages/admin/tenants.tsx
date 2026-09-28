import PlatformClients from '@/pages/platform/clients';
import type { AdminClient } from '@/features/admin/types';

type Tenant = { id: string; name: string; slug?: string; status: AdminClient['status']; memberships_count?: number; memberships?: Array<{ user?: { email?: string | null } }>; subscriptions?: Array<{ plan?: { name?: string | null; price_cents?: number | null }; ends_at?: string | null }> };
type Props = { tenants?: { data?: Tenant[] }; filters?: { search?: string } };

export default function AdminTenants({ tenants = { data: [] }, filters = {} }: Props) {
    const clients: AdminClient[] = (tenants.data ?? []).map((tenant) => {
        const subscription = tenant.subscriptions?.[0];
        const memberEmail = tenant.memberships?.[0]?.user?.email;
        return { id: tenant.id, name: tenant.name, email: memberEmail ?? 'Sem e-mail principal', plan: subscription?.plan?.name ?? 'Sem plano', status: tenant.status, monthlyValueCents: subscription?.plan?.price_cents ?? 0, members: tenant.memberships_count ?? tenant.memberships?.length ?? 0, renewsAt: subscription?.ends_at ?? null };
    });
    return <PlatformClients clients={clients} filters={filters} />;
}

AdminTenants.layout = { breadcrumbs: [{ title: 'Administração', href: '/admin' }, { title: 'Clientes', href: '/admin/tenants' }] };
