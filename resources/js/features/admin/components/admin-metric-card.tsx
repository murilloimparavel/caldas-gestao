import {
    ArrowDownRight,
    ArrowUpRight,
    CircleDollarSign,
    UsersRound,
    type LucideIcon,
} from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { AdminMetric } from '@/features/admin/types';

const tones = {
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
    rose: 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300',
};

export function AdminMetricCard({
    metric,
    icon: Icon = UsersRound,
}: {
    metric: AdminMetric;
    icon?: LucideIcon;
}) {
    const positive = metric.trend?.startsWith('+');
    return (
        <Card className="gap-4 py-5">
            <CardContent className="flex items-start justify-between gap-4">
                <div className="space-y-2">
                    <p className="text-sm text-muted-foreground">
                        {metric.label}
                    </p>
                    <p className="text-2xl font-semibold tracking-tight">
                        {metric.value}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {metric.detail}
                    </p>
                    {metric.trend ? (
                        <span
                            className={cn(
                                'inline-flex items-center gap-1 text-xs font-medium',
                                positive ? 'text-emerald-600' : 'text-rose-600',
                            )}
                        >
                            {positive ? (
                                <ArrowUpRight className="size-3.5" />
                            ) : (
                                <ArrowDownRight className="size-3.5" />
                            )}
                            {metric.trend} no período
                        </span>
                    ) : null}
                </div>
                <div
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-xl',
                        tones[metric.tone ?? 'blue'],
                    )}
                >
                    <Icon className="size-5" aria-hidden="true" />
                </div>
            </CardContent>
        </Card>
    );
}

export { CircleDollarSign };
