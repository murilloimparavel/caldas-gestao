import {
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    ExternalLink,
    RefreshCw,
    RotateCcw,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { firstError } from '@/components/operational';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type AutomationIssueKind =
    'blocked' | 'category' | 'conflict' | 'creation' | 'stale' | 'sync';

type AutomationFeedbackProps = {
    actionLabel?: string;
    children?: ReactNode;
    className?: string;
    kind: AutomationIssueKind;
    onAction?: () => void;
    onReload?: () => void;
    title?: string;
};

const issueCopy: Record<
    AutomationIssueKind,
    { description: string; icon: typeof AlertCircle; title: string }
> = {
    blocked: {
        description:
            'A comanda precisa ser revisada antes de continuar porque já possui itens manuais, fechamento ou pagamento.',
        icon: AlertTriangle,
        title: 'A comanda ficou para revisão',
    },
    category: {
        description:
            'A categoria padrão está ausente, inativa ou não aceita serviços. O agendamento foi preservado.',
        icon: AlertCircle,
        title: 'Automação de comanda indisponível',
    },
    conflict: {
        description:
            'O horário mudou enquanto o formulário estava aberto. Atualize a agenda e confira os dados antes de tentar novamente.',
        icon: AlertTriangle,
        title: 'Conflito ou alteração simultânea',
    },
    creation: {
        description:
            'O agendamento não conseguiu concluir a criação automática da comanda. Nenhum dado deve ser repetido manualmente sem conferir primeiro.',
        icon: AlertCircle,
        title: 'Não foi possível criar a comanda automaticamente',
    },
    stale: {
        description:
            'Este agendamento foi atualizado em outra tela. Recarregue os dados para evitar sobrescrever alterações recentes.',
        icon: RefreshCw,
        title: 'Dados desatualizados',
    },
    sync: {
        description:
            'A comanda automática não pode mais ser sincronizada neste estado. Confira a comanda e faça qualquer ajuste diretamente nela.',
        icon: RotateCcw,
        title: 'Sincronização pausada',
    },
};

export function AutomationFeedback({
    actionLabel,
    children,
    className,
    kind,
    onAction,
    onReload,
    title,
}: AutomationFeedbackProps) {
    const copy = issueCopy[kind];
    const Icon = copy.icon;
    const isCritical = kind === 'creation' || kind === 'category';

    return (
        <Alert
            aria-live={isCritical ? 'assertive' : 'polite'}
            className={cn(
                'items-start gap-3 border-amber-400/50 bg-amber-50/80 text-amber-950 dark:border-amber-500/40 dark:bg-amber-950/30 dark:text-amber-100',
                isCritical &&
                    'border-destructive/35 bg-destructive/5 text-destructive dark:bg-destructive/10',
                className,
            )}
        >
            <Icon aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
            <div className="min-w-0 space-y-2">
                <AlertTitle className="text-sm font-semibold">
                    {title ?? copy.title}
                </AlertTitle>
                <AlertDescription className="text-current/80">
                    <p>{children ?? copy.description}</p>
                    {onAction || onReload ? (
                        <div className="mt-2 flex flex-wrap gap-2">
                            {onAction ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={onAction}
                                    className="border-current/25 bg-background/60 text-current hover:bg-background"
                                >
                                    {kind === 'creation' ? (
                                        <RotateCcw aria-hidden="true" />
                                    ) : (
                                        <ExternalLink aria-hidden="true" />
                                    )}
                                    {actionLabel ??
                                        (kind === 'creation'
                                            ? 'Tentar novamente'
                                            : 'Revisar comanda')}
                                </Button>
                            ) : null}
                            {onReload ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={onReload}
                                    className="text-current hover:bg-background/70"
                                >
                                    <RefreshCw aria-hidden="true" />
                                    Recarregar dados
                                </Button>
                            ) : null}
                        </div>
                    ) : null}
                </AlertDescription>
            </div>
        </Alert>
    );
}

export function automationIssueFromErrors(
    errors: Record<string, unknown>,
): AutomationIssueKind | null {
    const messages = Object.values(errors)
        .map(firstError)
        .filter((message): message is string => Boolean(message))
        .join(' ')
        .toLowerCase();

    if (!messages) {
        return null;
    }

    if (
        messages.includes('modified concurrently') ||
        messages.includes('lock_version') ||
        messages.includes('versão') ||
        messages.includes('atualizado em outra')
    ) {
        return 'stale';
    }

    if (
        messages.includes('conflito') ||
        messages.includes('disponibilidade') ||
        messages.includes('horário')
    ) {
        return 'conflict';
    }

    if (
        messages.includes('categoria padrão') ||
        (messages.includes('categoria') &&
            (messages.includes('inativa') || messages.includes('serviço')))
    ) {
        return 'category';
    }

    if (
        messages.includes('manual') ||
        messages.includes('fechamento') ||
        messages.includes('pagamento') ||
        messages.includes('revis')
    ) {
        return 'blocked';
    }

    if (messages.includes('sincron') || messages.includes('indisponível')) {
        return 'sync';
    }

    if (messages.includes('comanda') || messages.includes('agendamento')) {
        return 'creation';
    }

    return null;
}

export function AutomationSuccess({ children }: { children: ReactNode }) {
    return (
        <div
            aria-live="polite"
            className="flex items-start gap-2.5 rounded-lg border border-emerald-500/30 bg-emerald-50/70 px-3 py-2.5 text-xs text-emerald-900 dark:bg-emerald-950/25 dark:text-emerald-200"
        >
            <CheckCircle2
                aria-hidden="true"
                className="mt-0.5 size-4 shrink-0"
            />
            <span>{children}</span>
        </div>
    );
}
