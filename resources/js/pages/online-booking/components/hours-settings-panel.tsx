import { CalendarDays } from 'lucide-react';
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

export function HoursSettingsPanel({ settings }: { settings: PublicSettings }) {
    return (
        <SectionCard
            icon={CalendarDays}
            title="Horário de atendimento"
            description="A disponibilidade pública respeita a agenda e os bloqueios da equipe."
        >
            <div className={bookingTokens.space.controlGroup}>
                {weekDays.map(({ label, key, enabledByDefault }) => {
                    const hours = settings.public_hours?.[String(key)];

                    return (
                        <div
                            key={key}
                            className={`grid grid-cols-[1fr_auto_auto] items-center gap-3 ${bookingTokens.radius.control} border border-border ${bookingTokens.space.control}`}
                        >
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
                                    defaultChecked={
                                        hours?.enabled ?? enabledByDefault
                                    }
                                    className="size-4 accent-primary"
                                />
                                {label}
                            </label>
                            <Input
                                aria-label={`${label} início`}
                                type="time"
                                name={`public_hours[${key}][starts_at]`}
                                defaultValue={
                                    hours?.starts_at ??
                                    (enabledByDefault ? '08:00' : '')
                                }
                                className="h-8 w-28"
                            />
                            <Input
                                aria-label={`${label} fim`}
                                type="time"
                                name={`public_hours[${key}][ends_at]`}
                                defaultValue={
                                    hours?.ends_at ??
                                    (enabledByDefault ? '18:00' : '')
                                }
                                className="h-8 w-28"
                            />
                        </div>
                    );
                })}
            </div>
        </SectionCard>
    );
}
