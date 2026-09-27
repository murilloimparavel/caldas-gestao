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
