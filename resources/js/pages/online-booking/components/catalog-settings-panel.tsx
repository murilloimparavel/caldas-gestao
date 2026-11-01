import { AlertCircle, Scissors, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { SectionCard, SelectionCard } from './booking-form-primitives';
import { bookingTokens } from './design-tokens';
import type { BookingItem } from '../types';

export function CatalogSettingsPanel({
    services,
    professionals,
    serviceIds,
    professionalIds,
}: {
    services: BookingItem[];
    professionals: BookingItem[];
    serviceIds?: string[];
    professionalIds?: string[];
}) {
    const [selectedServices, setSelectedServices] = useState(() =>
        services
            .filter(
                (item) =>
                    item.status === 'active' &&
                    (serviceIds?.includes(item.id) ??
                        item.online_booking_enabled),
            )
            .map((item) => item.id),
    );
    const [selectedProfessionals, setSelectedProfessionals] = useState(() =>
        professionals
            .filter(
                (item) =>
                    item.status === 'active' &&
                    (professionalIds?.includes(item.id) ??
                        item.online_booking_enabled),
            )
            .map((item) => item.id),
    );
    const updateSelection = (ids: string[], id: string, checked: boolean) =>
        checked ? [...ids, id] : ids.filter((selectedId) => selectedId !== id);
    const unlinked = services.filter(
        (item) =>
            selectedServices.includes(item.id) &&
            !(item.active_professional_ids ?? []).some((id) =>
                selectedProfessionals.includes(id),
            ),
    );

    return (
        <div className={bookingTokens.space.page}>
            <SectionCard
                icon={Scissors}
                title="Serviços do rascunho"
                description="Escolha os serviços e salve o rascunho. Eles aparecerão no site após a publicação, se tiverem um profissional selecionado vinculado."
            >
                {unlinked.length > 0 ? (
                    <div
                        role="status"
                        className="mb-4 flex gap-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                    >
                        <AlertCircle
                            aria-hidden="true"
                            className="mt-0.5 size-4 shrink-0"
                        />
                        <p>
                            Estes serviços não aparecerão no site sem um
                            profissional ativo selecionado e vinculado:{' '}
                            <strong>
                                {unlinked.map((item) => item.name).join(', ')}
                            </strong>
                            . Vincule-os no cadastro do profissional e selecione
                            esse profissional aqui.
                        </p>
                    </div>
                ) : null}
                <div
                    className={`grid ${bookingTokens.space.compactRow}`}
                    aria-label="Serviços disponíveis"
                >
                    {services.length > 0 ? (
                        services.map((item) => (
                            <SelectionCard
                                key={item.id}
                                item={item}
                                selected={selectedServices.includes(item.id)}
                                group="service"
                                onSelectionChange={(checked) =>
                                    setSelectedServices((ids) =>
                                        updateSelection(ids, item.id, checked),
                                    )
                                }
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
                title="Profissionais do rascunho"
                description="Selecione quem poderá receber solicitações após a publicação."
            >
                <div
                    className={`grid ${bookingTokens.space.compactRow}`}
                    aria-label="Profissionais disponíveis"
                >
                    {professionals.length > 0 ? (
                        professionals.map((item) => (
                            <SelectionCard
                                key={item.id}
                                item={item}
                                selected={selectedProfessionals.includes(
                                    item.id,
                                )}
                                group="professional"
                                onSelectionChange={(checked) =>
                                    setSelectedProfessionals((ids) =>
                                        updateSelection(ids, item.id, checked),
                                    )
                                }
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
