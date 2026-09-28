import {
    AlertCircle,
    ClipboardCheck,
    ExternalLink,
    History,
    RotateCcw,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ReadinessRow } from './booking-form-primitives';
import type { PublicationHistoryItem, Readiness } from '../types';

type Props = {
    readiness: Readiness;
    activeServicesCount: number;
    activeProfessionalsCount: number;
    hasPendingChanges: boolean;
    draftDiff: string[];
    publicationHistory: PublicationHistoryItem[];
    onRestore: (publicationId: string) => void;
};

export function PublicationSettingsPanel({
    readiness,
    activeServicesCount,
    activeProfessionalsCount,
    hasPendingChanges,
    draftDiff,
    publicationHistory,
    onRestore,
}: Props) {
    return (
        <div className="space-y-5">
            {hasPendingChanges && draftDiff.length > 0 ? (
                <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-100">
                    <div className="flex items-start gap-3">
                        <AlertCircle
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div>
                            <p className="text-sm font-semibold">
                                Alterações aguardando publicação
                            </p>
                            <p className="mt-1 text-xs opacity-80">
                                A versão pública continua intacta até você
                                publicar.
                            </p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {draftDiff.map((label) => (
                                    <Badge
                                        key={label}
                                        variant="outline"
                                        className="border-current/30"
                                    >
                                        {label}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            ) : null}

            <Card className="overflow-hidden">
                <CardHeader className="bg-muted/40">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <ClipboardCheck
                            aria-hidden="true"
                            className="size-4 text-primary"
                        />
                        Checklist de publicação
                    </CardTitle>
                    <CardDescription>
                        Resolva os itens abaixo para liberar o link público.
                    </CardDescription>
                </CardHeader>
                <CardContent className="pt-5">
                    <ul className="space-y-3">
                        <ReadinessRow
                            label="Unidade habilitada"
                            ready={readiness.unit_enabled}
                        />
                        <ReadinessRow
                            label={`Serviço ativo selecionado (${activeServicesCount})`}
                            ready={readiness.has_active_service}
                        />
                        <ReadinessRow
                            label={`Profissional ativo selecionado (${activeProfessionalsCount})`}
                            ready={readiness.has_active_professional}
                        />
                        <ReadinessRow
                            label="Existe serviço e profissional compatíveis"
                            ready={readiness.has_service_professional_pair}
                        />
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="border-b border-border/60 bg-muted/20">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <History
                            aria-hidden="true"
                            className="size-4 text-primary"
                        />
                        Histórico de publicações
                    </CardTitle>
                    <CardDescription>
                        Cada versão é imutável. Restaurar cria um novo rascunho
                        sem alterar o histórico.
                    </CardDescription>
                </CardHeader>
                {publicationHistory.length > 0 ? (
                    <CardContent className="divide-y divide-border/60 p-0">
                        {publicationHistory.map((item) => (
                            <div
                                key={item.id}
                                className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="flex items-start gap-3">
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                        v{item.version}
                                    </span>
                                    <div>
                                        <p className="text-sm font-medium">
                                            Publicada em{' '}
                                            {item.published_at
                                                ? new Date(
                                                      item.published_at,
                                                  ).toLocaleString('pt-BR')
                                                : 'data indisponível'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Revisão {item.source_revision}
                                            {item.published_by?.name
                                                ? ` · ${item.published_by.name}`
                                                : ''}
                                            {item.superseded_at
                                                ? ' · substituída'
                                                : ' · ativa'}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {item.preview_url ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={item.preview_url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <ExternalLink aria-hidden="true" />
                                                Visualizar
                                            </a>
                                        </Button>
                                    ) : null}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => onRestore(item.id)}
                                    >
                                        <RotateCcw aria-hidden="true" />
                                        Restaurar como rascunho
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                ) : (
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            Ainda não há versões publicadas para esta unidade.
                        </p>
                    </CardContent>
                )}
            </Card>
        </div>
    );
}
