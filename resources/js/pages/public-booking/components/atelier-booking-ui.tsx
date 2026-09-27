import type { ReactNode } from 'react';

export const atelierBookingTokens = {
    accent: 'var(--booking-primary, #d4af37)',
    accentSoft: '#ffe9b0',
    canvas: '#131313',
    surface: '#1f1f1f',
    surfaceRaised: '#353535',
    border: '#39362f',
    text: '#f8f2e8',
    muted: '#a9a39a',
} as const;

export function AtelierProgress({
    activeStep,
    label,
}: {
    activeStep: number;
    label: string;
}): ReactNode {
    return (
        <div className="mb-5 flex flex-col gap-1.5">
            <div className="flex items-center justify-between">
                <span className="flex items-center gap-1.5 font-['Manrope'] text-[10px] font-bold tracking-[0.16em] text-[#ffe9b0] uppercase">
                    <span className="size-1.5 rounded-full bg-[var(--booking-primary,#d4af37)]" />
                    Etapa {activeStep} de 4 · {label}
                </span>
                <span className="font-['Manrope'] text-[10px] tracking-widest text-[#a69e94] uppercase">
                    {activeStep * 25}% concluído
                </span>
            </div>
            <div className="h-1 overflow-hidden rounded-full bg-[#353535]">
                <div
                    className="h-full rounded-full bg-[var(--booking-primary,#d4af37)] shadow-[0_0_8px_rgba(242,202,80,0.5)] transition-all duration-500"
                    style={{ width: `${activeStep * 25}%` }}
                />
            </div>
        </div>
    );
}

export function AtelierSectionHeading({
    eyebrow,
    children,
}: {
    eyebrow: string;
    children: ReactNode;
}): ReactNode {
    return (
        <div className="flex items-center justify-between gap-4">
            <div className="flex items-center gap-2">
                <span className="h-px w-4 bg-[#e9c176]" />
                <span className="font-['Manrope'] text-[10px] font-bold tracking-[0.2em] text-[#e9c176] uppercase">
                    {eyebrow}
                </span>
            </div>
            <span className="font-['Manrope'] text-[10px] tracking-widest text-[#a69e94]">
                {children}
            </span>
        </div>
    );
}

export function AtelierSelectionSummary({
    count,
    totalCents,
    totalMinutes,
    formatMoney,
}: {
    count: number;
    totalCents: number;
    totalMinutes: number;
    formatMoney: (cents: number) => string;
}): ReactNode {
    return (
        <div className="fixed right-0 bottom-[76px] left-0 z-40 mx-auto flex w-full max-w-[390px] items-center justify-between border-t border-[#4d4635]/30 bg-[#171612] px-4 py-3 md:max-w-5xl md:px-10">
            <div className="min-w-0">
                <p className="truncate font-['Manrope'] text-[10px] font-bold tracking-[0.14em] text-[#d4af37] uppercase">
                    {count
                        ? `${count} serviço${count > 1 ? 's' : ''} selecionado${count > 1 ? 's' : ''}`
                        : 'Nenhum serviço'}
                </p>
                <p className="font-['Plus_Jakarta_Sans'] text-lg font-extrabold text-[#ffe9b0]">
                    {formatMoney(totalCents)}{' '}
                    <span className="font-['Manrope'] text-xs font-medium text-[#a9a39a]">
                        · {totalMinutes} min
                    </span>
                </p>
            </div>
        </div>
    );
}
