import { CalendarDays, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SectionCard } from './booking-form-primitives';
import { bookingTokens } from './design-tokens';
import type { PublicSettings } from '../types';

const weekDays = [
    { label: 'Segunda-feira', key: 1, enabledByDefault: true },
    { label: 'Terça-feira', key: 2, enabledByDefault: true },
    { label: 'Quarta-feira', key: 3, enabledByDefault: true },
    { label: 'Quinta-feira', key: 4, enabledByDefault: true },
    { label: 'Sexta-feira', key: 5, enabledByDefault: true },
    { label: 'Sábado', key: 6, enabledByDefault: true },
    { label: 'Domingo', key: 0, enabledByDefault: false },
];
type Period = { starts_at: string; ends_at: string };

export function HoursSettingsPanel({ settings }: { settings: PublicSettings }) {
    const [days, setDays] = useState(() =>
        Object.fromEntries(
            weekDays.map(({ key, enabledByDefault }) => {
                const hours = settings.public_hours?.[String(key)];
                const periods = hours?.display_periods ?? [
                    {
                        starts_at:
                            hours?.starts_at ??
                            (enabledByDefault ? '08:00' : ''),
                        ends_at:
                            hours?.ends_at ?? (enabledByDefault ? '18:00' : ''),
                    },
                ];

                return [
                    key,
                    { enabled: hours?.enabled ?? enabledByDefault, periods },
                ];
            }),
        ),
    );
    const changePeriods = (key: number, periods: Period[]) =>
        setDays((current) => ({
            ...current,
            [key]: { ...current[key], periods },
        }));

    return (
        <SectionCard
            icon={CalendarDays}
            title="Horário de atendimento"
            description="Adicione os períodos de cada dia, incluindo a pausa para almoço. A disponibilidade também respeita a agenda e os bloqueios da equipe."
        >
            <div className={bookingTokens.space.controlGroup}>
                {weekDays.map(({ label, key }) => {
                    const day = days[key];
                    const valid = day.periods
                        .filter((period) => period.starts_at && period.ends_at)
                        .toSorted((a, b) =>
                            a.starts_at.localeCompare(b.starts_at),
                        );

                    return (
                        <div
                            key={key}
                            className={`space-y-3 ${bookingTokens.radius.control} border border-border ${bookingTokens.space.control}`}
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <label className="flex items-center gap-2 text-sm font-medium">
                                    <input
                                        type="hidden"
                                        name={`public_hours[${key}][enabled]`}
                                        value="0"
                                    />
                                    <input
                                        type="checkbox"
                                        name={`public_hours[${key}][enabled]`}
                                        value="1"
                                        checked={day.enabled}
                                        onChange={(event) =>
                                            setDays((current) => ({
                                                ...current,
                                                [key]: {
                                                    ...current[key],
                                                    enabled:
                                                        event.target.checked,
                                                },
                                            }))
                                        }
                                        className="size-4 accent-primary"
                                    />
                                    {label}
                                </label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    disabled={
                                        !day.enabled || day.periods.length >= 4
                                    }
                                    onClick={() =>
                                        changePeriods(key, [
                                            ...day.periods,
                                            { starts_at: '', ends_at: '' },
                                        ])
                                    }
                                    aria-label={`Adicionar período ${label}`}
                                >
                                    <Plus className="size-4" />
                                    Adicionar período
                                </Button>
                            </div>
                            <input
                                type="hidden"
                                name={`public_hours[${key}][starts_at]`}
                                value={valid[0]?.starts_at ?? ''}
                            />
                            <input
                                type="hidden"
                                name={`public_hours[${key}][ends_at]`}
                                value={valid.reduce(
                                    (latest, period) =>
                                        period.ends_at > latest
                                            ? period.ends_at
                                            : latest,
                                    '',
                                )}
                            />
                            {day.periods.map((period, index) => (
                                <div
                                    key={index}
                                    className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] items-center gap-2"
                                >
                                    {(['starts_at', 'ends_at'] as const).map(
                                        (field) => (
                                            <Input
                                                key={field}
                                                aria-label={`${label} ${field === 'starts_at' ? 'início' : 'fim'} ${index + 1}`}
                                                type="time"
                                                name={`public_hours[${key}][display_periods][${index}][${field}]`}
                                                value={period[field]}
                                                onChange={(event) =>
                                                    changePeriods(
                                                        key,
                                                        day.periods.map(
                                                            (
                                                                candidate,
                                                                candidateIndex,
                                                            ) =>
                                                                candidateIndex ===
                                                                index
                                                                    ? {
                                                                          ...candidate,
                                                                          [field]:
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : candidate,
                                                        ),
                                                    )
                                                }
                                                className="h-9 min-w-0"
                                            />
                                        ),
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={day.periods.length <= 1}
                                        onClick={() =>
                                            changePeriods(
                                                key,
                                                day.periods.filter(
                                                    (_, candidateIndex) =>
                                                        candidateIndex !==
                                                        index,
                                                ),
                                            )
                                        }
                                        aria-label={`Remover período ${index + 1} ${label}`}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    );
                })}
            </div>
        </SectionCard>
    );
}
