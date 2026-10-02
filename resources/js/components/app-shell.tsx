import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';
import { useIsTablet } from '@/hooks/use-mobile';
import type { AppVariant, SharedPageProps } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const sidebarOpen = usePage<SharedPageProps>().props.sidebarOpen;
    const isTablet = useIsTablet();

    if (variant === 'header') {
        return <div className="flex min-h-dvh w-full flex-col">{children}</div>;
    }

    return (
        <SidebarProvider
            key={isTablet ? 'tablet' : 'desktop'}
            defaultOpen={sidebarOpen && !isTablet}
        >
            {children}
        </SidebarProvider>
    );
}
