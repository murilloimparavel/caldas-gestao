import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, LayoutDashboard, Menu, Users } from 'lucide-react';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import customers from '@/routes/customers';
import type { SharedPageProps } from '@/types';

const itemClassName =
    'flex min-h-11 min-w-11 flex-1 flex-col items-center justify-center gap-0.5 rounded-lg px-2 py-1 text-[11px] font-medium leading-tight transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring';

export function MobileBottomNav() {
    const { isCurrentUrl } = useCurrentUrl();
    const { props } = usePage<SharedPageProps>();
    const canViewCustomers = props.auth.permissions.includes('customer.view');
    const dashboardIsActive = isCurrentUrl(dashboard());
    const customersIsActive = isCurrentUrl(customers.index(), undefined, true);

    return (
        <nav
            aria-label="Navegação móvel"
            className="fixed inset-x-4 bottom-0 z-30 flex min-h-16 items-center rounded-t-2xl border border-b-0 border-sidebar-border bg-sidebar/95 px-2 pb-[env(safe-area-inset-bottom)] text-sidebar-foreground shadow-[0_-8px_24px_-16px_rgba(15,23,42,0.8)] backdrop-blur-md sm:hidden"
        >
            <div className="flex w-full items-center gap-1">
                <Link
                    href={dashboard()}
                    prefetch
                    aria-current={dashboardIsActive ? 'page' : undefined}
                    className={cn(
                        itemClassName,
                        dashboardIsActive
                            ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                            : 'text-sidebar-foreground/75 hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground',
                    )}
                >
                    <LayoutDashboard className="size-5" aria-hidden="true" />
                    <span>Painel</span>
                </Link>

                <button
                    type="button"
                    aria-disabled="true"
                    aria-label="Agenda — Em breve"
                    disabled
                    className={cn(
                        itemClassName,
                        'cursor-not-allowed text-sidebar-foreground/45',
                    )}
                >
                    <CalendarDays className="size-5" aria-hidden="true" />
                    <span>Agenda</span>
                </button>

                {canViewCustomers ? (
                    <Link
                        href={customers.index()}
                        prefetch
                        aria-current={customersIsActive ? 'page' : undefined}
                        className={cn(
                            itemClassName,
                            customersIsActive
                                ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                                : 'text-sidebar-foreground/75 hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground',
                        )}
                    >
                        <Users className="size-5" aria-hidden="true" />
                        <span>Clientes</span>
                    </Link>
                ) : (
                    <button
                        type="button"
                        aria-disabled="true"
                        aria-label="Clientes — indisponível"
                        disabled
                        className={cn(
                            itemClassName,
                            'cursor-not-allowed text-sidebar-foreground/45',
                        )}
                    >
                        <Users className="size-5" aria-hidden="true" />
                        <span>Clientes</span>
                    </button>
                )}

                <SidebarTrigger
                    size="default"
                    aria-label="Mais — abrir navegação principal"
                    className={cn(
                        itemClassName,
                        'h-auto w-auto flex-1 px-2 py-1 text-sidebar-foreground/75 hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground',
                    )}
                >
                    <Menu className="size-5" aria-hidden="true" />
                    <span>Mais</span>
                </SidebarTrigger>
            </div>
        </nav>
    );
}
