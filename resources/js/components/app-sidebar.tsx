import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CalendarDays,
    ClipboardList,
    FolderTree,
    LayoutDashboard,
    Package,
    Scissors,
    Tags,
    Truck,
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
import categories from '@/routes/categories';
import customers from '@/routes/customers';
import products from '@/routes/products';
import professionals from '@/routes/professionals';
import services from '@/routes/services';
import suppliers from '@/routes/suppliers';
import type { SharedPageProps, SidebarNavGroup } from '@/types';

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
            {
                title: 'Clientes',
                href: customers.index(),
                icon: Users,
                permission: 'customer.view',
            },
            {
                title: 'Profissionais',
                href: professionals.index(),
                icon: UserRound,
                permission: 'professional.view',
            },
        ],
    },
    {
        label: 'Gestão',
        items: [
            {
                title: 'Serviços',
                href: services.index(),
                icon: Scissors,
                permission: 'service.view',
            },
            {
                title: 'Produtos',
                href: products.index(),
                icon: Package,
                permission: 'product.view',
            },
            {
                title: 'Categorias',
                href: categories.index(),
                icon: FolderTree,
                permission: 'category.view',
            },
            {
                title: 'Fornecedores',
                href: suppliers.index(),
                icon: Truck,
                permission: 'supplier.view',
            },
            { title: 'Catálogo', icon: Tags, disabled: true },
            { title: 'Financeiro', icon: WalletCards, disabled: true },
            { title: 'Relatórios', icon: BarChart3, disabled: true },
        ],
    },
];

export function AppSidebar() {
    const { props } = usePage<SharedPageProps>();
    const permissions = new Set(props.auth.permissions);
    const visibleGroups = mainNavGroups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.permission || permissions.has(item.permission),
            ),
        }))
        .filter((group) => group.items.length > 0);

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
                    <NavMain groups={visibleGroups} />
                </nav>
            </SidebarContent>
            <div className="mt-auto p-2">
                <NavUser />
            </div>
        </Sidebar>
    );
}
