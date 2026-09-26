import { useId } from 'react';
import type { ReactNode } from 'react';

type DashboardPanelProps = {
    title: string;
    description?: string;
    eyebrow?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
};

export function DashboardPanel({
    title,
    description,
    eyebrow,
    action,
    children,
    className = '',
}: DashboardPanelProps) {
    const titleId = useId();

    return (
        <section
            className={`surface-panel min-w-0 overflow-hidden ${className}`}
            aria-labelledby={titleId}
        >
            <header className="flex flex-wrap items-start justify-between gap-3 border-b border-border/70 px-4 py-4 sm:px-5">
                <div className="min-w-0">
                    {eyebrow && (
                        <p className="text-2xs font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                            {eyebrow}
                        </p>
                    )}
                    <h2
                        id={titleId}
                        className="mt-1 text-base font-semibold tracking-tight text-foreground sm:text-lg"
                    >
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-1 max-w-2xl text-xs leading-5 text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {action}
            </header>
            {children}
        </section>
    );
}
