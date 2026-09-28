import { Scissors, UsersRound } from 'lucide-react';
import { SectionCard, SelectionCard } from './booking-form-primitives';
import type { BookingItem } from '../types';

export function CatalogSettingsPanel({
    services,
    professionals,
}: {
    services: BookingItem[];
    professionals: BookingItem[];
}) {
    return (
        <div className="space-y-5">
            <SectionCard
                icon={Scissors}
                title="Serviços publicados"
                description="Escolha as ofertas que aparecerão no canal."
            >
                <div className="grid gap-2" aria-label="Serviços disponíveis">
                    {services.length > 0 ? (
                        services.map((item) => (
                            <SelectionCard
                                key={item.id}
                                item={item}
                                group="service"
                            />
                        ))
                    ) : (
                        <p className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                            Cadastre um serviço ativo para começar.
                        </p>
                    )}
                </div>
            </SectionCard>
            <SectionCard
                icon={UsersRound}
                title="Profissionais publicados"
                description="Selecione quem pode receber solicitações online."
            >
                <div
                    className="grid gap-2"
                    aria-label="Profissionais disponíveis"
                >
                    {professionals.length > 0 ? (
                        professionals.map((item) => (
                            <SelectionCard
                                key={item.id}
                                item={item}
                                group="professional"
                            />
                        ))
                    ) : (
                        <p className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                            Cadastre um profissional ativo para começar.
                        </p>
                    )}
                </div>
            </SectionCard>
        </div>
    );
}
