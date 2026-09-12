import { Lock, MoreHorizontal } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { ScheduleBlock } from '@/types/calendar';
import { formatTime } from './date-utils';

export function ScheduleBlockCard({
    block,
    onOpen,
    timeZone,
}: {
    block: ScheduleBlock;
    onOpen?: (block: ScheduleBlock) => void;
    timeZone?: string;
}) {
    return (
        <button
            type="button"
            onClick={(e) => {
                e.stopPropagation();
                onOpen?.(block);
            }}
            onMouseDown={(e) => e.stopPropagation()}
            onPointerDown={(e) => e.stopPropagation()}
            onTouchStart={(e) => e.stopPropagation()}
            className="group flex w-full items-start justify-between rounded-lg border border-slate-300 bg-slate-100/90 p-3 text-left text-slate-800 shadow-2xs transition hover:border-slate-400 hover:bg-slate-200/90 hover:shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-200 dark:hover:bg-slate-800"
        >
            <div className="flex items-start gap-3">
                <div className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    <Lock className="size-3.5" aria-hidden="true" />
                </div>
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold text-slate-900 dark:text-slate-100">
                            {formatTime(block.starts_at, timeZone)} –{' '}
                            {formatTime(block.ends_at, timeZone)}
                        </span>
                        <Badge
                            variant="outline"
                            className="border-slate-300 bg-slate-200/70 text-2xs text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            Ocupado
                        </Badge>
                    </div>
                    <p className="mt-1 truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                        {block.reason || 'Ocupado / Horário bloqueado'}
                    </p>
                    {block.professional?.name ? (
                        <p className="mt-0.5 truncate text-xs text-slate-600 dark:text-slate-400">
                            Profissional: {block.professional.name}
                        </p>
                    ) : (
                        <p className="mt-0.5 text-xs text-slate-600 dark:text-slate-400">
                            Aplica-se a toda a unidade
                        </p>
                    )}
                </div>
            </div>
            <MoreHorizontal
                className="size-4 shrink-0 text-slate-500 opacity-60 transition group-hover:opacity-100 dark:text-slate-400"
                aria-hidden="true"
            />
        </button>
    );
}
