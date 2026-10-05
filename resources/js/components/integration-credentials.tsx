import { Link } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import {
    Activity,
    Check,
    Clipboard,
    KeyRound,
    Plus,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import apiCredentials from '@/routes/integration-credentials';
import oauthGrants from '@/routes/integration-credentials/oauth-grants';
import stepUp from '@/routes/integrations/step-up';
import { edit as securitySettings } from '@/routes/security';

export type Credential = {
    id: string;
    label: string;
    capabilities: string[];
    created_at: string;
    expires_at: string;
    last_used_at: string | null;
    revoked_at: string | null;
    status: 'active' | 'revoked' | 'expired' | 'inactive';
};

export type Props = {
    credentials: Credential[];
    oauthGrants: OAuthGrant[];
    canManage: boolean;
    hasIntegrationPasskey?: boolean;
};

export type OAuthGrant = {
    id: string;
    client_name: string;
    capabilities: string[];
    created_at: string;
    expires_at: string;
    last_used_at: string | null;
    status: 'active' | 'revoked' | 'expired';
};

type StepUpPurpose =
    'credentials.issue' | 'credentials.revoke' | 'oauth.revoke';

type StepUpOptions = {
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

type CredentialIssueResponse = {
    data: Credential;
    secret: string;
};

type CapabilitySet =
    | 'context:read'
    | 'catalog:read'
    | 'setup:read'
    | 'catalog:read+setup:read'
    | 'context:read+catalog:read+setup:read'
    | 'operations:propose';

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

const csrfToken = (): { header: string; token: string } | null => {
    const meta = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    )?.content;

    if (meta) {
        return { header: 'X-CSRF-TOKEN', token: meta };
    }

    const xsrf = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='));

    return xsrf
        ? {
              header: 'X-XSRF-TOKEN',
              token: decodeURIComponent(xsrf.slice('XSRF-TOKEN='.length)),
          }
        : null;
};

const apiRequest = async <T,>(
    url: string,
    method: 'GET' | 'POST' | 'DELETE',
    body?: unknown,
): Promise<T> => {
    const csrf = method === 'GET' ? null : csrfToken();
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(body === undefined
                ? {}
                : { 'Content-Type': 'application/json' }),
            ...(csrf ? { [csrf.header]: csrf.token } : {}),
        },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });

    if (!response.ok) {
        let message =
            'Não foi possível concluir a solicitação. Tente novamente.';

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

const formatDate = (value: string | null): string => {
    if (!value) {
        return 'Nunca';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const statusLabel = (credential: Credential): string => {
    if (credential.revoked_at || credential.status === 'revoked') {
        return 'Revogada';
    }

    if (
        credential.status === 'expired' ||
        new Date(credential.expires_at).getTime() <= Date.now()
    ) {
        return 'Expirada';
    }

    return credential.status === 'active' ? 'Ativa' : 'Inativa';
};

export default function IntegrationCredentials({
    credentials: initialCredentials,
    oauthGrants: initialOAuthGrants,
    canManage,
    hasIntegrationPasskey,
}: Props) {
    const [credentials, setCredentials] = useState(initialCredentials);
    const [oauthGrantList, setOAuthGrantList] = useState(initialOAuthGrants);
    const [label, setLabel] = useState('');
    const [capabilitySet, setCapabilitySet] =
        useState<CapabilitySet>('context:read');
    const [secret, setSecret] = useState<string | null>(null);
    const [secretCopied, setSecretCopied] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [pendingOperation, setPendingOperation] = useState<
        'issue' | string | null
    >(null);
    const [revokeTarget, setRevokeTarget] = useState<Credential | null>(null);
    const [oauthRevokeTarget, setOAuthRevokeTarget] =
        useState<OAuthGrant | null>(null);
    const [copyError, setCopyError] = useState(false);
    const passkeySupported = useMemo(
        () =>
            typeof window !== 'undefined' &&
            'PublicKeyCredential' in window &&
            Boolean(navigator.credentials?.get),
        [],
    );

    const authenticateStepUp = useCallback(
        async (purpose: StepUpPurpose, grantId?: string): Promise<void> => {
            const optionsResponse = await apiRequest<StepUpOptions>(
                stepUp.options.url(
                    purpose,
                    grantId
                        ? { query: { oauth_grant_id: grantId } }
                        : undefined,
                ),
                'GET',
            );
            const requestOptions: PublicKeyCredentialRequestOptions = {
                ...optionsResponse.options,
                challenge: base64UrlToBytes(optionsResponse.options.challenge),
                allowCredentials: optionsResponse.options.allowCredentials?.map(
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
                    'A autenticação por passkey foi cancelada. Tente novamente.',
                );
            }

            await apiRequest<{ status: string }>(
                stepUp.verify.url(purpose),
                'POST',
                {
                    ceremony: optionsResponse.ceremony,
                    credential: serializeAssertion(assertion),
                    ...(grantId ? { oauth_grant_id: grantId } : {}),
                },
            );
        },
        [],
    );

    const handleIssue = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const normalizedLabel = label.trim();

        if (!normalizedLabel || pendingOperation) {
            return;
        }

        setError(null);
        setPendingOperation('issue');

        try {
            await authenticateStepUp('credentials.issue');
            const response = await apiRequest<CredentialIssueResponse>(
                apiCredentials.store.url({ purpose: 'credentials.issue' }),
                'POST',
                { label: normalizedLabel, capability_set: capabilitySet },
            );

            setCredentials((current) => [response.data, ...current]);
            setLabel('');
            setSecret(response.secret);
            setSecretCopied(false);
            setCopyError(false);
        } catch (caught) {
            setError(
                caught instanceof Error
                    ? caught.message
                    : 'Não foi possível criar a credencial.',
            );
        } finally {
            setPendingOperation(null);
        }
    };

    const handleRevoke = async () => {
        if (!revokeTarget || pendingOperation) {
            return;
        }

        const target = revokeTarget;
        setError(null);
        setPendingOperation(target.id);

        try {
            await authenticateStepUp('credentials.revoke');
            await apiRequest<void>(
                apiCredentials.destroy.url({
                    purpose: 'credentials.revoke',
                    credential: target.id,
                }),
                'DELETE',
            );

            const revokedAt = new Date().toISOString();
            setCredentials((current) =>
                current.map((credential) =>
                    credential.id === target.id
                        ? {
                              ...credential,
                              revoked_at: revokedAt,
                              status: 'inactive',
                          }
                        : credential,
                ),
            );
            setRevokeTarget(null);
        } catch (caught) {
            setError(
                caught instanceof Error
                    ? caught.message
                    : 'Não foi possível revogar a credencial.',
            );
        } finally {
            setPendingOperation(null);
        }
    };

    const handleOAuthGrantRevoke = async () => {
        if (!oauthRevokeTarget || pendingOperation) {
            return;
        }

        const target = oauthRevokeTarget;
        setError(null);
        setPendingOperation(target.id);

        try {
            await authenticateStepUp('oauth.revoke', target.id);
            await apiRequest<void>(
                oauthGrants.destroy.url({
                    purpose: 'oauth.revoke',
                    grant: target.id,
                }),
                'DELETE',
            );
            setOAuthGrantList((current) =>
                current.map((grant) =>
                    grant.id === target.id
                        ? { ...grant, status: 'revoked' }
                        : grant,
                ),
            );
            setOAuthRevokeTarget(null);
        } catch (caught) {
            setError(
                caught instanceof Error
                    ? caught.message
                    : 'Não foi possível revogar o acesso OAuth.',
            );
        } finally {
            setPendingOperation(null);
        }
    };

    const handleCopySecret = async () => {
        if (!secret) {
            return;
        }

        try {
            await navigator.clipboard.writeText(secret);
            setSecretCopied(true);
            setCopyError(false);
        } catch {
            setCopyError(true);
        }
    };

    const dismissSecret = () => {
        setSecret(null);
        setSecretCopied(false);
        setCopyError(false);
    };

    if (!canManage) {
        return null;
    }

    return (
        <section
            aria-labelledby="integration-credentials-heading"
            className="space-y-5 border-t border-border pt-6"
        >
            <Heading
                variant="small"
                title="Credenciais para API e integrações"
                description="Escolha a menor permissão necessária. Leituras aprovadas podem ser combinadas; escrita continua separada e exige aprovação humana."
            />
            <h2 id="integration-credentials-heading" className="sr-only">
                Credenciais para API e integrações
            </h2>

            <div className="rounded-xl border border-border bg-card p-4 sm:p-5">
                <div className="flex items-start gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <ShieldCheck aria-hidden="true" className="size-5" />
                    </div>
                    <div className="min-w-0 flex-1 space-y-1">
                        <p className="font-medium">Permissões disponíveis</p>
                        <p className="text-sm text-muted-foreground">
                            <code>context:read</code> confirma o vínculo
                            administrativo. <code>catalog:read</code> lê nomes,
                            preço e duração do catálogo. <code>setup:read</code>{' '}
                            lê indicadores mínimos de configuração. Essas
                            leituras podem ser combinadas.{' '}
                            <code>operations:propose</code> envia propostas de
                            alteração sem executá-las.
                        </p>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <Badge variant="outline" className="font-mono">
                                context:read
                            </Badge>
                            <Badge variant="outline" className="font-mono">
                                operations:propose
                            </Badge>
                            <Badge variant="outline" className="font-mono">
                                catalog:read
                            </Badge>
                            <Badge variant="outline" className="font-mono">
                                setup:read
                            </Badge>
                        </div>
                        <p className="mt-3 text-sm text-muted-foreground">
                            Com <code>operations:propose</code>, a integração
                            pode propor criação e edição de categorias,
                            profissionais e serviços, além de alterar nome, fuso
                            horário e opções de agendamento da unidade.
                            Disponibilidade, bloqueios de agenda e publicação do
                            site de agendamento ficam disponíveis somente nos
                            fluxos internos autorizados da plataforma.
                        </p>
                    </div>
                </div>

                {!passkeySupported && (
                    <p
                        className="mt-4 rounded-lg border border-border bg-muted/50 p-3 text-sm text-muted-foreground"
                        role="status"
                    >
                        Este navegador não oferece suporte a passkeys. Use um
                        navegador compatível para criar ou revogar credenciais.
                    </p>
                )}

                {hasIntegrationPasskey === false && (
                    <p
                        className="mt-4 rounded-lg border border-border bg-muted/50 p-3 text-sm text-muted-foreground"
                        role="status"
                    >
                        Cadastre uma passkey antes de criar ou revogar
                        credenciais.{' '}
                        <Link
                            href={securitySettings()}
                            className="font-medium text-foreground underline underline-offset-4"
                        >
                            Abrir configurações de segurança
                        </Link>
                    </p>
                )}

                <form onSubmit={handleIssue} className="mt-5 grid gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="integration-credential-label">
                            Nome da credencial
                        </Label>
                        <Input
                            id="integration-credential-label"
                            autoComplete="off"
                            maxLength={80}
                            value={label}
                            onChange={(event) => setLabel(event.target.value)}
                            placeholder="Ex.: Verificação de contexto — produção"
                            required
                            disabled={
                                !passkeySupported || pendingOperation !== null
                            }
                            aria-describedby="integration-credential-label-help"
                        />
                        <p
                            id="integration-credential-label-help"
                            className="text-xs text-muted-foreground"
                        >
                            O nome ajuda você a reconhecer e revogar esta
                            credencial depois.
                        </p>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="integration-credential-capability">
                            Permissão desta credencial
                        </Label>
                        <select
                            id="integration-credential-capability"
                            value={capabilitySet}
                            onChange={(event) =>
                                setCapabilitySet(
                                    event.target.value as CapabilitySet,
                                )
                            }
                            disabled={
                                !passkeySupported || pendingOperation !== null
                            }
                            className="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            aria-describedby="integration-credential-capability-help"
                        >
                            <option value="context:read">
                                Somente leitura do contexto (context:read)
                            </option>
                            <option value="catalog:read">
                                Leitura do catálogo (catalog:read)
                            </option>
                            <option value="setup:read">
                                Leitura do setup (setup:read)
                            </option>
                            <option value="catalog:read+setup:read">
                                Catálogo + setup (somente leitura)
                            </option>
                            <option value="context:read+catalog:read+setup:read">
                                Contexto + catálogo + setup (somente leitura)
                            </option>
                            <option value="operations:propose">
                                Propor alterações de serviços, categorias,
                                profissionais e unidade (operations:propose)
                            </option>
                        </select>
                        <p
                            id="integration-credential-capability-help"
                            className="text-xs text-muted-foreground"
                        >
                            {capabilitySet === 'operations:propose'
                                ? 'Permite propor criação e edição de categorias, profissionais e serviços, além de alterações de nome, fuso horário e opções de agendamento da unidade. Cada proposta precisa ser revisada e confirmada por um administrador com passkey; a integração nunca executa a alteração diretamente.'
                                : capabilitySet.includes('+')
                                  ? 'Combina apenas leituras aprovadas. Não altera dados nem acessa clientes, contatos, notas ou vendas.'
                                  : 'Permissão somente leitura. Não altera dados nem acessa clientes, contatos, notas ou vendas.'}
                        </p>
                    </div>
                    {capabilitySet === 'operations:propose' && (
                        <p
                            className="rounded-lg border border-amber-500/40 bg-amber-500/5 p-3 text-sm"
                            role="status"
                        >
                            Esta credencial pode enviar propostas, mas não
                            aprová-las nem executá-las. Um administrador deverá
                            revisar e confirmar cada proposta com passkey.
                        </p>
                    )}
                    <Button
                        type="submit"
                        className="sm:justify-self-start"
                        disabled={
                            !passkeySupported ||
                            !hasIntegrationPasskey ||
                            pendingOperation !== null ||
                            !label.trim()
                        }
                    >
                        {pendingOperation === 'issue' ? (
                            <Spinner aria-hidden="true" />
                        ) : (
                            <Plus aria-hidden="true" />
                        )}
                        Criar credencial
                    </Button>
                </form>

                {error && (
                    <InputError className="mt-3" message={error} role="alert" />
                )}
                <p className="mt-3 flex items-start gap-2 text-xs text-muted-foreground">
                    <KeyRound
                        aria-hidden="true"
                        className="mt-0.5 size-3.5 shrink-0"
                    />
                    A criação e a revogação exigem confirmação com sua passkey.
                    O segredo será mostrado uma única vez.
                </p>
            </div>

            <div className="space-y-3">
                <div className="flex items-center justify-between gap-3">
                    <h3 className="font-medium">Credenciais emitidas</h3>
                    <span className="text-sm text-muted-foreground">
                        {credentials.length}{' '}
                        {credentials.length === 1
                            ? 'credencial'
                            : 'credenciais'}
                    </span>
                </div>

                {credentials.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border px-5 py-8 text-center">
                        <KeyRound
                            aria-hidden="true"
                            className="mx-auto size-6 text-muted-foreground"
                        />
                        <p className="mt-3 font-medium">
                            Nenhuma credencial criada
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Crie uma credencial de leitura mínima ou uma
                            credencial que possa propor alterações, sujeita a
                            aprovação administrativa com passkey.
                        </p>
                    </div>
                ) : (
                    <ul className="grid gap-3">
                        {credentials.map((credential) => {
                            const active = statusLabel(credential) === 'Ativa';

                            return (
                                <li
                                    key={credential.id}
                                    className="rounded-xl border border-border bg-card p-4 sm:p-5"
                                >
                                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="min-w-0 space-y-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h4 className="font-medium break-words">
                                                    {credential.label}
                                                </h4>
                                                <Badge
                                                    variant={
                                                        active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {statusLabel(credential)}
                                                </Badge>
                                            </div>
                                            <div className="grid gap-x-5 gap-y-1 text-sm text-muted-foreground sm:grid-cols-2">
                                                <p>
                                                    Criada:{' '}
                                                    {formatDate(
                                                        credential.created_at,
                                                    )}
                                                </p>
                                                <p>
                                                    Expira:{' '}
                                                    {formatDate(
                                                        credential.expires_at,
                                                    )}
                                                </p>
                                                <p className="flex items-center gap-1.5">
                                                    <Activity
                                                        aria-hidden="true"
                                                        className="size-3.5"
                                                    />{' '}
                                                    Último uso:{' '}
                                                    {formatDate(
                                                        credential.last_used_at,
                                                    )}
                                                </p>
                                                <p className="font-mono text-xs">
                                                    {credential.capabilities.join(
                                                        ', ',
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        {active && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="w-full text-destructive hover:text-destructive sm:w-auto"
                                                disabled={
                                                    !passkeySupported ||
                                                    !hasIntegrationPasskey ||
                                                    pendingOperation !== null
                                                }
                                                onClick={() =>
                                                    setRevokeTarget(credential)
                                                }
                                            >
                                                {pendingOperation ===
                                                credential.id ? (
                                                    <Spinner aria-hidden="true" />
                                                ) : (
                                                    <Trash2 aria-hidden="true" />
                                                )}
                                                Revogar
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            <div className="space-y-3">
                <div className="flex items-center justify-between gap-3">
                    <h3 className="font-medium">Acessos OAuth autorizados</h3>
                    <span className="text-sm text-muted-foreground">
                        {oauthGrantList.length}{' '}
                        {oauthGrantList.length === 1 ? 'acesso' : 'acessos'}
                    </span>
                </div>
                {oauthGrantList.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-border px-5 py-6 text-center text-sm text-muted-foreground">
                        Nenhum acesso OAuth autorizado.
                    </p>
                ) : (
                    <ul className="grid gap-3">
                        {oauthGrantList.map((grant) => {
                            const active = grant.status === 'active';

                            return (
                                <li
                                    key={grant.id}
                                    className="rounded-xl border border-border bg-card p-4 sm:p-5"
                                >
                                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="min-w-0 space-y-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h4 className="font-medium break-words">
                                                    {grant.client_name}
                                                </h4>
                                                <Badge
                                                    variant={
                                                        active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {active
                                                        ? 'Ativo'
                                                        : grant.status ===
                                                            'expired'
                                                          ? 'Expirado'
                                                          : 'Revogado'}
                                                </Badge>
                                            </div>
                                            <div className="grid gap-x-5 gap-y-1 text-sm text-muted-foreground sm:grid-cols-2">
                                                <p>
                                                    Autorizado:{' '}
                                                    {formatDate(
                                                        grant.created_at,
                                                    )}
                                                </p>
                                                <p>
                                                    Expira:{' '}
                                                    {formatDate(
                                                        grant.expires_at,
                                                    )}
                                                </p>
                                                <p>
                                                    Último uso:{' '}
                                                    {formatDate(
                                                        grant.last_used_at,
                                                    )}
                                                </p>
                                                <p className="font-mono text-xs">
                                                    {grant.capabilities.join(
                                                        ', ',
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        {active && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="w-full text-destructive hover:text-destructive sm:w-auto"
                                                disabled={
                                                    !passkeySupported ||
                                                    !hasIntegrationPasskey ||
                                                    pendingOperation !== null
                                                }
                                                onClick={() =>
                                                    setOAuthRevokeTarget(grant)
                                                }
                                            >
                                                {pendingOperation ===
                                                grant.id ? (
                                                    <Spinner aria-hidden="true" />
                                                ) : (
                                                    <Trash2 aria-hidden="true" />
                                                )}{' '}
                                                Revogar acesso
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            <Dialog
                open={Boolean(oauthRevokeTarget)}
                onOpenChange={(open) => {
                    if (!open && pendingOperation === null) {
                        setOAuthRevokeTarget(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Revogar acesso OAuth?</DialogTitle>
                        <DialogDescription>
                            O cliente {oauthRevokeTarget?.client_name} perderá
                            acesso imediatamente. Tokens de acesso e de
                            atualização serão revogados. A confirmação exige sua
                            passkey.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOAuthRevokeTarget(null)}
                            disabled={pendingOperation !== null}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleOAuthGrantRevoke}
                            disabled={
                                !passkeySupported ||
                                !hasIntegrationPasskey ||
                                pendingOperation !== null
                            }
                        >
                            {oauthRevokeTarget &&
                            pendingOperation === oauthRevokeTarget.id ? (
                                <Spinner aria-hidden="true" />
                            ) : (
                                <Trash2 aria-hidden="true" />
                            )}{' '}
                            Confirmar revogação
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={Boolean(secret)}
                onOpenChange={(open) => {
                    if (!open && secretCopied) {
                        dismissSecret();
                    }
                }}
            >
                <DialogContent
                    onEscapeKeyDown={(event) => {
                        if (!secretCopied) {
                            event.preventDefault();
                        }
                    }}
                    onPointerDownOutside={(event) => {
                        if (!secretCopied) {
                            event.preventDefault();
                        }
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Guarde sua credencial agora</DialogTitle>
                        <DialogDescription>
                            Por segurança, este segredo não poderá ser
                            consultado novamente. Copie-o e salve em um local
                            seguro antes de fechar.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3">
                        <Label htmlFor="issued-integration-secret">
                            Segredo da credencial
                        </Label>
                        <textarea
                            id="issued-integration-secret"
                            readOnly
                            value={secret ?? ''}
                            rows={3}
                            className="w-full resize-y rounded-md border border-input bg-muted p-3 font-mono text-sm break-all focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            onFocus={(event) => event.currentTarget.select()}
                            onCopy={() => {
                                setSecretCopied(true);
                                setCopyError(false);
                            }}
                        />
                        {copyError && (
                            <p
                                className="text-sm text-destructive"
                                role="alert"
                            >
                                Não foi possível copiar. Selecione e copie o
                                segredo antes de fechar.
                            </p>
                        )}
                        {secretCopied && (
                            <p
                                className="flex items-center gap-2 text-sm text-green-700 dark:text-green-400"
                                role="status"
                            >
                                <Check aria-hidden="true" className="size-4" />{' '}
                                Segredo copiado para a área de transferência.
                            </p>
                        )}
                    </div>
                    <DialogFooter className="flex-col-reverse sm:flex-row">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={dismissSecret}
                            disabled={!secretCopied}
                        >
                            Já guardei
                        </Button>
                        <Button
                            type="button"
                            onClick={() => void handleCopySecret()}
                        >
                            {secretCopied ? (
                                <Check aria-hidden="true" />
                            ) : (
                                <Clipboard aria-hidden="true" />
                            )}
                            {secretCopied
                                ? 'Copiar novamente'
                                : 'Copiar segredo'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={revokeTarget !== null}
                onOpenChange={(open) => {
                    if (!open && pendingOperation === null) {
                        setRevokeTarget(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Revogar credencial?</DialogTitle>
                        <DialogDescription>
                            “{revokeTarget?.label}” perderá o acesso
                            imediatamente. Essa ação não pode ser desfeita.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="flex-col-reverse sm:flex-row">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRevokeTarget(null)}
                            disabled={pendingOperation !== null}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => void handleRevoke()}
                            disabled={
                                pendingOperation !== null ||
                                !passkeySupported ||
                                !hasIntegrationPasskey
                            }
                        >
                            {pendingOperation === revokeTarget?.id ? (
                                <Spinner aria-hidden="true" />
                            ) : (
                                <Trash2 aria-hidden="true" />
                            )}
                            Confirmar com passkey
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
