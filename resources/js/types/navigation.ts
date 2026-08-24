import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

export type SidebarNavItem = Omit<NavItem, 'href'> & {
    href?: NonNullable<InertiaLinkProps['href']>;
    disabled?: boolean;
};

export type SidebarNavGroup = {
    label: string;
    items: SidebarNavItem[];
};
