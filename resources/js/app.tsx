import { createInertiaApp } from '@inertiajs/react';
import { lazy, Suspense, useEffect } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { Spinner } from '@/components/ui/spinner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';

const AppLayout = lazy(() => import('@/layouts/app-layout'));
const AuthLayout = lazy(() => import('@/layouts/auth-layout'));
const SettingsLayout = lazy(() => import('@/layouts/settings/layout'));

const appName = import.meta.env.VITE_APP_NAME || 'Caldas Gestão';

function AppLoadingFallback() {
    return (
        <main className="flex min-h-dvh items-center justify-center bg-background text-foreground">
            <div className="flex items-center gap-3 rounded-lg border bg-card px-4 py-3 text-sm text-muted-foreground shadow-sm">
                <Spinner className="size-5" aria-label="Carregando a página" />
                <span aria-hidden="true">Carregando…</span>
            </div>
        </main>
    );
}

function AppBootstrap({ children }: { children: React.ReactNode }) {
    useEffect(() => {
        document.getElementById('app-loading')?.remove();
    }, []);

    return children;
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('marketing/'):
            case name.startsWith('public-booking/'):
            case name.startsWith('public/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <Suspense fallback={<AppLoadingFallback />}>
                <AppBootstrap>
                    <TooltipProvider delayDuration={0}>
                        {app}
                        <Toaster />
                    </TooltipProvider>
                </AppBootstrap>
            </Suspense>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
