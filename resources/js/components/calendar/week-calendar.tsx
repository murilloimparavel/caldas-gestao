import { Lock } from 'lucide-react';
import { useRef, useState } from 'react';
import type { CSSProperties } from 'react';
import type {
    CalendarAppointment,
    CalendarOption,
    CalendarRange,
    ScheduleBlock,
} from '@/types/calendar';
import { AppointmentCard } from './appointment-card';
import {
    asInstant,
    dateKey,
    dayDates,
    formatDay,
    formatDuration,
    formatMinutes,
    formatTime,
    zonedTimeParts,
} from './date-utils';
import { ProfessionalAvatarHeader } from './professional-avatar-header';
import { SelectionPopover } from './selection-popover';
import type { DragSelection } from './selection-popover';

export function WeekCalendar({
    appointments,
    onDragSelect,
    onOpen,
    onOpenBlock,
    onSlotClick,
    professionals = [],
    range,
    scheduleBlocks = [],
    timeZone,
}: {
    appointments: CalendarAppointment[];
    onDragSelect?: (
        action: 'appointment' | 'block',
        selection: DragSelection,
    ) => void;
    onOpen: (appointment: CalendarAppointment) => void;
    onOpenBlock?: (block: ScheduleBlock) => void;
    onSlotClick?: (params: {
        date: string;
        time: string;
        professionalId?: string;
    }) => void;
    professionals?: CalendarOption[];
    range: CalendarRange;
    scheduleBlocks?: ScheduleBlock[];
    timeZone?: string;
}) {
    const dates = dayDates(range.start, 'week');
    const startHour = 7;
    const endHour = 21;
    const slotHeight = 48; // Mínimo 48px para excelente usabilidade touch (>= 44px)
    const timelineHeight = (endHour - startHour) * 2 * slotHeight;
    const timeSlots = Array.from(
        { length: (endHour - startHour) * 2 },
        (_, index) => startHour * 60 + index * 30,
    );

    const [dragging, setDragging] = useState<DragSelection | null>(null);
    const [completedSelection, setCompletedSelection] =
        useState<DragSelection | null>(null);
    const isMouseDownRef = useRef(false);

    // Se houver profissionais selecionados/disponíveis e for visualização por profissionais (ou exibição na semana)
    const showProfessionalColumns =
        professionals.length > 0 && dates.length === 1;
    const columns = showProfessionalColumns
        ? professionals.map((p) => ({
              id: p.id,
              title: p.name,
              professional: p,
              date: dates[0],
          }))
        : dates.map((d) => ({
              id: d,
              title: formatDay(d, timeZone),
              professional: null,
              date: d,
          }));

    const getMinutesFromY = (y: number) => {
        const clampedY = Math.max(0, Math.min(y, timelineHeight - 1));
        const slot15 = Math.floor(clampedY / (slotHeight / 2));
        const minutes = startHour * 60 + slot15 * 15;

        return Math.min(minutes, endHour * 60 - 15);
    };

    const handlePointerDown = (
        colDate: string,
        profId: string | undefined,
        clientY: number,
        currentTarget: HTMLDivElement,
    ) => {
        const rect = currentTarget.getBoundingClientRect();
        const y = clientY - rect.top;
        const minutes = getMinutesFromY(y);

        isMouseDownRef.current = true;
        setDragging({
            date: colDate,
            endMinutes: minutes,
            professionalId: profId,
            startMinutes: minutes,
        });
    };

    const handlePointerMove = (
        clientY: number,
        currentTarget: HTMLDivElement,
    ) => {
        if (!isMouseDownRef.current || !dragging) {
            return;
        }

        const rect = currentTarget.getBoundingClientRect();
        const y = clientY - rect.top;
        const minutes = getMinutesFromY(y);

        if (minutes !== dragging.endMinutes) {
            setDragging((prev) =>
                prev ? { ...prev, endMinutes: minutes } : null,
            );
        }
    };

    const handlePointerUp = () => {
        if (!isMouseDownRef.current || !dragging) {
            return;
        }

        isMouseDownRef.current = false;

        if (Math.abs(dragging.endMinutes - dragging.startMinutes) === 0) {
            const hours = Math.floor(dragging.startMinutes / 60);
            const mins = dragging.startMinutes % 60;
            const timeStr = `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;

            onSlotClick?.({
                date: dragging.date,
                professionalId: dragging.professionalId,
                time: timeStr,
            });
            setDragging(null);
        } else {
            setCompletedSelection(dragging);
            setDragging(null);
        }
    };

    return (
        <div className="surface-panel overflow-hidden select-none">
            <div className="overflow-x-auto">
                <div
                    style={{
                        minWidth: `${Math.max(920, columns.length * 140 + 80)}px`,
                    }}
                >
                    <div
                        className="grid border-b border-border bg-muted/25"
                        style={{
                            gridTemplateColumns: `4.5rem repeat(${columns.length}, minmax(8rem, 1fr))`,
                        }}
                    >
                        <div
                            aria-hidden="true"
                            className="border-r border-border"
                        />
                        {columns.map((col) => (
                            <div
                                key={col.id}
                                className="border-r border-border px-2 py-2 last:border-r-0"
                            >
                                {col.professional ? (
                                    <ProfessionalAvatarHeader
                                        professional={col.professional}
                                        subtitle={formatDay(col.date, timeZone)}
                                    />
                                ) : (
                                    <div className="px-1 py-1">
                                        <p className="text-xs font-semibold text-muted-foreground capitalize">
                                            {col.title}
                                        </p>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                    <div
                        className="grid"
                        style={{
                            gridTemplateColumns: `4.5rem repeat(${columns.length}, minmax(8rem, 1fr))`,
                        }}
                    >
                        <div
                            className="relative border-r border-border"
                            style={{ height: timelineHeight }}
                        >
                            {timeSlots.map((minutes) => (
                                <span
                                    key={minutes}
                                    className="absolute right-2 -translate-y-1/2 text-2xs font-medium text-muted-foreground"
                                    style={{
                                        top:
                                            ((minutes - startHour * 60) / 30) *
                                            slotHeight,
                                    }}
                                >
                                    {String(Math.floor(minutes / 60)).padStart(
                                        2,
                                        '0',
                                    )}
                                    :{String(minutes % 60).padStart(2, '0')}
                                </span>
                            ))}
                        </div>
                        {columns.map((col) => {
                            const dayAppointments = appointments.filter(
                                (appointment) => {
                                    const matchDate =
                                        dateKey(
                                            appointment.starts_at,
                                            timeZone,
                                        ) === col.date;

                                    if (!matchDate) {
                                        return false;
                                    }

                                    if (col.professional) {
                                        const profId =
                                            appointment.professional_id ??
                                            appointment.professional?.id;

                                        return profId === col.professional.id;
                                    }

                                    return true;
                                },
                            );

                            const dayBlocks = scheduleBlocks.filter((block) => {
                                if (block.status === 'cancelled') {
                                    return false;
                                }

                                const matchDate =
                                    dateKey(block.starts_at, timeZone) ===
                                    col.date;

                                if (!matchDate) {
                                    return false;
                                }

                                if (col.professional) {
                                    return (
                                        !block.professional_id ||
                                        block.professional_id ===
                                            col.professional.id
                                    );
                                }

                                return true;
                            });

                            const isCurrentColDragging =
                                dragging &&
                                dragging.date === col.date &&
                                dragging.professionalId ===
                                    col.professional?.id;

                            const dragMinMins = isCurrentColDragging
                                ? Math.min(
                                      dragging.startMinutes,
                                      dragging.endMinutes,
                                  )
                                : 0;
                            const dragMaxMins = isCurrentColDragging
                                ? Math.max(
                                      dragging.startMinutes,
                                      dragging.endMinutes,
                                  ) + 15
                                : 0;
                            const dragDuration = dragMaxMins - dragMinMins;
                            const dragTop = isCurrentColDragging
                                ? ((dragMinMins - startHour * 60) / 30) *
                                  slotHeight
                                : 0;
                            const dragHeight = isCurrentColDragging
                                ? (dragDuration / 30) * slotHeight
                                : 0;

                            return (
                                <div
                                    key={col.id}
                                    className="relative cursor-pointer touch-none border-r border-border bg-[linear-gradient(to_bottom,transparent_47px,var(--border)_48px)] bg-size-[100%_48px] last:border-r-0"
                                    style={{ height: timelineHeight }}
                                    onMouseDown={(e) => {
                                        if (e.button !== 0) {
                                            return;
                                        }

                                        handlePointerDown(
                                            col.date,
                                            col.professional?.id,
                                            e.clientY,
                                            e.currentTarget,
                                        );
                                    }}
                                    onMouseMove={(e) => {
                                        handlePointerMove(
                                            e.clientY,
                                            e.currentTarget,
                                        );
                                    }}
                                    onMouseUp={() => {
                                        handlePointerUp();
                                    }}
                                    onTouchStart={(e) => {
                                        if (e.touches[0]) {
                                            handlePointerDown(
                                                col.date,
                                                col.professional?.id,
                                                e.touches[0].clientY,
                                                e.currentTarget,
                                            );
                                        }
                                    }}
                                    onTouchMove={(e) => {
                                        if (e.touches[0]) {
                                            handlePointerMove(
                                                e.touches[0].clientY,
                                                e.currentTarget,
                                            );
                                        }
                                    }}
                                    onTouchEnd={() => {
                                        handlePointerUp();
                                    }}
                                >
                                    {/* Overlay Visual durante o arraste */}
                                    {isCurrentColDragging ? (
                                        <div
                                            className="pointer-events-none absolute inset-x-1 z-20 flex flex-col justify-between rounded-md border-2 border-primary/50 bg-primary/20 p-1.5 shadow-sm"
                                            style={{
                                                height: `${dragHeight}px`,
                                                top: `${dragTop}px`,
                                            }}
                                        >
                                            <span className="text-3xs font-bold text-primary drop-shadow-xs dark:text-primary-foreground">
                                                {formatMinutes(dragMinMins)} -{' '}
                                                {formatMinutes(dragMaxMins)} •{' '}
                                                {formatDuration(dragDuration)}
                                            </span>
                                        </div>
                                    ) : null}

                                    {dayBlocks.map((block) => {
                                        const start = asInstant(
                                            block.starts_at,
                                        );
                                        const end = asInstant(block.ends_at);
                                        const { hour, minute } = zonedTimeParts(
                                            block.starts_at,
                                            timeZone,
                                        );
                                        const startMinutes = hour * 60 + minute;
                                        const duration = Math.max(
                                            15,
                                            Math.round(
                                                (end.getTime() -
                                                    start.getTime()) /
                                                    60000,
                                            ),
                                        );
                                        const style: CSSProperties = {
                                            height: `${Math.max(32, (duration / 30) * slotHeight - 4)}px`,
                                            top: `${((startMinutes - startHour * 60) / 30) * slotHeight + 2}px`,
                                        };

                                        return (
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
                                                className="group absolute inset-x-1 z-10 flex min-h-[44px] flex-col justify-between overflow-hidden rounded-md border border-slate-300 bg-slate-100/95 p-1.5 text-left text-slate-800 shadow-2xs backdrop-blur-xs transition hover:border-slate-400 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-900/95 dark:text-slate-200 dark:hover:bg-slate-800"
                                                style={style}
                                                title={`Ocupado: ${block.reason || 'Horário bloqueado'} (${formatTime(block.starts_at, timeZone)} - ${formatTime(block.ends_at, timeZone)})`}
                                            >
                                                <div className="flex items-center justify-between gap-1">
                                                    <span className="flex items-center gap-1 text-2xs font-semibold text-slate-700 dark:text-slate-300">
                                                        <Lock
                                                            className="size-3 shrink-0 text-slate-500 dark:text-slate-400"
                                                            aria-hidden="true"
                                                        />
                                                        {formatTime(
                                                            block.starts_at,
                                                            timeZone,
                                                        )}
                                                        –
                                                        {formatTime(
                                                            block.ends_at,
                                                            timeZone,
                                                        )}
                                                    </span>
                                                    <span className="py-0.2 rounded bg-slate-200 px-1 text-[9px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                        Ocupado
                                                    </span>
                                                </div>
                                                <p className="truncate text-3xs font-medium text-slate-900 dark:text-slate-100">
                                                    {block.reason || 'Ocupado'}
                                                </p>
                                                {block.professional?.name &&
                                                !col.professional ? (
                                                    <p className="truncate text-2xs text-slate-600 dark:text-slate-400">
                                                        {
                                                            block.professional
                                                                .name
                                                        }
                                                    </p>
                                                ) : null}
                                            </button>
                                        );
                                    })}
                                    {dayAppointments.map((appointment) => {
                                        const start = asInstant(
                                            appointment.starts_at,
                                        );
                                        const end = asInstant(
                                            appointment.ends_at,
                                        );
                                        const { hour, minute } = zonedTimeParts(
                                            appointment.starts_at,
                                            timeZone,
                                        );
                                        const startMinutes = hour * 60 + minute;
                                        const duration = Math.max(
                                            30,
                                            Math.round(
                                                (end.getTime() -
                                                    start.getTime()) /
                                                    60000,
                                            ),
                                        );
                                        const style: CSSProperties = {
                                            height: `${Math.max(44, (duration / 30) * slotHeight - 6)}px`,
                                            top: `${((startMinutes - startHour * 60) / 30) * slotHeight + 3}px`,
                                        };

                                        return (
                                            <div
                                                key={appointment.id}
                                                className="absolute inset-x-1 z-10 min-h-[44px]"
                                                style={style}
                                                onMouseDown={(e) =>
                                                    e.stopPropagation()
                                                }
                                                onPointerDown={(e) =>
                                                    e.stopPropagation()
                                                }
                                                onTouchStart={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <AppointmentCard
                                                    appointment={appointment}
                                                    compact
                                                    onOpen={onOpen}
                                                    timeZone={timeZone}
                                                />
                                            </div>
                                        );
                                    })}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            {completedSelection ? (
                <SelectionPopover
                    selection={completedSelection}
                    onCancel={() => setCompletedSelection(null)}
                    onSelectNew={(sel) => {
                        onDragSelect?.('appointment', sel);
                        setCompletedSelection(null);
                    }}
                    onSelectBlock={(sel) => {
                        onDragSelect?.('block', sel);
                        setCompletedSelection(null);
                    }}
                />
            ) : null}
        </div>
    );
}
