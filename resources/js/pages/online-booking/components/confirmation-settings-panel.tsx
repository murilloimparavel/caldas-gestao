import { AlertCircle, BellRing } from 'lucide-react';
import { SectionCard } from './booking-form-primitives';

export function ConfirmationSettingsPanel() {
    return (
        <SectionCard
            icon={BellRing}
            title="Confirmação via WhatsApp"
            description="O pedido reserva o horário e abre uma conversa para confirmação manual."
        >
            <div className="rounded-xl border border-primary/20 bg-primary/5 p-4">
                <div className="flex gap-3">
                    <AlertCircle className="mt-0.5 size-5 shrink-0 text-primary" />
                    <div className="space-y-1">
                        <p className="text-sm font-semibold">
                            Sem pagamento online
                        </p>
                        <p className="text-xs leading-5 text-muted-foreground">
                            Após o agendamento, o cliente será encaminhado ao
                            WhatsApp do profissional. Se ele não tiver número,
                            usamos o WhatsApp da unidade.
                        </p>
                    </div>
                </div>
            </div>
            <p className="rounded-xl border border-border bg-muted/20 p-4 text-sm leading-6 text-muted-foreground">
                O fluxo atual registra o pedido como agendamento e abre o
                WhatsApp do profissional. Esta tela é apenas informativa; não há
                pagamento, cancelamento ou configuração adicional nesta etapa.
            </p>
        </SectionCard>
    );
}
