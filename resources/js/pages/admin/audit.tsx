import { Head } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { AdminPageHeader } from '@/features/admin/components/admin-page-header';
import {
    adminRoutes,
    formatAdminDate,
} from '@/features/admin/types';
import type { AdminAuditEvent } from '@/features/admin/types';
import {
    EmptyState,
    PageCanvas,
    Pagination,
} from '@/components/operational';
import type { Paginated } from '@/components/operational';
import { Card, CardContent } from '@/components/ui/card';

type RawEvent = AdminAuditEvent & {
    created_at?: string;
    occurred_at?: string;
    actor?: { name?: string; email?: string } | null;
    actor_user?: { name?: string; email?: string } | null;
};
type Props = { events?: Paginated<RawEvent> | RawEvent[] };

export default function AdminAudit({ events: inputEvents = [] }: Props) {
    const paginator = Array.isArray(inputEvents) ? undefined : inputEvents;

    const events = Array.isArray(inputEvents)
        ? inputEvents
        : (inputEvents.data ?? []);

    return (
        <>
            <Head title="Auditoria administrativa" />
            <PageCanvas className="gap-8">
                <AdminPageHeader
                    title="Auditoria administrativa"
                    description="Consulte as alterações relevantes feitas no painel e quem as executou."
                />
                {events.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Nenhuma alteração registrada"
                        description="Eventos administrativos aparecerão aqui quando uma ação for concluída."
                    />
                ) : (
                    <Card>
                        <CardContent className="p-0">
                            <ol className="divide-y">
                                {events.map((event) => {
                                    const actor =
                                        event.actor ?? event.actor_user;
                                    const date =
                                        event.createdAt ??
                                        event.created_at ??
                                        event.occurred_at ??
                                        '';

                                    return (
                                        <li
                                            key={event.id}
                                            className="flex gap-4 p-5"
                                        >
                                            <span className="mt-1 flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                                <ClipboardList className="size-4" />
                                            </span>
                                            <div className="min-w-0">
                                                <p className="font-medium">
                                                    {event.description ??
                                                        event.action}
                                                </p>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {actor?.name ??
                                                        actor?.email ??
                                                        'Sistema'}{' '}
                                                    · {formatAdminDate(date)}
                                                </p>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>
                        </CardContent>
                    </Card>
                )}
                {paginator ? <Pagination links={paginator.links} /> : null}
            </PageCanvas>
        </>
    );
}

AdminAudit.layout = {
    breadcrumbs: [
        { title: 'Administração', href: adminRoutes.dashboard },
        { title: 'Auditoria', href: adminRoutes.audit },
    ],
};
