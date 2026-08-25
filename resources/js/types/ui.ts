import type { ReactNode } from 'react';
import type { Auth } from '@/types/auth';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type TenantSummary = {
    id: string;
    name: string;
    slug: string;
    status: string;
    timezone: string;
    default_currency: string;
};

export type UnitSummary = {
    id: string;
    name: string;
    slug: string;
    status: string;
    timezone: string | null;
};

export type Workspace = {
    tenant: TenantSummary;
    activeUnit: UnitSummary | null;
    availableUnits: UnitSummary[];
};

export type Flash = {
    success?: string | null;
    info?: string | null;
    warning?: string | null;
    error?: string | null;
};

export type Ui = {
    sidebarOpen: boolean;
};

export type SharedPageProps = {
    schemaVersion: 1;
    requestId: string;
    correlationId: string;
    name: string;
    auth: Auth;
    workspace: Workspace | null;
    flash: Flash;
    ui: Ui;
    sidebarOpen: boolean;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
