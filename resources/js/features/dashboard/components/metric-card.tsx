import type { DashboardMetric } from '../types';

const toneClasses: Record<DashboardMetric['tone'], string> = {
    brand: 'bg-primary',
    success: 'bg-emerald-600',
    warning: 'bg-amber-600',
    neutral: 'bg-slate-500',
};

export function MetricCard({ metric }: { metric: DashboardMetric }) {
    return (
        <article className="surface-panel min-w-0 p-4 transition-shadow hover:shadow-md sm:p-5">
            <div className="flex items-center gap-2">
                <span
                    className={`size-2 rounded-full ${toneClasses[metric.tone]}`}
                    aria-hidden="true"
                />
                <h3 className="truncate text-sm font-medium text-muted-foreground">
                    {metric.label}
                </h3>
            </div>
            <p className="mt-4 font-mono text-2xl font-semibold tracking-[-0.05em] text-foreground sm:mt-5 sm:text-3xl">
                {metric.value}
            </p>
            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                {metric.detail}
            </p>
        </article>
    );
}
