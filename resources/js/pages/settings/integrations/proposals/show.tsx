import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AlertTriangle, Check, Clock3, X } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import integrationProposals from '@/routes/integration-proposals';
import stepUpRoutes from '@/routes/integrations/step-up';

type Proposal = {
    id: string;
    operation: string;
    status:
        | 'pending_confirmation'
        | 'executing'
        | 'succeeded'
        | 'failed'
        | 'needs_refresh'
        | 'rejected'
        | 'expired';
    expiresAt: string;
    createdAt: string | null;
    summary: {
        name?: string | null;
        description?: string | null;
        type?: 'service' | 'product' | 'general';
        is_active?: boolean;
        duration_minutes?: number;
        price_cents?: number;
        status?: 'active' | 'inactive';
        professionals?: Array<{ id: string; name: string | null }>;
        professionalAssignmentsValid?: boolean;
        category_id?: string;
        changed_fields?: string[];
        expected_version?: number;
        service_id?: string;
        timezone?: string | null;
        address?: Record<string, string | null> | null;
        online_booking_enabled?: boolean;
        appointment_sales_automation_enabled?: boolean;
        content?: Record<string, unknown>;
        [key: string]: unknown;
    };
    currentCategory: {
        name: string;
        type: 'service' | 'product' | 'general';
        description: string | null;
        is_active: boolean;
        lock_version: number;
    } | null;
    proposedCategory: {
        name: string;
        type: 'service' | 'product' | 'general';
        description: string | null;
        is_active: boolean;
    } | null;
    expectedCategoryVersionMatches: boolean;
    currentService: {
        name: string;
        description: string | null;
        duration_minutes: number;
        price_cents: number;
        status: 'active' | 'inactive';
        lock_version: number;
    } | null;
    proposedService: {
        name: string;
        description: string | null;
        duration_minutes: number;
        price_cents: number;
        status: 'active' | 'inactive';
    } | null;
    expectedServiceVersionMatches: boolean;
    currentUnit: {
        name: string;
        timezone: string | null;
        address: Record<string, string | null> | null;
        online_booking_enabled: boolean;
        appointment_sales_automation_enabled: boolean;
        lock_version: number;
    } | null;
    expectedUnitVersionMatches: boolean;
    serviceRelations: {
        currentCategory: { id: string; name: string } | null;
        proposedCategory: { id: string; name: string } | null;
        currentProfessionals: Array<{ id: string; name: string | null }>;
        proposedProfessionals: Array<{ id: string; name: string | null }>;
        assignmentsValid: boolean;
    } | null;
    professionalServices: {
        currentServices: Array<{ name: string | null }>;
        proposedServices: Array<{ name: string | null }>;
    } | null;
    operationReview: {
        kind: string;
        current: Record<string, unknown> | null;
        proposed: Record<string, unknown>;
        expectedVersionMatches: boolean;
    };
    canConfirm: boolean;
};

type PageProps = {
    proposal: Proposal;
};

type StepUpOptionsResponse = {
    ceremony: string;
    options: PublicKeyCredentialRequestOptionsJSON;
};

type PublicKeyCredentialRequestOptionsJSON = Omit<
    PublicKeyCredentialRequestOptions,
    'challenge' | 'allowCredentials'
> & {
    challenge: string;
    allowCredentials?: Array<
        Omit<PublicKeyCredentialDescriptor, 'id'> & { id: string }
    >;
};

type AuthenticationResponseJSON = {
    id: string;
    rawId: string;
    type: 'public-key';
    response: {
        authenticatorData: string;
        clientDataJSON: string;
        signature: string;
        userHandle: string | null;
    };
    clientExtensionResults: AuthenticationExtensionsClientOutputs;
};

const base64UrlToBytes = (value: string): Uint8Array<ArrayBuffer> => {
    const base64 = value.replace(/-/gu, '+').replace(/_/gu, '/');
    const padded = base64.padEnd(Math.ceil(base64.length / 4) * 4, '=');
    const binary = window.atob(padded);
    const bytes = new Uint8Array(new ArrayBuffer(binary.length));

    for (let index = 0; index < binary.length; index += 1) {
        bytes[index] = binary.charCodeAt(index);
    }

    return bytes;
};

const bytesToBase64Url = (buffer: ArrayBuffer): string => {
    const bytes = new Uint8Array(buffer);
    let binary = '';

    for (const byte of bytes) {
        binary += String.fromCharCode(byte);
    }

    return window
        .btoa(binary)
        .replace(/\+/gu, '-')
        .replace(/\//gu, '_')
        .replace(/=+$/gu, '');
};

const serializeAssertion = (
    credential: PublicKeyCredential,
): AuthenticationResponseJSON => {
    const response = credential.response as AuthenticatorAssertionResponse;

    return {
        id: credential.id,
        rawId: bytesToBase64Url(credential.rawId),
        type: 'public-key',
        response: {
            authenticatorData: bytesToBase64Url(response.authenticatorData),
            clientDataJSON: bytesToBase64Url(response.clientDataJSON),
            signature: bytesToBase64Url(response.signature),
            userHandle:
                response.userHandle === null
                    ? null
                    : bytesToBase64Url(response.userHandle),
        },
        clientExtensionResults: credential.getClientExtensionResults(),
    };
};

const csrfHeaders = (): Record<string, string> => {
    const csrfToken = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    )?.content;

    if (csrfToken) {
        return { 'X-CSRF-TOKEN': csrfToken };
    }

    const xsrfCookie = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='));

    return xsrfCookie
        ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfCookie.slice(11)) }
        : {};
};

const sameOriginUrl = (url: string): string => {
    const parsedUrl = new URL(url, window.location.origin);

    if (parsedUrl.origin !== window.location.origin) {
        throw new Error('A rota da proposta não pertence a este sistema.');
    }

    return `${parsedUrl.pathname}${parsedUrl.search}`;
};

const requestStepUp = async <T,>(
    url: string,
    method: 'GET' | 'POST',
    body?: unknown,
): Promise<T> => {
    const response = await fetch(sameOriginUrl(url), {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(body === undefined
                ? {}
                : { 'Content-Type': 'application/json' }),
            ...(method === 'POST' ? csrfHeaders() : {}),
        },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });

    if (!response.ok) {
        let message =
            'Não foi possível confirmar sua identidade. Tente novamente.';

        try {
            const payload = (await response.json()) as {
                message?: unknown;
                errors?: Record<string, unknown>;
            };
            const firstError = payload.errors
                ? Object.values(payload.errors)
                      .flat()
                      .find((item) => typeof item === 'string')
                : undefined;

            if (typeof firstError === 'string') {
                message = firstError;
            } else if (typeof payload.message === 'string') {
                message = payload.message;
            }
        } catch {
            // Keep the generic message when the server returns no JSON body.
        }

        throw new Error(message);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
};

const formatDate = (value: string | null): string | null => {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
};

const formatMoney = (cents: number): string =>
    new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(cents / 100);

const statusLabels: Record<Proposal['status'], string> = {
    pending_confirmation: 'Aguardando decisão',
    executing: 'Em execução',
    succeeded: 'Concluída',
    failed: 'Falhou',
    needs_refresh: 'Precisa revisar',
    rejected: 'Rejeitada',
    expired: 'Expirada',
};

const statusDescriptions: Record<Proposal['status'], string> = {
    pending_confirmation:
        'Ao confirmar, o sistema criará o cadastro descrito acima. Sua passkey será solicitada para autorizar esta ação.',
    executing: 'A criação foi iniciada e ainda está sendo processada.',
    succeeded: 'A criação do cadastro foi concluída.',
    failed: 'O sistema não conseguiu concluir a criação deste cadastro.',
    needs_refresh:
        'Os dados do sistema mudaram desde a criação da proposta. Gere uma nova proposta para revisar os dados atuais.',
    rejected: 'Esta proposta foi rejeitada e não será executada.',
    expired: 'Esta proposta expirou e não poderá mais ser confirmada.',
};

const categoryTypeLabels: Record<
    NonNullable<Proposal['summary']['type']>,
    string
> = {
    service: 'Serviços',
    product: 'Produtos',
    general: 'Geral',
};

function CategoryValues({
    title,
    values,
}: {
    title: string;
    values: NonNullable<Proposal['currentCategory']>;
}) {
    return (
        <Card className="overflow-hidden">
            <CardHeader className="border-b border-border bg-muted/30">
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-5 p-5 sm:p-6">
                <div className="grid gap-1">
                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Nome
                    </span>
                    <span className="text-base font-medium">{values.name}</span>
                </div>
                <div className="grid gap-1">
                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Descrição
                    </span>
                    <span className="text-sm leading-6 text-muted-foreground">
                        {values.description || 'Sem descrição'}
                    </span>
                </div>
                <div className="grid gap-1">
                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Tipo
                    </span>
                    <span className="text-sm font-medium">
                        {categoryTypeLabels[values.type]}
                    </span>
                </div>
                <div className="grid gap-1">
                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Status
                    </span>
                    <span className="text-sm font-medium">
                        {values.is_active ? 'Ativa' : 'Inativa'}
                    </span>
                </div>
            </CardContent>
        </Card>
    );
}

function ServiceValues({
    title,
    values,
}: {
    title: string;
    values: NonNullable<Proposal['currentService']>;
}) {
    return (
        <Card className="overflow-hidden">
            <CardHeader className="border-b border-border bg-muted/30">
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-5 p-5 sm:p-6">
                <div className="grid gap-1">
                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Nome
                    </span>
                    <span className="text-base font-medium">{values.name}</span>
                </div>
                <div className="grid gap-1">
                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Descrição
                    </span>
                    <span className="text-sm leading-6 text-muted-foreground">
                        {values.description || 'Sem descrição'}
                    </span>
                </div>
                <div className="grid gap-1">
                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Duração
                    </span>
                    <span className="text-sm font-medium">
                        {values.duration_minutes} minutos
                    </span>
                </div>
                <div className="grid gap-1">
                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Preço
                    </span>
                    <span className="text-sm font-medium">
                        {formatMoney(values.price_cents)}
                    </span>
                </div>
                <div className="grid gap-1">
                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Status
                    </span>
                    <span className="text-sm font-medium">
                        {values.status === 'active' ? 'Ativo' : 'Inativo'}
                    </span>
                </div>
            </CardContent>
        </Card>
    );
}

function UnitValues({
    title,
    values,
}: {
    title: string;
    values: {
        name: string;
        timezone: string | null;
        address: Record<string, string | null> | null;
        online_booking_enabled: boolean;
        appointment_sales_automation_enabled: boolean;
    } | null;
}) {
    const address = values?.address
        ? Object.values(values.address).filter(Boolean).join(', ')
        : null;

    return (
        <Card className="overflow-hidden">
            <CardHeader className="border-b border-border bg-muted/30">
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4 p-5 sm:p-6">
                <RelationValues
                    title="Nome"
                    value={values?.name ?? 'Unidade indisponível'}
                />
                <RelationValues
                    title="Fuso horário"
                    value={values?.timezone ?? 'Não definido'}
                />
                <RelationValues
                    title="Endereço"
                    value={address ?? 'Não definido'}
                />
                <RelationValues
                    title="Agendamento online"
                    value={
                        values?.online_booking_enabled
                            ? 'Ativado'
                            : 'Desativado'
                    }
                />
                <RelationValues
                    title="Automação de vendas de atendimentos"
                    value={
                        values?.appointment_sales_automation_enabled
                            ? 'Ativada'
                            : 'Desativada'
                    }
                />
            </CardContent>
        </Card>
    );
}

function RelationValues({ title, value }: { title: string; value: string }) {
    return (
        <div className="grid gap-1">
            <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {title}
            </span>
            <span className="text-sm font-medium">{value}</span>
        </div>
    );
}

function PeopleValues({
    title,
    people,
}: {
    title: string;
    people: Array<{ id?: string; name: string | null }>;
}) {
    return (
        <div className="grid gap-1">
            <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {title}
            </span>
            <span className="text-sm font-medium">
                {people.length > 0
                    ? people
                          .map(
                              (person) =>
                                  person.name ?? person.id ?? 'Sem nome',
                          )
                          .join(', ')
                    : 'Nenhum profissional'}
            </span>
        </div>
    );
}

function OperationReview({ review }: { review: Proposal['operationReview'] }) {
    const renderValue = (value: unknown): string => {
        if (value === null || value === undefined) {
            return 'Não definido';
        }

        if (typeof value === 'boolean') {
            return value ? 'Sim' : 'Não';
        }

        if (typeof value === 'object') {
            return JSON.stringify(value, null, 2);
        }

        return String(value);
    };

    const renderValues = (values: Record<string, unknown> | null) => {
        if (values === null) {
            return (
                <span className="text-sm text-muted-foreground">
                    Ainda não existe
                </span>
            );
        }

        return (
            <dl className="grid gap-3">
                {Object.entries(values).map(([key, value]) => (
                    <div key={key} className="grid gap-1">
                        <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            {key.replaceAll('_', ' ')}
                        </dt>
                        <dd className="text-sm font-medium break-words whitespace-pre-wrap">
                            {renderValue(value)}
                        </dd>
                    </div>
                ))}
            </dl>
        );
    };

    return (
        <section
            className="grid gap-4 lg:grid-cols-2"
            aria-label="Comparação da proposta"
        >
            <Card>
                <CardHeader className="border-b border-border bg-muted/30">
                    <CardTitle className="text-base">Atual</CardTitle>
                </CardHeader>
                <CardContent className="p-5">
                    {renderValues(review.current)}
                </CardContent>
            </Card>
            <Card>
                <CardHeader className="border-b border-border bg-muted/30">
                    <CardTitle className="text-base">Proposto</CardTitle>
                </CardHeader>
                <CardContent className="p-5">
                    {renderValues(review.proposed)}
                </CardContent>
            </Card>
        </section>
    );
}

export default function ShowProposal({ proposal }: PageProps) {
    const [pendingAction, setPendingAction] = useState<
        'confirm' | 'reject' | null
    >(null);
    const [error, setError] = useState<string | null>(null);
    const passkeySupported =
        typeof window !== 'undefined' &&
        'PublicKeyCredential' in window &&
        Boolean(navigator.credentials?.get);
    const expiresAt = formatDate(proposal.expiresAt);
    const createdAt = formatDate(proposal.createdAt);
    const canAct =
        proposal.status === 'pending_confirmation' && proposal.canConfirm;
    const isCategoryUpdate = proposal.operation === 'category.update';
    const isCategory =
        isCategoryUpdate || proposal.operation === 'category.create';
    const isServiceUpdate = proposal.operation === 'service.update';
    const isProfessionalUpdate = proposal.operation === 'professional.update';
    const isUnitUpdate = proposal.operation === 'unit.update';
    const hasOperationReview =
        isProfessionalUpdate ||
        proposal.operation.startsWith('availability_rule.') ||
        proposal.operation.startsWith('schedule_block.') ||
        proposal.operation.startsWith('booking.');
    const statusDescription = isCategoryUpdate
        ? {
              pending_confirmation:
                  'Ao confirmar, os valores propostos substituirão os valores atuais. Sua passkey será solicitada para autorizar a atualização.',
              executing:
                  'A atualização foi iniciada e ainda está sendo processada.',
              succeeded: 'A atualização da categoria foi concluída.',
              failed: 'O sistema não conseguiu concluir a atualização desta categoria.',
              needs_refresh:
                  'A categoria mudou desde a criação da proposta. Gere uma nova proposta para revisar os dados atuais.',
              rejected: 'Esta proposta foi rejeitada e não será executada.',
              expired:
                  'Esta proposta expirou e não poderá mais ser confirmada.',
          }[proposal.status]
        : isServiceUpdate
          ? {
                pending_confirmation:
                    'Ao confirmar, os valores propostos serão aplicados. As relações não incluídas na proposta serão preservadas; sua passkey será solicitada para autorizar a atualização.',
                executing:
                    'A atualização foi iniciada e ainda está sendo processada.',
                succeeded: 'A atualização do serviço foi concluída.',
                failed: 'O sistema não conseguiu concluir a atualização deste serviço.',
                needs_refresh:
                    'O serviço mudou desde a criação da proposta. Gere uma nova proposta para revisar os dados atuais.',
                rejected: 'Esta proposta foi rejeitada e não será executada.',
                expired:
                    'Esta proposta expirou e não poderá mais ser confirmada.',
            }[proposal.status]
          : statusDescriptions[proposal.status];

    const confirmProposal = async () => {
        if (pendingAction || !passkeySupported) {
            return;
        }

        setError(null);
        setPendingAction('confirm');

        try {
            const challenge = await requestStepUp<StepUpOptionsResponse>(
                stepUpRoutes.options.url('operations.confirm', {
                    query: { proposal: proposal.id },
                }),
                'GET',
            );
            const requestOptions: PublicKeyCredentialRequestOptions = {
                ...challenge.options,
                challenge: base64UrlToBytes(challenge.options.challenge),
                allowCredentials: challenge.options.allowCredentials?.map(
                    (credential) => ({
                        ...credential,
                        id: base64UrlToBytes(credential.id),
                    }),
                ),
            };
            const assertion = await navigator.credentials.get({
                publicKey: requestOptions,
            });

            if (!(assertion instanceof PublicKeyCredential)) {
                throw new Error(
                    'A confirmação por passkey foi cancelada. Tente novamente.',
                );
            }

            await requestStepUp<{ status: string }>(
                stepUpRoutes.verify.url('operations.confirm', {
                    query: { proposal: proposal.id },
                }),
                'POST',
                {
                    ceremony: challenge.ceremony,
                    credential: serializeAssertion(assertion),
                },
            );

            const confirmUrl = sameOriginUrl(
                integrationProposals.confirm(proposal.id).url,
            );

            router.post(
                confirmUrl,
                {},
                {
                    onError: (errors) => {
                        setError(
                            Object.values(errors).find(
                                (message): message is string =>
                                    typeof message === 'string',
                            ) ?? 'Não foi possível aplicar a alteração.',
                        );
                    },
                    onFinish: () => setPendingAction(null),
                },
            );
        } catch (caught) {
            setError(
                caught instanceof Error
                    ? caught.message
                    : 'Não foi possível aplicar a alteração.',
            );
            setPendingAction(null);
        }
    };

    const rejectProposal = () => {
        if (pendingAction) {
            return;
        }

        setError(null);
        setPendingAction('reject');

        const rejectUrl = sameOriginUrl(
            integrationProposals.reject(proposal.id).url,
        );

        router.post(
            rejectUrl,
            {},
            {
                onError: (errors) => {
                    setError(
                        Object.values(errors).find(
                            (message): message is string =>
                                typeof message === 'string',
                        ) ?? 'Não foi possível rejeitar esta proposta.',
                    );
                },
                onFinish: () => setPendingAction(null),
            },
        );
    };

    return (
        <>
            <Head
                title={`${isCategoryUpdate || isServiceUpdate || isProfessionalUpdate || isUnitUpdate ? 'Revisar atualização' : 'Revisar proposta'} · ${proposal.summary.name}`}
            />

            <main className="mx-auto flex min-h-full w-full max-w-4xl flex-col gap-6 px-4 py-6 sm:px-6 sm:py-10">
                <header className="flex flex-col gap-4 border-b border-border pb-6 sm:flex-row sm:items-start sm:justify-between">
                    <div className="grid gap-2">
                        <p className="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                            Assistente · revisão de proposta
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            {isCategoryUpdate
                                ? 'Confira a atualização da categoria'
                                : isServiceUpdate
                                  ? 'Confira a atualização do serviço'
                                  : isProfessionalUpdate
                                    ? 'Confira a atualização do profissional'
                                    : isUnitUpdate
                                      ? 'Confira as configurações da unidade'
                                      : 'Confira o que será criado'}
                        </h1>
                        <p className="max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                            {isCategoryUpdate
                                ? 'Compare os valores atuais com os propostos. A atualização substitui todos os campos exibidos.'
                                : isServiceUpdate
                                  ? 'Compare os campos atuais e as relações que serão aplicadas. Relações ausentes da proposta serão preservadas.'
                                  : isProfessionalUpdate
                                    ? 'Confira os serviços atuais e os serviços que serão vinculados após confirmar.'
                                    : isUnitUpdate
                                      ? 'Compare as configurações atuais da unidade com os valores propostos antes de confirmar.'
                                      : 'Revise os dados abaixo antes de autorizar a criação.'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="outline" className="w-fit font-mono">
                            {proposal.operation}
                        </Badge>
                        <Badge
                            variant={
                                proposal.status === 'pending_confirmation'
                                    ? 'secondary'
                                    : 'outline'
                            }
                        >
                            {statusLabels[proposal.status]}
                        </Badge>
                    </div>
                </header>

                {isUnitUpdate ? (
                    <section
                        className="grid gap-4 lg:grid-cols-2"
                        aria-label="Comparação das configurações da unidade"
                    >
                        <UnitValues
                            title={`Atual · versão ${proposal.currentUnit?.lock_version ?? 'indisponível'}`}
                            values={proposal.currentUnit}
                        />
                        <UnitValues
                            title={`Proposto · baseado na versão ${proposal.summary.expected_version}`}
                            values={{
                                name:
                                    proposal.summary.name ??
                                    proposal.currentUnit?.name ??
                                    '',
                                timezone: proposal.summary.timezone ?? null,
                                address: proposal.summary.address ?? null,
                                online_booking_enabled:
                                    proposal.summary.online_booking_enabled ??
                                    false,
                                appointment_sales_automation_enabled:
                                    proposal.summary
                                        .appointment_sales_automation_enabled ??
                                    false,
                            }}
                        />
                    </section>
                ) : isCategoryUpdate ? (
                    <section
                        className="grid gap-4 lg:grid-cols-2"
                        aria-label="Comparação da atualização"
                    >
                        {proposal.currentCategory ? (
                            <CategoryValues
                                title={`Atual · versão ${proposal.currentCategory.lock_version}`}
                                values={proposal.currentCategory}
                            />
                        ) : (
                            <Card>
                                <CardContent className="p-5 text-sm text-muted-foreground">
                                    A categoria não está disponível nesta
                                    unidade.
                                </CardContent>
                            </Card>
                        )}
                        <CategoryValues
                            title={`Proposto · baseado na versão ${proposal.summary.expected_version}`}
                            values={{
                                ...(proposal.proposedCategory ??
                                    proposal.currentCategory ?? {
                                        name: proposal.summary.name ?? '',
                                        type:
                                            proposal.summary.type ?? 'general',
                                        description:
                                            proposal.summary.description ??
                                            null,
                                        is_active:
                                            proposal.summary.is_active ?? true,
                                    }),
                                lock_version:
                                    proposal.summary.expected_version ?? 0,
                            }}
                        />
                    </section>
                ) : isServiceUpdate ? (
                    <section
                        className="grid gap-4 lg:grid-cols-2"
                        aria-label="Comparação da atualização do serviço"
                    >
                        {proposal.currentService ? (
                            <ServiceValues
                                title={`Atual · versão ${proposal.currentService.lock_version}`}
                                values={proposal.currentService}
                            />
                        ) : (
                            <Card>
                                <CardContent className="p-5 text-sm text-muted-foreground">
                                    O serviço não está disponível nesta unidade.
                                </CardContent>
                            </Card>
                        )}
                        <ServiceValues
                            title={`Proposto · baseado na versão ${proposal.summary.expected_version}`}
                            values={{
                                ...(proposal.proposedService ??
                                    proposal.currentService ?? {
                                        name: proposal.summary.name ?? '',
                                        description:
                                            proposal.summary.description ??
                                            null,
                                        duration_minutes:
                                            proposal.summary.duration_minutes ??
                                            0,
                                        price_cents:
                                            proposal.summary.price_cents ?? 0,
                                        status:
                                            proposal.summary.status ?? 'active',
                                    }),
                                lock_version:
                                    proposal.summary.expected_version ?? 0,
                            }}
                        />
                        <Card className="lg:col-span-2">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Categoria e profissionais
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <RelationValues
                                    title="Categoria atual"
                                    value={
                                        proposal.serviceRelations
                                            ?.currentCategory?.name ??
                                        'Sem categoria'
                                    }
                                />
                                <RelationValues
                                    title="Categoria após confirmar"
                                    value={
                                        proposal.serviceRelations
                                            ?.proposedCategory?.name ??
                                        'Sem categoria'
                                    }
                                />
                                <PeopleValues
                                    title="Profissionais atuais"
                                    people={
                                        proposal.serviceRelations
                                            ?.currentProfessionals ?? []
                                    }
                                />
                                <PeopleValues
                                    title="Profissionais após confirmar"
                                    people={
                                        proposal.serviceRelations
                                            ?.proposedProfessionals ?? []
                                    }
                                />
                            </CardContent>
                        </Card>
                        <p className="text-sm text-muted-foreground lg:col-span-2">
                            Campos e relações omitidos serão preservados. Uma
                            relação explícita pode ser limpa com categoria nula
                            ou lista vazia de profissionais.
                        </p>
                    </section>
                ) : isProfessionalUpdate ? (
                    <section
                        className="grid gap-4 lg:grid-cols-2"
                        aria-label="Serviços vinculados ao profissional"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Vínculos atuais
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <PeopleValues
                                    title="Serviços"
                                    people={
                                        proposal.professionalServices
                                            ?.currentServices ?? []
                                    }
                                />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Vínculos após confirmar
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <PeopleValues
                                    title="Serviços"
                                    people={
                                        proposal.professionalServices
                                            ?.proposedServices ?? []
                                    }
                                />
                            </CardContent>
                        </Card>
                        <div className="lg:col-span-2">
                            <OperationReview
                                review={proposal.operationReview}
                            />
                        </div>
                    </section>
                ) : hasOperationReview ? (
                    <OperationReview review={proposal.operationReview} />
                ) : (
                    <Card className="overflow-hidden">
                        <CardHeader className="border-b border-border bg-muted/30">
                            <CardTitle className="text-base">
                                Resumo do cadastro
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                            <div className="grid gap-1 sm:col-span-2">
                                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Nome
                                </span>
                                <span className="text-base font-medium">
                                    {proposal.summary.name}
                                </span>
                            </div>
                            <div className="grid gap-1 sm:col-span-2">
                                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Descrição
                                </span>
                                <span className="text-sm leading-6 text-muted-foreground">
                                    {proposal.summary.description ||
                                        'Sem descrição'}
                                </span>
                            </div>
                            {isCategory ? (
                                <>
                                    <div className="grid gap-1">
                                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Tipo
                                        </span>
                                        <span className="text-sm font-medium">
                                            {proposal.summary.type === 'service'
                                                ? 'Serviços'
                                                : proposal.summary.type ===
                                                    'product'
                                                  ? 'Produtos'
                                                  : 'Geral'}
                                        </span>
                                    </div>
                                    <div className="grid gap-1">
                                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Status inicial
                                        </span>
                                        <span className="text-sm font-medium">
                                            {proposal.summary.is_active
                                                ? 'Ativa'
                                                : 'Inativa'}
                                        </span>
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className="grid gap-1">
                                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Duração
                                        </span>
                                        <span className="text-sm font-medium">
                                            {proposal.summary.duration_minutes}{' '}
                                            minutos
                                        </span>
                                    </div>
                                    <div className="grid gap-1">
                                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Preço
                                        </span>
                                        <span className="text-sm font-medium">
                                            {formatMoney(
                                                proposal.summary.price_cents ??
                                                    0,
                                            )}
                                        </span>
                                    </div>
                                    <div className="grid gap-1">
                                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Status inicial
                                        </span>
                                        <span className="text-sm font-medium">
                                            {proposal.summary.status ===
                                            'active'
                                                ? 'Ativo'
                                                : 'Inativo'}
                                        </span>
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Profissionais selecionados
                                        </span>
                                        {(proposal.summary.professionals ?? [])
                                            .length === 0 ? (
                                            <span className="text-sm text-muted-foreground">
                                                Nenhum profissional selecionado
                                            </span>
                                        ) : null}
                                        {(proposal.summary.professionals ?? [])
                                            .length > 0 ? (
                                            <ul
                                                className="flex flex-wrap gap-2"
                                                aria-label="Profissionais selecionados"
                                            >
                                                {(
                                                    proposal.summary
                                                        .professionals ?? []
                                                ).map((professional) => (
                                                    <li
                                                        key={professional.id}
                                                        className="rounded-full border border-border bg-muted/40 px-3 py-1 text-sm"
                                                    >
                                                        {professional.name ??
                                                            `${professional.id} (fora da unidade)`}
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : null}
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                    <div className="grid gap-3">
                        <Alert
                            variant={
                                proposal.status === 'failed' ||
                                proposal.status === 'needs_refresh'
                                    ? 'destructive'
                                    : 'default'
                            }
                        >
                            {proposal.status === 'pending_confirmation' ? (
                                <AlertTriangle aria-hidden="true" />
                            ) : null}
                            <AlertTitle>
                                {proposal.status === 'pending_confirmation'
                                    ? isCategoryUpdate ||
                                      isServiceUpdate ||
                                      isUnitUpdate
                                        ? 'Confirme somente se a atualização estiver correta'
                                        : 'Confirme somente se os dados estiverem corretos'
                                    : statusLabels[proposal.status]}
                            </AlertTitle>
                            <AlertDescription>
                                {statusDescription}
                            </AlertDescription>
                        </Alert>

                        {isCategoryUpdate &&
                            proposal.status === 'pending_confirmation' &&
                            !proposal.expectedCategoryVersionMatches && (
                                <Alert variant="destructive">
                                    <AlertTriangle aria-hidden="true" />
                                    <AlertTitle>
                                        A categoria mudou desde a proposta
                                    </AlertTitle>
                                    <AlertDescription>
                                        Compare a versão atual com a proposta.
                                        Gere uma nova proposta usando a versão
                                        atual antes de confirmar.
                                    </AlertDescription>
                                </Alert>
                            )}

                        {isServiceUpdate &&
                            proposal.status === 'pending_confirmation' &&
                            !proposal.expectedServiceVersionMatches && (
                                <Alert variant="destructive">
                                    <AlertTriangle aria-hidden="true" />
                                    <AlertTitle>
                                        O serviço mudou desde a proposta
                                    </AlertTitle>
                                    <AlertDescription>
                                        Compare a versão atual com a proposta.
                                        Gere uma nova proposta usando a versão
                                        atual antes de confirmar.
                                    </AlertDescription>
                                </Alert>
                            )}

                        {isUnitUpdate &&
                            proposal.status === 'pending_confirmation' &&
                            !proposal.expectedUnitVersionMatches && (
                                <Alert variant="destructive">
                                    <AlertTriangle aria-hidden="true" />
                                    <AlertTitle>
                                        As configurações da unidade mudaram
                                    </AlertTitle>
                                    <AlertDescription>
                                        Revise os valores atuais e gere uma nova
                                        proposta com a versão atual antes de
                                        confirmar.
                                    </AlertDescription>
                                </Alert>
                            )}

                        {isServiceUpdate &&
                            proposal.status === 'pending_confirmation' &&
                            !proposal.serviceRelations?.assignmentsValid && (
                                <Alert variant="destructive">
                                    <AlertTriangle aria-hidden="true" />
                                    <AlertTitle>
                                        Uma relação proposta não está mais
                                        disponível
                                    </AlertTitle>
                                    <AlertDescription>
                                        A categoria ou um profissional já não
                                        pertence à unidade. Gere uma nova
                                        proposta após revisar os dados.
                                    </AlertDescription>
                                </Alert>
                            )}

                        {hasOperationReview &&
                            proposal.status === 'pending_confirmation' &&
                            !proposal.operationReview
                                .expectedVersionMatches && (
                                <Alert variant="destructive">
                                    <AlertTriangle aria-hidden="true" />
                                    <AlertTitle>
                                        A configuração mudou desde a proposta
                                    </AlertTitle>
                                    <AlertDescription>
                                        Revise o estado atual e gere uma nova
                                        proposta antes de confirmar.
                                    </AlertDescription>
                                </Alert>
                            )}

                        {expiresAt && (
                            <p className="flex items-center gap-2 text-xs text-muted-foreground">
                                <Clock3
                                    aria-hidden="true"
                                    className="size-3.5"
                                />
                                {proposal.status === 'expired'
                                    ? `Expirou em ${expiresAt}`
                                    : `Válida até ${expiresAt}`}
                            </p>
                        )}

                        {createdAt && (
                            <p className="text-xs text-muted-foreground">
                                Criada em {createdAt}
                            </p>
                        )}

                        {proposal.status === 'pending_confirmation' &&
                            proposal.operation === 'service.create' &&
                            !proposal.summary.professionalAssignmentsValid && (
                                <Alert variant="destructive">
                                    <AlertTriangle aria-hidden="true" />
                                    <AlertTitle>
                                        A seleção de profissionais mudou
                                    </AlertTitle>
                                    <AlertDescription>
                                        Um ou mais profissionais selecionados
                                        não pertencem mais a esta unidade. A
                                        proposta precisa ser refeita antes de
                                        confirmar.
                                    </AlertDescription>
                                </Alert>
                            )}

                        {proposal.status === 'pending_confirmation' &&
                            !isCategoryUpdate &&
                            (isCategory ||
                                isServiceUpdate ||
                                proposal.summary
                                    .professionalAssignmentsValid) &&
                            !proposal.canConfirm && (
                                <p
                                    className="rounded-md border border-border bg-muted/40 p-3 text-sm text-muted-foreground"
                                    role="status"
                                >
                                    Esta proposta não pode ser confirmada nesta
                                    sessão.
                                </p>
                            )}

                        {proposal.status === 'pending_confirmation' &&
                            proposal.canConfirm &&
                            !passkeySupported && (
                                <p
                                    className="rounded-md border border-border bg-muted/40 p-3 text-sm text-muted-foreground"
                                    role="status"
                                >
                                    Este navegador não oferece suporte a
                                    passkeys. Abra esta proposta em um navegador
                                    compatível para confirmar.
                                </p>
                            )}

                        {error && (
                            <p
                                className="text-sm text-destructive"
                                role="alert"
                            >
                                {error}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 gap-2 sm:min-w-48">
                        {canAct && (
                            <>
                                <Button
                                    type="button"
                                    onClick={confirmProposal}
                                    disabled={
                                        pendingAction !== null ||
                                        !passkeySupported
                                    }
                                    className="min-h-11"
                                >
                                    {pendingAction === 'confirm' ? (
                                        <Spinner />
                                    ) : (
                                        <Check aria-hidden="true" />
                                    )}
                                    {pendingAction === 'confirm'
                                        ? 'Confirmando…'
                                        : isCategoryUpdate ||
                                            isServiceUpdate ||
                                            isUnitUpdate
                                          ? 'Confirmar atualização'
                                          : 'Confirmar criação'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={rejectProposal}
                                    disabled={pendingAction !== null}
                                    className="min-h-11"
                                >
                                    {pendingAction === 'reject' ? (
                                        <Spinner />
                                    ) : (
                                        <X aria-hidden="true" />
                                    )}
                                    {pendingAction === 'reject'
                                        ? 'Rejeitando…'
                                        : 'Rejeitar proposta'}
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            </main>
        </>
    );
}
