import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface ProductShellProps {
    badge?: string;
    children: ReactNode;
    className?: string;
    title?: string;
    url?: string;
}

export function ProductShell({
    badge = 'Ao vivo',
    children,
    className,
    title = 'Caldas Gestão',
    url = 'caldas.app/gestao',
}: ProductShellProps) {
    return (
        <div
            className={cn(
                'group relative flex flex-col overflow-hidden rounded-2xl border border-white/15 bg-[#0F1311] text-[#F2EFE7] shadow-2xl shadow-black/60 transition-all duration-300',
                className,
            )}
        >
            {/* Window chrome header */}
            <div className="flex items-center justify-between border-b border-white/10 bg-[#161B18] px-4 py-3">
                <div className="flex items-center gap-2">
                    <span
                        className="size-3 rounded-full bg-[#FF5F56]/80 transition group-hover:bg-[#FF5F56]"
                        aria-hidden="true"
                    />
                    <span
                        className="size-3 rounded-full bg-[#FFBD2E]/80 transition group-hover:bg-[#FFBD2E]"
                        aria-hidden="true"
                    />
                    <span
                        className="size-3 rounded-full bg-[#27C93F]/80 transition group-hover:bg-[#27C93F]"
                        aria-hidden="true"
                    />
                </div>

                <div className="flex items-center gap-2 rounded-md border border-white/10 bg-[#0A0C0B]/70 px-3 py-1 text-3xs font-medium text-[#A9A79D]">
                    <span
                        className="size-1.5 rounded-full bg-[#C8FF3D]"
                        aria-hidden="true"
                    />
                    <span className="font-mono">{url}</span>
                </div>

                <div className="flex items-center gap-2">
                    <span className="hidden text-3xs font-semibold text-[#A9A79D] sm:inline">
                        {title}
                    </span>
                    {badge ? (
                        <span className="inline-flex items-center rounded-full border border-[#C8FF3D]/30 bg-[#C8FF3D]/10 px-2 py-0.5 text-3xs font-bold text-[#C8FF3D]">
                            {badge}
                        </span>
                    ) : null}
                </div>
            </div>

            {/* Window Content Area */}
            <div className="relative flex-1 overflow-hidden p-4 sm:p-6">
                {children}
            </div>
        </div>
    );
}

export default ProductShell;
