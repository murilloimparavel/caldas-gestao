import { Link } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Filter,
    Lock,
    RefreshCw,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index as calendarIndex } from '@/routes/calendar';
import type {
    CalendarFilters,
    CalendarRange,
    CalendarView,
} from '@/types/calendar';
import { addDays, addMonths, dateKey, formatDay } from './date-utils';

export function CalendarToolbar({
    canManage,
    date,
    filters,
    onCreate,
    onCreateBlock,
    onFilter,
    onSync,
    isSyncing = false,
    range,
    timeZone,
    view,
}: {
    canManage: boolean;
    date: string;
    filters: CalendarFilters;
    onCreate: () => void;
    onCreateBlock?: () => void;
    onFilter: () => void;
    onSync?: () => void;
    isSyncing?: boolean;
    range?: CalendarRange;
    timeZone?: string;
    view: CalendarView;
}) {
    const step = view === 'month' ? 1 : view === 'week' ? 7 : 1;
    const move = view === 'month' ? addMonths : addDays;
    const previousDate = move(date, -step);
    const nextDate = move(date, step);

    const query = { ...filters, view };

    return (
        <div className="flex flex-col gap-3 sm:gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div className="flex min-w-0 flex-wrap items-center justify-between gap-2 sm:justify-start">
                <div className="flex items-center gap-1 sm:gap-2">
                    <Button
                        asChild
                        size="icon"
                        variant="outline"
                        aria-label="Período anterior"
                        className="h-11 w-11 shrink-0"
                    >
                        <Link
                            href={calendarIndex({
                                query: {
                                    ...query,
                                    date: previousDate,
                                },
                            })}
                        >
                            <ChevronLeft
                                className="size-5"
                                aria-hidden="true"
                            />
                        </Link>
                    </Button>
                    <div className="min-w-0 px-1 text-center sm:text-left">
                        <p className="truncate text-base font-semibold capitalize sm:text-xl">
                            {range?.label ?? formatDay(date, timeZone)}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {view === 'week'
                                ? 'Visão semanal'
                                : view === 'month'
                                  ? 'Visão mensal'
                                  : 'Visão diária'}
                        </p>
                    </div>
                    <Button
                        asChild
                        size="icon"
                        variant="outline"
                        aria-label="Próximo período"
                        className="h-11 w-11 shrink-0"
                    >
                        <Link
                            href={calendarIndex({
                                query: {
                                    ...query,
                                    date: nextDate,
                                },
                            })}
                        >
                            <ChevronRight
                                className="size-5"
                                aria-hidden="true"
                            />
                        </Link>
                    </Button>
                </div>
                <div className="flex items-center gap-1.5 sm:gap-2">
                    <Button
                        asChild
                        variant="ghost"
                        className="h-11 min-h-[44px] px-3 font-medium sm:inline-flex sm:h-9 sm:min-h-0"
                    >
                        <Link
                            href={calendarIndex({
                                query: {
                                    ...filters,
                                    date: dateKey(
                                        new Date().toISOString(),
                                        timeZone,
                                    ),
                                    view,
                                },
                            })}
                        >
                            Hoje
                        </Link>
                    </Button>

                    <div
                        className="inline-flex items-center gap-1.5 rounded-full border border-border/70 bg-muted/40 px-2.5 py-1 text-xs font-medium text-muted-foreground select-none"
                        title="Agenda conectada com sincronização automática a cada 30 segundos"
                    >
                        <span
                            className="size-2 animate-pulse rounded-full bg-emerald-500"
                            aria-hidden="true"
                        />
                        <span className="xs:inline hidden">Ao vivo</span>
                    </div>

                    {onSync ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={onSync}
                            disabled={isSyncing}
                            aria-label={
                                isSyncing
                                    ? 'Sincronizando agenda…'
                                    : 'Sincronizar agenda manualmente'
                            }
                            title="Sincronizar agenda agora"
                            className="h-11 w-11 shrink-0 sm:h-9 sm:w-9"
                        >
                            <RefreshCw
                                className={cn(
                                    'size-4',
                                    isSyncing && 'animate-spin text-primary',
                                )}
                                aria-hidden="true"
                            />
                            <span className="sr-only">
                                {isSyncing
                                    ? 'Sincronizando agenda…'
                                    : 'Sincronizar agenda'}
                            </span>
                        </Button>
                    ) : null}
                </div>
            </div>

            <div className="flex flex-col gap-2.5 sm:flex-row sm:flex-wrap sm:items-center">
                <div
                    className="flex w-full rounded-lg border border-border bg-muted/30 p-1 sm:w-auto"
                    role="group"
                    aria-label="Visualização da agenda"
                >
                    {(['day', 'week', 'month'] as CalendarView[]).map(
                        (item) => (
                            <Button
                                key={item}
                                asChild
                                size="sm"
                                variant={view === item ? 'secondary' : 'ghost'}
                                className="h-11 min-h-[44px] flex-1 px-3 text-xs font-medium sm:h-9 sm:min-h-0 sm:flex-none"
                            >
                                <Link
                                    href={calendarIndex({
                                        query: { ...filters, date, view: item },
                                    })}
                                >
                                    {item === 'day'
                                        ? 'Dia'
                                        : item === 'week'
                                          ? 'Semana'
                                          : 'Mês'}
                                </Link>
                            </Button>
                        ),
                    )}
                </div>
                <div className="flex w-full items-center gap-2 sm:w-auto">
                    <Button
                        variant="outline"
                        onClick={onFilter}
                        className="h-11 min-h-[44px] flex-1 sm:h-9 sm:min-h-0 sm:flex-none"
                    >
                        <Filter className="size-4" aria-hidden="true" />
                        Filtrar
                    </Button>
                    {canManage ? (
                        <>
                            {onCreateBlock ? (
                                <Button
                                    variant="outline"
                                    onClick={onCreateBlock}
                                    className="h-11 min-h-[44px] flex-1 sm:h-9 sm:min-h-0 sm:flex-none"
                                >
                                    <Lock
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    <span className="xs:inline hidden sm:inline">
                                        Bloqueio
                                    </span>
                                    <span className="xs:hidden sm:hidden">
                                        Bloquear
                                    </span>
                                </Button>
                            ) : null}
                            <Button
                                onClick={onCreate}
                                className="h-11 min-h-[44px] flex-1 sm:h-9 sm:min-h-0 sm:flex-none"
                            >
                                <CalendarDays
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <span>Agendar</span>
                            </Button>
                        </>
                    ) : null}
                </div>
            </div>
        </div>
    );
}
