import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCheck, Settings } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { PageCanvas, ResourceHeader } from '@/components/operational';
import { index, read, readAll, open } from '@/routes/notifications';
import { edit } from '@/routes/notification-settings';

type Notice = {
    id: string;
    category: string;
    title: string;
    body: string;
    read_at: string | null;
    created_at: string;
    appointment_id: string | null;
};
type Props = {
    notifications: {
        data: Notice[];
        prev_page_url: string | null;
        next_page_url: string | null;
        current_page: number;
    };
    unreadCount: number;
};
export default function NotificationsIndex({
    notifications,
    unreadCount,
}: Props) {
    return (
        <>
            <Head title="Notificações" />
            <PageCanvas>
                <ResourceHeader
                    title="Notificações"
                    description={
                        unreadCount > 0
                            ? `${unreadCount} aviso(s) ainda não lido(s)`
                            : 'Sua agenda, com os avisos importantes em um só lugar.'
                    }
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={edit()}>
                                    <Settings className="size-4" />
                                    Preferências
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                disabled={unreadCount === 0}
                                onClick={() =>
                                    router.post(
                                        readAll.url(),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <CheckCheck className="size-4" />
                                Marcar todas como lidas
                            </Button>
                        </div>
                    }
                />
                <div className="mt-6 divide-y overflow-hidden rounded-xl border bg-card">
                    {notifications.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 px-6 py-16 text-center">
                            <Bell className="size-8 text-muted-foreground" />
                            <h2 className="font-medium">Tudo em dia</h2>
                            <p className="max-w-sm text-sm text-muted-foreground">
                                Os avisos dos agendamentos vinculados ao seu
                                perfil aparecerão aqui.
                            </p>
                        </div>
                    ) : (
                        notifications.data.map((notice) => (
                            <article
                                key={notice.id}
                                className={`flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:justify-between ${notice.read_at ? '' : 'bg-primary/5'}`}
                            >
                                <div className="min-w-0 space-y-2">
                                    <div className="flex items-center gap-2">
                                        {!notice.read_at && (
                                            <span
                                                className="size-2 shrink-0 rounded-full bg-primary"
                                                aria-label="Não lida"
                                            />
                                        )}
                                        <h2 className="font-medium">
                                            {notice.title}
                                        </h2>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {notice.body}
                                    </p>
                                    <time
                                        className="block text-xs text-muted-foreground"
                                        dateTime={notice.created_at}
                                    >
                                        {new Date(
                                            notice.created_at,
                                        ).toLocaleString('pt-BR')}
                                    </time>
                                </div>
                                <div className="flex shrink-0 flex-wrap gap-2">
                                    {notice.appointment_id && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link href={open(notice.id)}>
                                                Ver agendamento
                                            </Link>
                                        </Button>
                                    )}
                                    {!notice.read_at && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    read.url(notice.id),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Marcar como lida
                                        </Button>
                                    )}
                                </div>
                            </article>
                        ))
                    )}
                </div>
                {(notifications.prev_page_url ||
                    notifications.next_page_url) && (
                    <nav
                        aria-label="Páginas de notificações"
                        className="mt-5 flex items-center justify-between gap-3"
                    >
                        <Button
                            variant="outline"
                            disabled={!notifications.prev_page_url}
                            onClick={() =>
                                router.get(
                                    index.url({
                                        query: {
                                            page:
                                                notifications.current_page - 1,
                                        },
                                    }),
                                )
                            }
                        >
                            Anterior
                        </Button>
                        <span className="text-sm text-muted-foreground">
                            Página {notifications.current_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={!notifications.next_page_url}
                            onClick={() =>
                                router.get(
                                    index.url({
                                        query: {
                                            page:
                                                notifications.current_page + 1,
                                        },
                                    }),
                                )
                            }
                        >
                            Próxima
                        </Button>
                    </nav>
                )}
            </PageCanvas>
        </>
    );
}
NotificationsIndex.layout = {
    breadcrumbs: [{ title: 'Notificações', href: index() }],
};
