import { Lock } from 'lucide-react';
import { useRef, useState } from 'react';
import type { KeyboardEvent as ReactKeyboardEvent } from 'react';
import type {
    CalendarAppointment,
    CalendarRange,
    ScheduleBlock,
} from '@/types/calendar';
import { AppointmentCard } from './appointment-card';
import {
    addDays,
    dateKey,
    dateOnlyParts,
    formatDay,
} from './date-utils';

export function MonthAgenda({
    appointments,
    onOpen,
    onOpenBlock,
    range,
    scheduleBlocks = [],
    timeZone,
}: {
    appointments: CalendarAppointment[];
    onOpen: (appointment: CalendarAppointment) => void;
    onOpenBlock?: (block: ScheduleBlock) => void;
    range: CalendarRange;
    scheduleBlocks?: ScheduleBlock[];
    timeZone?: string;
}) {
    const [activeCellIndex, setActiveCellIndex] = useState(0);
    const cellRefs = useRef<Record<string, HTMLDivElement | null>>({});
    const { month, year } = dateOnlyParts(range.start);
    const monthStart = `${year}-${String(month).padStart(2, '0')}-01`;
    const monthStartWeekday = new Date(
        Date.UTC(year, month - 1, 1),
    ).getUTCDay();
    const monthEnd = new Date(Date.UTC(year, month, 0)).getUTCDate();
    const dayCount = Math.ceil((monthStartWeekday + monthEnd) / 7) * 7;
    const dates = Array.from({ length: dayCount }, (_, index) =>
        addDays(monthStart, index - monthStartWeekday),
    );
    const rows = Array.from({ length: dates.length / 7 }, (_, rowIndex) =>
        dates.slice(rowIndex * 7, rowIndex * 7 + 7),
    );

    const focusCell = (index: number) => {
        const nextIndex = Math.max(0, Math.min(index, dates.length - 1));

        setActiveCellIndex(nextIndex);
        cellRefs.current[dates[nextIndex]]?.focus();
    };

    const handleCellKeyDown = (
        event: ReactKeyboardEvent<HTMLDivElement>,
        index: number,
        dayAppointments: CalendarAppointment[],
        dayBlocks: ScheduleBlock[],
    ) => {
        if (event.target !== event.currentTarget) {
            return;
        }

        const rowStart = Math.floor(index / 7) * 7;
        const rowEnd = Math.min(rowStart + 6, dates.length - 1);
        let nextIndex: number | null = null;

        switch (event.key) {
            case 'ArrowLeft':
                nextIndex = index - 1;
                break;
            case 'ArrowRight':
                nextIndex = index + 1;
                break;
            case 'ArrowUp':
                nextIndex = index - 7;
                break;
            case 'ArrowDown':
                nextIndex = index + 7;
                break;
            case 'Home':
                nextIndex = rowStart;
                break;
            case 'End':
                nextIndex = rowEnd;
                break;
            case 'Enter':
            case ' ':
                event.preventDefault();

                if (dayAppointments[0]) {
                    onOpen(dayAppointments[0]);
                } else if (dayBlocks[0]) {
                    onOpenBlock?.(dayBlocks[0]);
                }

                return;
            default:
                return;
        }

        if (nextIndex === null || nextIndex < 0 || nextIndex >= dates.length) {
            return;
        }

        event.preventDefault();
        focusCell(nextIndex);
    };

    return (
        <section
            className="surface-panel overflow-hidden"
            aria-label="Calendário mensal"
            role="grid"
        >
            <div
                role="row"
                className="grid grid-cols-7 border-b border-border bg-muted/25 text-center text-xs font-semibold text-muted-foreground"
            >
                {['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'].map(
                    (day) => (
                        <div
                            key={day}
                            role="columnheader"
                            className="border-r border-border px-2 py-3 last:border-r-0"
                        >
                            {day}
                        </div>
                    ),
                )}
            </div>
            <div role="rowgroup">
                {rows.map((rowDates, rowIndex) => (
                    <div
                        key={`week-${rowIndex}`}
                        role="row"
                        className="grid grid-cols-7"
                    >
                        {rowDates.map((date, dateIndex) => {
                            const index = rowIndex * 7 + dateIndex;
                            const dayAppointments = appointments.filter(
                                (appointment) =>
                                    dateKey(appointment.starts_at, timeZone) ===
                                    date,
                            );
                            const dayBlocks = scheduleBlocks.filter(
                                (block) =>
                                    block.status !== 'cancelled' &&
                                    dateKey(block.starts_at, timeZone) === date,
                            );

                            return (
                                <div
                                    key={date}
                                    role="gridcell"
                                    ref={(element) => {
                                        cellRefs.current[date] = element;
                                    }}
                                    tabIndex={
                                        activeCellIndex === index ? 0 : -1
                                    }
                                    aria-label={`${formatDay(date, timeZone)}: ${dayAppointments.length} agendamento${dayAppointments.length === 1 ? '' : 's'} e ${dayBlocks.length} bloqueio${dayBlocks.length === 1 ? '' : 's'}`}
                                    onFocus={() => setActiveCellIndex(index)}
                                    onKeyDown={(event) =>
                                        handleCellKeyDown(
                                            event,
                                            index,
                                            dayAppointments,
                                            dayBlocks,
                                        )
                                    }
                                    className="min-h-28 border-r border-b border-border p-2 outline-none last:border-r-0 focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset sm:min-h-32"
                                >
                                    <p className="text-xs font-semibold text-muted-foreground">
                                        {dateOnlyParts(date).day}
                                    </p>
                                    <div className="mt-2 grid gap-1">
                                        {dayBlocks.slice(0, 2).map((block) => (
                                            <button
                                                key={block.id}
                                                type="button"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    onOpenBlock?.(block);
                                                }}
                                                onMouseDown={(e) =>
                                                    e.stopPropagation()
                                                }
                                                onPointerDown={(e) =>
                                                    e.stopPropagation()
                                                }
                                                onTouchStart={(e) =>
                                                    e.stopPropagation()
                                                }
                                                className="flex w-full items-center gap-1 rounded border border-dashed border-amber-500/50 bg-amber-50/80 px-1.5 py-0.5 text-left text-2xs font-medium text-amber-900 transition hover:bg-amber-100 dark:border-amber-700/60 dark:bg-amber-950/60 dark:text-amber-200"
                                            >
                                                <Lock
                                                    className="size-2.5 shrink-0 text-amber-700 dark:text-amber-400"
                                                    aria-hidden="true"
                                                />
                                                <span className="truncate">
                                                    {block.reason || 'Bloqueio'}
                                                </span>
                                            </button>
                                        ))}
                                        {dayAppointments
                                            .slice(0, 3)
                                            .map((appointment) => (
                                                <AppointmentCard
                                                    key={appointment.id}
                                                    appointment={appointment}
                                                    compact
                                                    onOpen={onOpen}
                                                    timeZone={timeZone}
                                                />
                                            ))}
                                        {dayAppointments.length > 3 ? (
                                            <p className="text-2xs text-muted-foreground">
                                                +{dayAppointments.length - 3}{' '}
                                                outros
                                            </p>
                                        ) : null}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ))}
            </div>
        </section>
    );
}
