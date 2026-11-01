import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/notifications';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { notificationUnreadCount = 0 } = usePage<{
        notificationUnreadCount?: number;
    }>().props;

    return (
        <header className="sticky top-0 z-20 flex min-h-[calc(4rem+env(safe-area-inset-top))] shrink-0 items-center gap-2 border-b border-sidebar-border/50 bg-background/90 px-4 pt-[env(safe-area-inset-top)] transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 supports-[backdrop-filter]:backdrop-blur-md md:px-6">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <Button
                asChild
                variant="ghost"
                size="icon"
                className="relative ml-auto"
            >
                <Link
                    href={index()}
                    aria-label={`Notificações: ${notificationUnreadCount} não lidas`}
                >
                    <Bell className="size-5" />
                    {notificationUnreadCount > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 min-w-4 rounded-full bg-primary px-1 text-center text-[10px] text-primary-foreground">
                            {notificationUnreadCount > 99
                                ? '99+'
                                : notificationUnreadCount}
                        </span>
                    )}
                </Link>
            </Button>
        </header>
    );
}
