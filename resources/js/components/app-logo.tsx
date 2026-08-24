import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center text-[#3167d8]">
                <AppLogoIcon className="size-9" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate text-sm leading-tight font-semibold">
                    Caldas Gestão
                </span>
                <span className="mt-0.5 truncate text-[10px] tracking-[0.13em] text-sidebar-foreground/55 uppercase">
                    Operação diária
                </span>
            </div>
        </>
    );
}
