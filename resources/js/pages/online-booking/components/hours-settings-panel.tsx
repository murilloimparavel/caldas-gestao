import { CalendarDays } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { SectionCard } from './booking-form-primitives';
import { bookingTokens } from './design-tokens';
import type { PublicSettings } from '../types';

const weekDays = [
    'Segunda-feira',
    'Terça-feira',
    'Quarta-feira',
    'Quinta-feira',
    'Sexta-feira',
    'Sábado',
    'Domingo',
];

export function HoursSettingsPanel({ settings }: { settings: PublicSettings }) {
    return (
        <SectionCard
            icon={CalendarDays}
            title="Horário de atendimento"
            description="A disponibilidade pública respeita a agenda e os bloqueios da equipe."
        >
            <div className={bookingTokens.space.controlGroup}>
                {weekDays.map((day, index) => {
                    const hours = settings.public_hours?.[String(index)];

                    return (
                        <div
                            key={day}
                            className={`grid grid-cols-[1fr_auto_auto] items-center gap-3 ${bookingTokens.radius.control} border border-border ${bookingTokens.space.control}`}
                        >
                            <label className="flex items-center gap-2 text-sm font-medium">
                                <input
                                    type="hidden"
                                    name={`public_hours[${index}][enabled]`}
                                    value="0"
                                />
                                <input
                                    type="checkbox"
                                    name={`public_hours[${index}][enabled]`}
                                    value="1"
                                    defaultChecked={hours?.enabled ?? index < 6}
                                    className="size-4 accent-primary"
                                />
                                {day}
                            </label>
                            <Input
                                aria-label={`${day} início`}
                                type="time"
                                name={`public_hours[${index}][starts_at]`}
                                defaultValue={
                                    hours?.starts_at ??
                                    (index < 6 ? '08:00' : '')
                                }
                                className="h-8 w-28"
                            />
                            <Input
                                aria-label={`${day} fim`}
                                type="time"
                                name={`public_hours[${index}][ends_at]`}
                                defaultValue={
                                    hours?.ends_at ?? (index < 6 ? '18:00' : '')
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
