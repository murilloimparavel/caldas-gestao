import { CalendarClock } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Field, SectionCard } from './booking-form-primitives';
import { bookingTokens, bookingUi } from './design-tokens';
import type { PublicSettings } from '../types';

export function OperationalSettingsPanel({
    settings,
    templateKey,
}: {
    settings: PublicSettings;
    templateKey: string;
}) {
    return (
        <SectionCard
            icon={CalendarClock}
            title="Regras de agendamento"
            description={
                templateKey === 'atelier-barber'
                    ? 'Defina a antecedência mínima para solicitações de horário.'
                    : 'Defina a ordem da escolha e a antecedência mínima.'
            }
        >
            <div className={bookingTokens.layout.formGrid}>
                {templateKey === 'atelier-barber' ? (
                    <>
                        <input
                            type="hidden"
                            name="booking_flow"
                            value={
                                settings.booking_flow ??
                                settings.flow ??
                                'service_first'
                            }
                        />
                        <p className="rounded-lg border border-border bg-muted/30 p-3 text-sm text-muted-foreground sm:col-span-2">
                            O fluxo Atelier Barber segue suas quatro etapas de
                            referência; a ordem da escolha não é configurável
                            neste template.
                        </p>
                    </>
                ) : (
                    <div className={bookingTokens.space.controlGroup}>
                        <Label htmlFor="booking_flow">
                            Ordem do agendamento
                        </Label>
                        <select
                            id="booking_flow"
                            name="booking_flow"
                            defaultValue={
                                settings.booking_flow ??
                                settings.flow ??
                                'service_first'
                            }
                            className={bookingUi.select}
                        >
                            <option value="service_first">
                                Serviço primeiro
                            </option>
                            <option value="professional_first">
                                Profissional primeiro
                            </option>
                        </select>
                    </div>
                )}
                <Field
                    label="Antecedência mínima (minutos)"
                    name="minimum_notice_minutes"
                    type="number"
                    defaultValue={settings.minimum_notice_minutes?.toString()}
                    placeholder="30"
                />
            </div>
        </SectionCard>
    );
}
