import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Banknote,
    BarChart3,
    Boxes,
    CalendarDays,
    ClipboardList,
    FolderTree,
    Gift,
    Layers,
    LayoutDashboard,
    Package,
    PieChart,
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
import calendar from '@/routes/calendar';
import cashShifts from '@/routes/cash_shifts';
import categories from '@/routes/categories';
import customers from '@/routes/customers';
import inventory from '@/routes/inventory';
import packagesRoutes from '@/routes/packages';
import products from '@/routes/products';
import professionals from '@/routes/professionals';
import saleCategories from '@/routes/sale-categories';
import sales from '@/routes/sales';
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
            {
                title: 'Agenda',
                href: calendar.index(),
                icon: CalendarDays,
                permission: 'appointment.view',
            },
            {
                title: 'Comandas',
                href: sales.index(),
                icon: ClipboardList,
                permission: 'sale.view',
            },
            {
                title: 'Caixa Operacional',
                href: cashShifts.index(),
                icon: Banknote,
                permission: 'cash_shift.view',
            },
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
                title: 'Pacotes de Serviços',
                href: packagesRoutes.index(),
                icon: Gift,
                permission: 'package.view',
            },
            {
                title: 'Produtos',
                href: products.index(),
                icon: Package,
                permission: 'product.view',
            },
            {
                title: 'Estoque',
                href: inventory.index(),
                icon: Boxes,
                permission: 'inventory.view',
            },
            {
                title: 'Categorias',
                href: categories.index(),
                icon: FolderTree,
                permission: 'category.view',
            },
            {
                title: 'Categorias de Comanda',
                href: saleCategories.index(),
                icon: Layers,
                permission: 'sale_category.view',
            },
            {
                title: 'Fornecedores',
                href: suppliers.index(),
                icon: Truck,
                permission: 'supplier.view',
            },
            { title: 'Catálogo', icon: Tags, disabled: true },
            { title: 'Relatórios', icon: BarChart3, disabled: true },
        ],
    },
    {
        label: 'Financeiro',
        items: [
            {
                title: 'Painel Financeiro',
                href: '/finance/dashboard',
                icon: PieChart,
                permission: 'financial.view',
            },
            {
                title: 'Contas a Pagar/Receber',
                href: '/finance/transactions',
                icon: ArrowLeftRight,
                permission: 'financial.view',
            },
            {
                title: 'Comissões',
                href: '/finance/commissions',
                icon: WalletCards,
                permission: 'commission.view',
            },
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
