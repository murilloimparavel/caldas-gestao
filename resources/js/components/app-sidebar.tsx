import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Banknote,
    Boxes,
    CalendarDays,
    ClipboardList,
    FolderTree,
    Globe2,
    Gift,
    Layers,
    LayoutDashboard,
    Package,
    PieChart,
    Repeat2,
    Scissors,
    Settings,
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
import commissions from '@/routes/commissions';
import customers from '@/routes/customers';
import finance from '@/routes/finance';
import financialObligations from '@/routes/financial_obligations';
import inventory from '@/routes/inventory';
import onlineBooking from '@/routes/online_booking';
import packagesRoutes from '@/routes/packages';
import products from '@/routes/products';
import professionals from '@/routes/professionals';
import saleCategories from '@/routes/sale-categories';
import sales from '@/routes/sales';
import services from '@/routes/services';
import subscriptions from '@/routes/subscriptions';
import suppliers from '@/routes/suppliers';
import type { SharedPageProps, SidebarNavGroup } from '@/types';

const mainNavGroups: SidebarNavGroup[] = [
    {
        id: 'principal',
        label: 'Principal',
        icon: LayoutDashboard,
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
                permission: 'calendar.view',
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
        id: 'cadastros',
        label: 'Cadastros',
        icon: Users,
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
        ],
    },
    {
        id: 'controle',
        label: 'Controle',
        icon: Boxes,
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
                title: 'Assinaturas',
                href: subscriptions.index(),
                icon: Repeat2,
                permission: 'subscription.view',
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
                title: 'Categorias de Comanda',
                href: saleCategories.index(),
                icon: Layers,
                permission: 'sale_category.view',
            },
        ],
    },
    {
        id: 'configuracoes',
        label: 'Configurações',
        icon: Settings,
        items: [
            {
                title: 'Agendamento online',
                href: onlineBooking.index(),
                icon: Globe2,
                permission: 'unit.view',
            },
        ],
    },
    {
        id: 'financeiro',
        label: 'Financeiro',
        icon: WalletCards,
        items: [
            {
                title: 'Painel Financeiro',
                href: finance.dashboard(),
                icon: PieChart,
                permission: 'financial.view',
            },
            {
                title: 'Contas a Pagar/Receber',
                href: financialObligations.index(),
                icon: ArrowLeftRight,
                permission: 'financial.view',
            },
            {
                title: 'Comissões',
                href: commissions.index(),
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
        <Sidebar collapsible="icon" variant="inset" role="navigation" aria-label="Barra lateral de navegação">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch aria-label="Ir para o Painel">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <nav aria-label="Navegação principal">
                    <NavMain
                        groups={visibleGroups}
                        persistenceKey={props.auth.user?.id}
                    />
                </nav>
            </SidebarContent>
            <div className="mt-auto p-2">
                <NavUser />
            </div>
        </Sidebar>
    );
}
