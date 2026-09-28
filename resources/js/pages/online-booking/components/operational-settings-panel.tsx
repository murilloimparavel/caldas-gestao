import { Palette } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Field, SectionCard } from './booking-form-primitives';
import { bookingTokens, bookingUi } from './design-tokens';
import type { PublicSettings } from '../types';

export function OperationalSettingsPanel({
    settings,
}: {
    settings: PublicSettings;
}) {
    return (
        <SectionCard
            icon={Palette}
            title="Experiência do canal"
            description="Defina como o público encontrará sua unidade."
        >
            <div className={bookingTokens.layout.formGrid}>
                <Field
                    label="Cor do botão fixo"
                    name="brand_color"
                    defaultValue={
                        settings.brand_color ??
                        settings.accent_color ??
                        '#5b6cff'
                    }
                    placeholder="#5b6cff"
                />
                <div className={bookingTokens.space.controlGroup}>
                    <Label htmlFor="booking_flow">Ordem do agendamento</Label>
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
                        <option value="service_first">Serviço primeiro</option>
                        <option value="professional_first">
                            Profissional primeiro
                        </option>
                    </select>
                </div>
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
