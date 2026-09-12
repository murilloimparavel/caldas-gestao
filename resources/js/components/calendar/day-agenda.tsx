import { Clock3, Lock } from 'lucide-react';
import { useRef, useState } from 'react';
import type { CSSProperties } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn, getInitials } from '@/lib/utils';
import type {
    CalendarAppointment,
    CalendarOption,
    ScheduleBlock,
} from '@/types/calendar';
import { AppointmentCard } from './appointment-card';
import {
    asInstant,
    dateKey,
    dateOnlyParts,
    formatDay,
    formatDuration,
    formatMinutes,
    formatTime,
    zonedTimeParts,
} from './date-utils';
import { SelectionPopover } from './selection-popover';
import type { DragSelection } from './selection-popover';

export function DayAgenda({
    appointments,
    date,
    onDragSelect,
    onOpen,
    onOpenBlock,
    onSlotClick,
    professionals = [],
    selectedProfessionalId,
    onSelectProfessional,
    scheduleBlocks = [],
    timeZone,
    weekDates = [],
    onSelectDate,
}: {
    appointments: CalendarAppointment[];
    date: string;
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
    selectedProfessionalId?: string;
    onSelectProfessional?: (id: string | null) => void;
    scheduleBlocks?: ScheduleBlock[];
    timeZone?: string;
    weekDates?: string[];
    onSelectDate?: (date: string) => void;
}) {
    const startHour = 7;
    const endHour = 21;
    const slotHeight = 48;
    const timelineHeight = (endHour - startHour) * 2 * slotHeight;
    const timeSlots = Array.from(
        { length: (endHour - startHour) * 2 },
        (_, index) => startHour * 60 + index * 30,
    );

    const [dragging, setDragging] = useState<DragSelection | null>(null);
    const [completedSelection, setCompletedSelection] =
        useState<DragSelection | null>(null);
    const isMouseDownRef = useRef(false);

    const getMinutesFromY = (y: number) => {
        const clampedY = Math.max(0, Math.min(y, timelineHeight - 1));
        const slot15 = Math.floor(clampedY / (slotHeight / 2));
        const minutes = startHour * 60 + slot15 * 15;

        return Math.min(minutes, endHour * 60 - 15);
    };

    const handlePointerDown = (
        clientY: number,
        currentTarget: HTMLDivElement,
    ) => {
        const rect = currentTarget.getBoundingClientRect();
        const y = clientY - rect.top;
        const minutes = getMinutesFromY(y);

        isMouseDownRef.current = true;
        setDragging({
            date,
            endMinutes: minutes,
            professionalId: selectedProfessionalId,
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

    const dayAppointments = appointments
        .filter((appointment) => {
            const matchDate = dateKey(appointment.starts_at, timeZone) === date;

            if (!matchDate) {
                return false;
            }

            if (selectedProfessionalId) {
                const profId =
                    appointment.professional_id ?? appointment.professional?.id;

                return profId === selectedProfessionalId;
            }

            return true;
        })
        .sort((left, right) => left.starts_at.localeCompare(right.starts_at));

    const dayBlocks = scheduleBlocks
        .filter((block) => {
            if (block.status === 'cancelled') {
                return false;
            }

            const matchDate = dateKey(block.starts_at, timeZone) === date;

            if (!matchDate) {
                return false;
            }

            if (selectedProfessionalId) {
                return (
                    !block.professional_id ||
                    block.professional_id === selectedProfessionalId
                );
            }

            return true;
        })
        .sort((left, right) => left.starts_at.localeCompare(right.starts_at));

    const isDragging = dragging && dragging.date === date;
    const dragMinMins = isDragging
        ? Math.min(dragging.startMinutes, dragging.endMinutes)
        : 0;
    const dragMaxMins = isDragging
        ? Math.max(dragging.startMinutes, dragging.endMinutes) + 15
        : 0;
    const dragDuration = dragMaxMins - dragMinMins;
    const dragTop = isDragging
        ? ((dragMinMins - startHour * 60) / 30) * slotHeight
        : 0;
    const dragHeight = isDragging ? (dragDuration / 30) * slotHeight : 0;

    return (
        <section
            className="surface-panel p-3.5 select-none sm:p-5"
            aria-labelledby="day-agenda-title"
        >
            {/* Seletor de dias da semana para mobile */}
            {weekDates.length > 0 && onSelectDate ? (
                <div className="mb-4 scrollbar-none overflow-x-auto pb-1 md:hidden">
                    <p className="mb-1.5 text-3xs font-semibold tracking-wider text-muted-foreground uppercase">
                        Dias da semana
                    </p>
                    <div className="flex items-center gap-1.5">
                        {weekDates.map((d) => {
                            const isSelected = d === date;

                            return (
                                <button
                                    key={d}
                                    type="button"
                                    onClick={() => onSelectDate(d)}
                                    className={cn(
                                        'flex min-h-[44px] min-w-[3.25rem] flex-1 flex-col items-center justify-center rounded-lg border px-2 py-1 text-center transition',
                                        isSelected
                                            ? 'border-primary bg-primary font-bold text-primary-foreground shadow-xs'
                                            : 'border-border bg-card text-foreground hover:bg-muted/50',
                                    )}
                                >
                                    <span className="text-2xs uppercase opacity-80">
                                        {formatDay(d, timeZone).split(' ')[0]}
                                    </span>
                                    <span className="text-xs font-semibold">
                                        {dateOnlyParts(d).day}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            ) : null}

            {/* Seletor/tabs de profissionais para mobile */}
            {professionals.length > 1 && onSelectProfessional ? (
                <div className="mb-4 scrollbar-none overflow-x-auto pb-1 md:hidden">
                    <p className="mb-1.5 text-3xs font-semibold tracking-wider text-muted-foreground uppercase">
                        Filtrar Profissional
                    </p>
                    <div className="flex items-center gap-1.5">
                        <button
                            type="button"
                            onClick={() => onSelectProfessional(null)}
                            className={cn(
                                'flex h-11 min-h-[44px] shrink-0 items-center justify-center rounded-full px-4 text-xs font-semibold transition',
                                !selectedProfessionalId
                                    ? 'bg-primary text-primary-foreground'
                                    : 'border border-border bg-card text-muted-foreground hover:bg-muted',
                            )}
                        >
                            Todos ({professionals.length})
                        </button>
                        {professionals.map((p) => {
                            const isSelected = selectedProfessionalId === p.id;

                            return (
                                <button
                                    key={p.id}
                                    type="button"
                                    onClick={() => onSelectProfessional(p.id)}
                                    className={cn(
                                        'flex h-11 min-h-[44px] shrink-0 items-center gap-2 rounded-full px-3 text-xs font-semibold transition',
                                        isSelected
                                            ? 'bg-primary text-primary-foreground'
                                            : 'border border-border bg-card text-foreground hover:bg-muted',
                                    )}
                                >
                                    <Avatar className="size-6 border border-border">
                                        {p.avatar_url ? (
                                            <AvatarImage
                                                src={p.avatar_url}
                                                alt={p.name}
                                            />
                                        ) : null}
                                        <AvatarFallback className="bg-primary/20 text-2xs">
                                            {getInitials(p.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="truncate">{p.name}</span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            ) : null}

            <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2
                        id="day-agenda-title"
                        className="text-base font-semibold capitalize"
                    >
                        {formatDay(date, timeZone)}
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Clique ou arraste nos horários da grade temporal
                    </p>
                </div>
                <Clock3
                    className="size-5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>

            {/* Grade Temporal Diária Interativa (Drag to select e clique) */}
            <div className="mb-4 overflow-x-auto rounded-lg border border-border bg-muted/10 p-2">
                <div
                    className="grid"
                    style={{
                        gridTemplateColumns: '4.5rem 1fr',
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
                    <div
                        className="relative cursor-pointer touch-none bg-[linear-gradient(to_bottom,transparent_47px,var(--border)_48px)] bg-size-[100%_48px]"
                        style={{ height: timelineHeight }}
                        onMouseDown={(e) => {
                            if (e.button !== 0) {
                                return;
                            }

                            handlePointerDown(e.clientY, e.currentTarget);
                        }}
                        onMouseMove={(e) => {
                            handlePointerMove(e.clientY, e.currentTarget);
                        }}
                        onMouseUp={() => {
                            handlePointerUp();
                        }}
                        onTouchStart={(e) => {
                            if (e.touches[0]) {
                                handlePointerDown(
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
                        {isDragging ? (
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
                            const start = asInstant(block.starts_at);
                            const end = asInstant(block.ends_at);
                            const { hour, minute } = zonedTimeParts(
                                block.starts_at,
                                timeZone,
                            );
                            const startMinutes = hour * 60 + minute;
                            const duration = Math.max(
                                15,
                                Math.round(
                                    (end.getTime() - start.getTime()) / 60000,
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
                                    onMouseDown={(e) => e.stopPropagation()}
                                    onPointerDown={(e) => e.stopPropagation()}
                                    onTouchStart={(e) => e.stopPropagation()}
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
                                    {block.professional?.name ? (
                                        <p className="truncate text-2xs text-slate-600 dark:text-slate-400">
                                            {block.professional.name}
                                        </p>
                                    ) : null}
                                </button>
                            );
                        })}

                        {dayAppointments.map((appointment) => {
                            const start = asInstant(appointment.starts_at);
                            const end = asInstant(appointment.ends_at);
                            const { hour, minute } = zonedTimeParts(
                                appointment.starts_at,
                                timeZone,
                            );
                            const startMinutes = hour * 60 + minute;
                            const duration = Math.max(
                                30,
                                Math.round(
                                    (end.getTime() - start.getTime()) / 60000,
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
                                    onMouseDown={(e) => e.stopPropagation()}
                                    onPointerDown={(e) => e.stopPropagation()}
                                    onTouchStart={(e) => e.stopPropagation()}
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
        </section>
    );
}
