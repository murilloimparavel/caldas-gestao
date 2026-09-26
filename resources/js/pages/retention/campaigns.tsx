import { Form, Head, router, useHttp, usePage } from '@inertiajs/react';
import {
    Check,
    CheckCheck,
    ChevronDown,
    Megaphone,
    MessageCircle,
    Play,
    RefreshCw,
    Send,
    ShieldCheck,
    Sparkles,
    Users,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import {
    createIdempotencyKey,
    EmptyState,
    FormActions,
    FormErrorSummary,
    FormField,
    PageCanvas,
    Pagination,
    ResourceHeader,
} from '@/components/operational';
import type { Paginated } from '@/components/operational';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import campaignsRoutes from '@/routes/retention/campaigns';
import type { SharedPageProps } from '@/types';

type CampaignStatus = 'draft' | 'active' | 'paused' | 'completed';
type CampaignChannel = 'email' | 'sms' | 'whatsapp' | 'phone';

type RetentionCampaign = {
    audience_snapshot_at: string | null;
    channel: CampaignChannel;
    created_at: string;
    deliveries_count: number;
    ends_at: string | null;
    id: string;
    message: string;
    name: string;
    purpose: string;
    recipients_count: number;
    segment_definition: {
        inactive_days?: number;
        retention_status?: 'none' | 'at_risk' | 'reactivated';
    } | null;
    starts_at: string | null;
    status: CampaignStatus;
    subject: string | null;
};

type Props = {
    campaigns: Paginated<RetentionCampaign>;
};

type OperationResponse = {
    blocked?: number;
    dry_run?: boolean;
    existing?: number | boolean;
    queued?: number;
    selected?: number;
    sent?: number;
};

const statusLabel: Record<CampaignStatus, string> = {
    draft: 'Rascunho',
    active: 'Ativa',
    paused: 'Pausada',
    completed: 'Concluída',
};

const channelLabel: Record<CampaignChannel, string> = {
    email: 'E-mail',
    sms: 'SMS',
    whatsapp: 'WhatsApp',
    phone: 'Telefone',
};

const statusTone: Record<CampaignStatus, string> = {
    draft: 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300',
    active: 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
    paused: 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
    completed:
        'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300',
};

function formatDate(value: string | null): string {
    if (!value) {
        return 'Ainda não definido';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(
              date,
          );
}

function StatusBadge({ status }: { status: CampaignStatus }) {
    return (
        <Badge
            variant="outline"
            className={`rounded-full ${statusTone[status]}`}
        >
            <span
                aria-hidden="true"
                className="mr-1.5 size-1.5 rounded-full bg-current"
            />
            {statusLabel[status]}
        </Badge>
    );
}

function SegmentSummary({ campaign }: { campaign: RetentionCampaign }) {
    const definition = campaign.segment_definition;
    const parts: string[] = [];

    if (definition?.inactive_days) {
        parts.push(`${definition.inactive_days}+ dias sem atividade`);
    }

    if (definition?.retention_status) {
        parts.push(`status: ${definition.retention_status.replace('_', ' ')}`);
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {parts.length > 0 ? (
                parts.map((part) => (
                    <Badge
                        key={part}
                        variant="secondary"
                        className="font-normal"
                    >
                        {part}
                    </Badge>
                ))
            ) : (
                <span className="text-xs text-muted-foreground">
                    Todos os clientes elegíveis
                </span>
            )}
        </div>
    );
}

export default function RetentionCampaigns({ campaigns }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [statusFilter, setStatusFilter] = useState<'all' | CampaignStatus>(
        'all',
    );
    const [feedback, setFeedback] = useState<string | null>(null);
    const [processingAction, setProcessingAction] = useState<string | null>(
        null,
    );
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('retention.manage');
    const initialMessage =
        'Olá {cliente}, sentimos sua falta aqui na {unidade}! Agende seu próximo horário e venha cuidar do visual: {link_agendamento}';
    const [messageText, setMessageText] = useState(initialMessage);
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    const insertTag = (tag: string): void => {
        const el = textareaRef.current;

        if (!el) {
            setMessageText((prev) => (prev ? `${prev} ${tag}` : tag));

            return;
        }

        const start = el.selectionStart ?? el.value.length;
        const end = el.selectionEnd ?? el.value.length;
        const current = el.value;
        const next = current.slice(0, start) + tag + current.slice(end);
        setMessageText(next);

        window.requestAnimationFrame(() => {
            el.focus();
            el.setSelectionRange(start + tag.length, start + tag.length);
        });
    };

    const demoUnitName =
        props.workspace?.activeUnit?.name ||
        props.workspace?.tenant?.name ||
        'Barbearia Caldas';
    const demoTenantSlug = props.workspace?.tenant?.slug || 'caldas';
    const demoUnitSlug = props.workspace?.activeUnit?.slug || 'matriz';
    const demoBookingUrl =
        typeof window !== 'undefined'
            ? `${window.location.origin}/book/${demoTenantSlug}/${demoUnitSlug}`
            : `https://caldas.app/book/${demoTenantSlug}/${demoUnitSlug}`;

    const previewMessage = (messageText || 'Escreva sua mensagem...')
        .replace(/\{cliente\}/g, 'Maria Silva')
        .replace(/\{unidade\}/g, demoUnitName)
        .replace(/\{link_agendamento\}/g, demoBookingUrl);

    const previewTime = new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date());

    const createKey = useMemo(
        () => createIdempotencyKey('retention-campaign-create'),
        [],
    );
    const audienceRequest = useHttp<{ dry_run: boolean }, OperationResponse>({
        dry_run: false,
    });
    const dispatchRequest = useHttp<{ dry_run: boolean }, OperationResponse>({
        dry_run: false,
    });
    const processRequest = useHttp<
        { dry_run: boolean; limit: number },
        OperationResponse
    >({
        dry_run: false,
        limit: 100,
    });

    const visibleCampaigns =
        statusFilter === 'all'
            ? campaigns.data
            : campaigns.data.filter(
                  (campaign) => campaign.status === statusFilter,
              );

    function reloadCampaigns(): void {
        router.reload({ only: ['campaigns'] });
    }

    async function runOperation(
        operation: 'audience' | 'dispatch' | 'process',
        campaign: RetentionCampaign,
    ): Promise<void> {
        const key = `${operation}-${campaign.id}`;
        setProcessingAction(key);
        setFeedback(null);

        try {
            let response: OperationResponse;

            if (operation === 'audience') {
                audienceRequest.setData({ dry_run: false });
                response = await audienceRequest.post(
                    campaignsRoutes.audience.url(campaign.id),
                    {
                        headers: {
                            'X-Idempotency-Key': createIdempotencyKey(key),
                        },
                    },
                );
                setFeedback(
                    `Audiência salva com ${response.selected ?? 0} cliente(s) selecionado(s).`,
                );
            } else if (operation === 'dispatch') {
                dispatchRequest.setData({ dry_run: false });
                response = await dispatchRequest.post(
                    campaignsRoutes.dispatch.url(campaign.id),
                    {
                        headers: {
                            'X-Idempotency-Key': createIdempotencyKey(key),
                        },
                    },
                );
                setFeedback(
                    `Fila preparada: ${response.queued ?? 0} entrega(s), ${response.blocked ?? 0} bloqueada(s) por consentimento ou proteção legal.`,
                );
            } else {
                processRequest.setData({ dry_run: false, limit: 100 });
                response = await processRequest.post(
                    campaignsRoutes.process.url(campaign.id),
                    {
                        headers: {
                            'X-Idempotency-Key': createIdempotencyKey(key),
                        },
                    },
                );
                setFeedback(
                    `${response.sent ?? 0} entrega(s) processada(s); ${response.blocked ?? 0} bloqueada(s).`,
                );
            }

            reloadCampaigns();
        } catch {
            setFeedback(
                'Não foi possível concluir a operação. Tente novamente.',
            );
        } finally {
            setProcessingAction(null);
        }
    }

    return (
        <PageCanvas>
            <Head title="Campanhas de retenção" />

            <ResourceHeader
                eyebrow="Relacionamento"
                title="Campanhas de retenção"
                description="Crie audiências responsáveis, acompanhe a fila e mantenha o histórico de cada ação de reativação."
                action={
                    canManage ? (
                        <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                            <DialogTrigger asChild>
                                <Button>
                                    <Megaphone className="mr-2 size-4" />
                                    Nova campanha
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                                <DialogHeader>
                                    <DialogTitle>
                                        Nova campanha de retenção
                                    </DialogTitle>
                                    <DialogDescription>
                                        Defina uma audiência e uma mensagem. O
                                        sistema respeita consentimentos e
                                        bloqueios legais antes de criar
                                        entregas.
                                    </DialogDescription>
                                </DialogHeader>
                                <Form
                                    {...campaignsRoutes.store.form()}
                                    headers={{ 'X-Idempotency-Key': createKey }}
                                    onSuccess={() => {
                                        setCreateOpen(false);
                                        setMessageText(initialMessage);
                                    }}
                                    className="grid gap-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <FormErrorSummary errors={errors} />
                                            <FormField
                                                name="name"
                                                label="Nome da campanha"
                                                error={errors.name}
                                                required
                                            >
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    placeholder="Ex.: Reativação de clientes inativos"
                                                    required
                                                />
                                            </FormField>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <FormField
                                                    name="channel"
                                                    label="Canal"
                                                    error={errors.channel}
                                                    required
                                                >
                                                    <Select
                                                        name="channel"
                                                        defaultValue="whatsapp"
                                                    >
                                                        <SelectTrigger id="channel">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="whatsapp">
                                                                WhatsApp
                                                            </SelectItem>
                                                            <SelectItem value="email">
                                                                E-mail
                                                            </SelectItem>
                                                            <SelectItem value="sms">
                                                                SMS
                                                            </SelectItem>
                                                            <SelectItem value="phone">
                                                                Telefone
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </FormField>
                                                <FormField
                                                    name="purpose"
                                                    label="Objetivo"
                                                    error={errors.purpose}
                                                >
                                                    <Input
                                                        id="purpose"
                                                        name="purpose"
                                                        defaultValue="retention"
                                                        placeholder="retention"
                                                    />
                                                </FormField>
                                            </div>
                                            <fieldset className="grid gap-3 rounded-xl border border-border p-4">
                                                <legend className="px-1 text-sm font-semibold">
                                                    Audiência
                                                </legend>
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <FormField
                                                        name="segment_definition[inactive_days]"
                                                        label="Inativo há pelo menos (dias)"
                                                        error={
                                                            errors[
                                                                'segment_definition.inactive_days'
                                                            ]
                                                        }
                                                    >
                                                        <Input
                                                            id="inactive_days"
                                                            name="segment_definition[inactive_days]"
                                                            type="number"
                                                            min="1"
                                                            max="3650"
                                                            defaultValue="90"
                                                        />
                                                    </FormField>
                                                    <FormField
                                                        name="segment_definition[retention_status]"
                                                        label="Status de retenção"
                                                        error={
                                                            errors[
                                                                'segment_definition.retention_status'
                                                            ]
                                                        }
                                                    >
                                                        <Select
                                                            name="segment_definition[retention_status]"
                                                            defaultValue="at_risk"
                                                        >
                                                            <SelectTrigger id="retention_status">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="at_risk">
                                                                    Em risco
                                                                </SelectItem>
                                                                <SelectItem value="none">
                                                                    Sem
                                                                    classificação
                                                                </SelectItem>
                                                                <SelectItem value="reactivated">
                                                                    Reativado
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </FormField>
                                                </div>
                                            </fieldset>
                                            <FormField
                                                name="subject"
                                                label="Assunto (opcional)"
                                                error={errors.subject}
                                            >
                                                <Input
                                                    id="subject"
                                                    name="subject"
                                                    placeholder="Sentimos sua falta"
                                                />
                                            </FormField>
                                            <FormField
                                                name="message"
                                                label="Mensagem"
                                                error={errors.message}
                                                required
                                            >
                                                <div className="space-y-2">
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <span className="text-xs text-muted-foreground">
                                                            Tags dinâmicas:
                                                        </span>
                                                        <div className="flex flex-wrap gap-1.5">
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    insertTag(
                                                                        '{cliente}',
                                                                    )
                                                                }
                                                                className="inline-flex items-center rounded-md border border-dashed border-emerald-500/50 bg-emerald-500/10 px-2 py-0.5 font-mono text-xs font-semibold text-emerald-700 transition hover:bg-emerald-500/20 active:scale-95 dark:text-emerald-300"
                                                            >
                                                                + {'{cliente}'}
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    insertTag(
                                                                        '{unidade}',
                                                                    )
                                                                }
                                                                className="inline-flex items-center rounded-md border border-dashed border-blue-500/50 bg-blue-500/10 px-2 py-0.5 font-mono text-xs font-semibold text-blue-700 transition hover:bg-blue-500/20 active:scale-95 dark:text-blue-300"
                                                            >
                                                                + {'{unidade}'}
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    insertTag(
                                                                        '{link_agendamento}',
                                                                    )
                                                                }
                                                                className="inline-flex items-center rounded-md border border-dashed border-purple-500/50 bg-purple-500/10 px-2 py-0.5 font-mono text-xs font-semibold text-purple-700 transition hover:bg-purple-500/20 active:scale-95 dark:text-purple-300"
                                                            >
                                                                +{' '}
                                                                {
                                                                    '{link_agendamento}'
                                                                }
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <Textarea
                                                        ref={textareaRef}
                                                        id="message"
                                                        name="message"
                                                        rows={4}
                                                        value={messageText}
                                                        onChange={(event) =>
                                                            setMessageText(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        placeholder="Escreva uma mensagem clara e responsável..."
                                                        required
                                                    />
                                                </div>
                                            </FormField>
                                            {/* Preview do WhatsApp em Tempo Real */}
                                            <div className="space-y-2">
                                                <div className="flex items-center justify-between text-xs text-muted-foreground">
                                                    <span className="font-semibold text-foreground">
                                                        Preview do WhatsApp em
                                                        Tempo Real
                                                    </span>
                                                    <span className="flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400">
                                                        <MessageCircle className="size-3.5" />
                                                        Simulação ao vivo
                                                    </span>
                                                </div>
                                                <div className="overflow-hidden rounded-2xl border border-border/80 shadow-sm">
                                                    {/* WhatsApp Header Bar */}
                                                    <div className="flex items-center justify-between bg-[#075e54] px-3.5 py-2.5 text-white">
                                                        <div className="flex items-center gap-2.5">
                                                            <div className="flex size-7 items-center justify-center rounded-full bg-white/20 text-xs font-bold text-white uppercase">
                                                                {demoUnitName.slice(
                                                                    0,
                                                                    2,
                                                                )}
                                                            </div>
                                                            <div className="min-w-0">
                                                                <p className="truncate text-xs leading-tight font-bold">
                                                                    {
                                                                        demoUnitName
                                                                    }
                                                                </p>
                                                                <p className="flex items-center gap-1 text-3xs font-medium text-emerald-200">
                                                                    <span className="size-1.5 rounded-full bg-emerald-300" />
                                                                    online
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div className="flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-3xs font-semibold text-white">
                                                            <MessageCircle className="size-3 text-[#25D366]" />
                                                            <span>
                                                                WhatsApp
                                                            </span>
                                                        </div>
                                                    </div>
                                                    {/* WhatsApp Chat Body */}
                                                    <div className="bg-[#efeae2] p-3.5 sm:p-4 dark:bg-[#0b141a]">
                                                        {/* Outgoing Message Bubble */}
                                                        <div className="ml-auto max-w-[88%] rounded-2xl rounded-tr-xs bg-[#d9fdd3] p-3 text-xs text-slate-900 shadow-xs dark:bg-[#005c4b] dark:text-white">
                                                            <p className="leading-relaxed break-words whitespace-pre-wrap">
                                                                {previewMessage}
                                                            </p>
                                                            <div className="mt-1.5 flex items-center justify-end gap-1 text-3xs text-slate-500 dark:text-emerald-200">
                                                                <span>
                                                                    {
                                                                        previewTime
                                                                    }
                                                                </span>
                                                                <CheckCheck className="size-3.5 text-[#53bdeb]" />
                                                            </div>
                                                        </div>
                                                    </div>
                                                    {/* Footer info */}
                                                    <div className="border-t border-border/50 bg-muted/40 px-3.5 py-1.5 text-3xs text-muted-foreground">
                                                        Variáveis
                                                        demonstrativas:{' '}
                                                        <strong className="text-foreground">
                                                            &#123;cliente&#125;
                                                        </strong>{' '}
                                                        ➔ Maria Silva ·{' '}
                                                        <strong className="text-foreground">
                                                            &#123;unidade&#125;
                                                        </strong>{' '}
                                                        ➔ {demoUnitName} ·{' '}
                                                        <strong className="text-foreground">
                                                            &#123;link_agendamento&#125;
                                                        </strong>{' '}
                                                        ➔ {demoBookingUrl}
                                                    </div>
                                                </div>
                                            </div>
                                            <FormActions
                                                processing={processing}
                                                onCancel={() => {
                                                    setCreateOpen(false);
                                                    setMessageText(
                                                        initialMessage,
                                                    );
                                                }}
                                                submitLabel="Criar campanha"
                                            />
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    ) : null
                }
            />

            <Alert>
                <ShieldCheck className="size-4" />
                <AlertTitle>Retenção com consentimento</AlertTitle>
                <AlertDescription>
                    As operações são internas e auditáveis. Nenhum provedor
                    externo é acionado por esta tela; entregas só avançam quando
                    o consentimento permanece válido.
                </AlertDescription>
            </Alert>

            {feedback ? (
                <Alert className="border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
                    <Check className="size-4" />
                    <AlertDescription>{feedback}</AlertDescription>
                </Alert>
            ) : null}

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-3 shadow-sm sm:p-4">
                <div>
                    <p className="text-sm font-semibold">Ciclo da campanha</p>
                    <p className="text-xs text-muted-foreground">
                        Rascunho → audiência → fila → processamento
                    </p>
                </div>
                <div
                    className="flex flex-wrap gap-1 rounded-lg bg-muted p-1"
                    role="group"
                    aria-label="Filtrar campanhas por status"
                >
                    {(
                        [
                            'all',
                            'draft',
                            'active',
                            'paused',
                            'completed',
                        ] as const
                    ).map((value) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => setStatusFilter(value)}
                            aria-pressed={statusFilter === value}
                            className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${statusFilter === value ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                        >
                            {value === 'all' ? 'Todas' : statusLabel[value]}
                        </button>
                    ))}
                </div>
            </div>

            {visibleCampaigns.length === 0 ? (
                <EmptyState
                    icon={Megaphone}
                    title="Nenhuma campanha neste filtro"
                    description="Crie uma campanha para transformar clientes inativos em novas oportunidades de relacionamento."
                />
            ) : (
                <div className="grid gap-4 xl:grid-cols-2">
                    {visibleCampaigns.map((campaign) => {
                        const audienceKey = `audience-${campaign.id}`;
                        const dispatchKey = `dispatch-${campaign.id}`;
                        const processKey = `process-${campaign.id}`;
                        const hasAudience = Boolean(
                            campaign.audience_snapshot_at,
                        );

                        return (
                            <Card key={campaign.id} className="overflow-hidden">
                                <CardHeader className="gap-3 border-b border-border bg-muted/20">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <CardTitle className="truncate">
                                                    {campaign.name}
                                                </CardTitle>
                                                <StatusBadge
                                                    status={campaign.status}
                                                />
                                            </div>
                                            <CardDescription>
                                                {channelLabel[campaign.channel]}{' '}
                                                · criada em{' '}
                                                {formatDate(
                                                    campaign.created_at,
                                                )}
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant="outline"
                                            className="shrink-0"
                                        >
                                            {campaign.purpose}
                                        </Badge>
                                    </div>
                                    <SegmentSummary campaign={campaign} />
                                </CardHeader>
                                <CardContent className="grid gap-5 pt-6">
                                    <p className="line-clamp-3 text-sm leading-6 text-muted-foreground">
                                        {campaign.message}
                                    </p>
                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        <div className="rounded-lg bg-muted/50 p-3">
                                            <Users className="mb-2 size-4 text-muted-foreground" />
                                            <p className="text-xl font-semibold">
                                                {campaign.recipients_count}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                audiência
                                            </p>
                                        </div>
                                        <div className="rounded-lg bg-muted/50 p-3">
                                            <Send className="mb-2 size-4 text-muted-foreground" />
                                            <p className="text-xl font-semibold">
                                                {campaign.deliveries_count}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                entregas
                                            </p>
                                        </div>
                                        <div className="rounded-lg bg-muted/50 p-3">
                                            <Sparkles className="mb-2 size-4 text-muted-foreground" />
                                            <p className="text-sm font-semibold">
                                                {hasAudience
                                                    ? 'Pronta'
                                                    : 'Pendente'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                snapshot
                                            </p>
                                        </div>
                                        <div className="rounded-lg bg-muted/50 p-3">
                                            <RefreshCw className="mb-2 size-4 text-muted-foreground" />
                                            <p className="text-sm font-semibold">
                                                {campaign.status === 'active'
                                                    ? 'Operável'
                                                    : 'Aguardando'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                estado
                                            </p>
                                        </div>
                                    </div>
                                    {canManage ? (
                                        <div className="flex flex-wrap gap-2 border-t border-border pt-4">
                                            {campaign.status === 'draft' ? (
                                                <Form
                                                    {...campaignsRoutes.status.form(
                                                        campaign.id,
                                                    )}
                                                    headers={{
                                                        'X-Idempotency-Key':
                                                            createIdempotencyKey(
                                                                `campaign-active-${campaign.id}`,
                                                            ),
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <>
                                                            <input
                                                                type="hidden"
                                                                name="status"
                                                                value="active"
                                                            />
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                <Play className="mr-1.5 size-3.5" />
                                                                Ativar
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            ) : null}
                                            {campaign.status === 'active' ? (
                                                <Form
                                                    {...campaignsRoutes.status.form(
                                                        campaign.id,
                                                    )}
                                                    headers={{
                                                        'X-Idempotency-Key':
                                                            createIdempotencyKey(
                                                                `campaign-pause-${campaign.id}`,
                                                            ),
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <>
                                                            <input
                                                                type="hidden"
                                                                name="status"
                                                                value="paused"
                                                            />
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                <ChevronDown className="mr-1.5 size-3.5" />
                                                                Pausar
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            ) : null}
                                            {campaign.status === 'paused' ? (
                                                <Form
                                                    {...campaignsRoutes.status.form(
                                                        campaign.id,
                                                    )}
                                                    headers={{
                                                        'X-Idempotency-Key':
                                                            createIdempotencyKey(
                                                                `campaign-resume-${campaign.id}`,
                                                            ),
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <>
                                                            <input
                                                                type="hidden"
                                                                name="status"
                                                                value="active"
                                                            />
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                <Play className="mr-1.5 size-3.5" />
                                                                Retomar
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            ) : null}
                                            {!hasAudience &&
                                            campaign.status !== 'completed' ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={
                                                        processingAction ===
                                                        audienceKey
                                                    }
                                                    onClick={() =>
                                                        void runOperation(
                                                            'audience',
                                                            campaign,
                                                        )
                                                    }
                                                >
                                                    <Users className="mr-1.5 size-3.5" />
                                                    {processingAction ===
                                                    audienceKey
                                                        ? 'Montando…'
                                                        : 'Montar audiência'}
                                                </Button>
                                            ) : null}
                                            {hasAudience &&
                                            campaign.status === 'active' ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={
                                                        processingAction ===
                                                        dispatchKey
                                                    }
                                                    onClick={() =>
                                                        void runOperation(
                                                            'dispatch',
                                                            campaign,
                                                        )
                                                    }
                                                >
                                                    <Send className="mr-1.5 size-3.5" />
                                                    {processingAction ===
                                                    dispatchKey
                                                        ? 'Preparando…'
                                                        : 'Preparar fila'}
                                                </Button>
                                            ) : null}
                                            {campaign.deliveries_count > 0 &&
                                            campaign.status === 'active' ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={
                                                        processingAction ===
                                                        processKey
                                                    }
                                                    onClick={() =>
                                                        void runOperation(
                                                            'process',
                                                            campaign,
                                                        )
                                                    }
                                                >
                                                    <RefreshCw className="mr-1.5 size-3.5" />
                                                    {processingAction ===
                                                    processKey
                                                        ? 'Processando…'
                                                        : 'Processar fila'}
                                                </Button>
                                            ) : null}
                                        </div>
                                    ) : null}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}

            <Pagination paginated={campaigns} />
        </PageCanvas>
    );
}
