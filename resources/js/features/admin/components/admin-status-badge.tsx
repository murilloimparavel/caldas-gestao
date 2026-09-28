import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { statusLabels, type AdminStatus } from '@/features/admin/types';

export function AdminStatusBadge({ status }: { status: AdminStatus }) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'rounded-full px-2.5 py-1 text-3xs font-semibold',
                status === 'active' || status === 'trial' || status === 'grace'
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                    : status === 'past_due'
                      ? 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
                      : 'border-rose-300 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
            )}
        >
            <span className="size-1.5 rounded-full bg-current" aria-hidden="true" />
            {statusLabels[status]}
        </Badge>
    );
}
