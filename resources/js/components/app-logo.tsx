import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { SharedPageProps } from '@/types';

export default function AppLogo() {
    const { branding } = usePage<SharedPageProps>().props;

    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center text-[#3167d8]">
                {branding.logoUrl ? (
                    <img
                        src={branding.logoUrl}
                        alt={branding.name}
                        className="size-9 rounded-md object-contain"
                    />
                ) : (
                    <AppLogoIcon className="size-9" />
                )}
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate text-sm leading-tight font-semibold">
                    {branding.name}
                </span>
                <span className="mt-0.5 truncate text-[10px] tracking-[0.13em] text-sidebar-foreground/55 uppercase">
                    Operação diária
                </span>
            </div>
        </>
    );
}
