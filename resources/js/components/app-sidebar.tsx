import { Link } from '@inertiajs/react';
import {
    BarChart3,
    CalendarDays,
    ClipboardList,
    LayoutDashboard,
    Tags,
    UserRound,
    Users,
    WalletCards,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { SidebarNavGroup } from '@/types';

const mainNavGroups: SidebarNavGroup[] = [
    {
        label: 'Operação',
        items: [
            {
                title: 'Visão de hoje',
                href: dashboard(),
                icon: LayoutDashboard,
            },
            { title: 'Agenda', icon: CalendarDays, disabled: true },
            { title: 'Comandas', icon: ClipboardList, disabled: true },
        ],
    },
    {
        label: 'Relacionamento',
        items: [
            { title: 'Clientes', icon: Users, disabled: true },
            { title: 'Profissionais', icon: UserRound, disabled: true },
        ],
    },
    {
        label: 'Gestão',
        items: [
            { title: 'Catálogo', icon: Tags, disabled: true },
            { title: 'Financeiro', icon: WalletCards, disabled: true },
            { title: 'Relatórios', icon: BarChart3, disabled: true },
        ],
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <nav aria-label="Navegação principal">
                    <NavMain groups={mainNavGroups} />
                </nav>
            </SidebarContent>
            <div className="mt-auto p-2">
                <NavUser />
            </div>
        </Sidebar>
    );
}
