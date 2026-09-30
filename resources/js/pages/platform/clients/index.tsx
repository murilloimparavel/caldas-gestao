import { Head, Link } from '@inertiajs/react';
import { Search, SlidersHorizontal } from 'lucide-react';
import { AdminPageHeader } from '@/features/admin/components/admin-page-header';
import { AdminStatusBadge } from '@/features/admin/components/admin-status-badge';
import {
    adminRoutes,
    formatAdminDate,
    formatAdminMoney,
} from '@/features/admin/types';
import type { AdminClient } from '@/features/admin/types';
import { PageCanvas } from '@/components/operational';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Props = {
    clients?: AdminClient[];
    filters?: { search?: string; status?: string };
};
export default function PlatformClients({ clients = [], filters = {} }: Props) {
    return (
        <>
            <Head title="Clientes da plataforma" />
            <PageCanvas className="gap-8">
                <AdminPageHeader
                    title="Clientes e contas"
                    description="Acompanhe cada operação, plano contratado e oportunidade de retenção."
                    actionHref={adminRoutes.createClient}
                    actionLabel="Novo cliente"
                />
                <div className="surface-panel flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <form
                        action={adminRoutes.clients}
                        method="get"
                        className="flex flex-1 gap-2 sm:max-w-xl"
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Buscar por empresa, e-mail ou plano"
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit">Buscar</Button>
                    </form>
                    <Button variant="outline">
                        <SlidersHorizontal className="size-4" />
                        Filtros
                    </Button>
                </div>
                <div className="surface-panel overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Cliente</TableHead>
                                <TableHead>Plano</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Valor mensal</TableHead>
                                <TableHead>Próxima renovação</TableHead>
                                <TableHead className="text-right">
                                    Ação
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {clients.map((client) => (
                                <TableRow key={client.id}>
                                    <TableCell>
                                        <Link
                                            href={adminRoutes.client(client.id)}
                                            className="font-semibold hover:text-primary"
                                        >
                                            {client.name}
                                        </Link>
                                        <span className="block text-xs text-muted-foreground">
                                            {client.email}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span className="font-medium">
                                            {client.plan}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {client.members} usuários
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <AdminStatusBadge
                                            status={client.status}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        {formatAdminMoney(
                                            client.monthlyValueCents,
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {formatAdminDate(client.renewsAt)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={adminRoutes.client(
                                                    client.id,
                                                )}
                                            >
                                                Abrir
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    {clients.length === 0 ? (
                        <p className="p-10 text-center text-sm text-muted-foreground">
                            Nenhum cliente encontrado.
                        </p>
                    ) : null}
                </div>
            </PageCanvas>
        </>
    );
}
PlatformClients.layout = {
    breadcrumbs: [
        { title: 'Administração', href: adminRoutes.dashboard },
        { title: 'Clientes', href: adminRoutes.clients },
    ],
};
