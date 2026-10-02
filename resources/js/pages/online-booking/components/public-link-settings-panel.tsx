import { Check, ExternalLink, Link2, Copy } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import tenantDomains from '@/routes/tenant-domains';
import { Field, SectionCard } from './booking-form-primitives';
import type { PublicSettings } from '../types';

export function PublicLinkSettingsPanel({
    settings,
    unitSlug,
    publicDomains,
    publicUrl,
    copied,
    onCopy,
}: {
    settings: PublicSettings;
    unitSlug: string;
    publicDomains: { id: string; hostname: string }[];
    publicUrl: string | null;
    copied: boolean;
    onCopy: (url: string | null) => void;
}) {
    const [selectedDomainId, setSelectedDomainId] = useState(
        settings.public_domain_id ?? '',
    );
    const selectedDomain = publicDomains.find(
        (domain) => domain.id === selectedDomainId,
    );
    const publicSlug = settings.public_slug ?? unitSlug;
    const displayedPublicUrl = selectedDomain
        ? `https://${selectedDomain.hostname}/`
        : publicUrl;

    return (
        <SectionCard
            icon={Link2}
            title="Link público"
            description="Compartilhe este endereço em redes sociais e mensagens."
        >
            {selectedDomain ? (
                <div className="space-y-2">
                    <input
                        type="hidden"
                        name="public_slug"
                        value={publicSlug}
                    />
                    <p className="text-sm text-muted-foreground">
                        O domínio personalizado usa a raiz do domínio. O nome de
                        usuário fica reservado para o link gratuito.
                    </p>
                </div>
            ) : (
                <Field
                    label="Nome de usuário"
                    name="public_slug"
                    defaultValue={publicSlug}
                    placeholder="minha-unidade"
                />
            )}
            <div className="space-y-2">
                <Label htmlFor="public_domain_id">Domínio do link</Label>
                <select
                    id="public_domain_id"
                    name="public_domain_id"
                    value={selectedDomainId}
                    onChange={(event) =>
                        setSelectedDomainId(event.target.value)
                    }
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    disabled={publicDomains.length === 0}
                >
                    <option value="">Domínio padrão do sistema</option>
                    {publicDomains.map((domain) => (
                        <option key={domain.id} value={domain.id}>
                            {domain.hostname}
                        </option>
                    ))}
                </select>
                {publicDomains.length > 0 ? (
                    <p className="text-xs text-muted-foreground">
                        O link será aberto neste domínio público ativo.
                    </p>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        Cadastre e ative um domínio público em{' '}
                        <a
                            className="text-primary underline underline-offset-4"
                            href={tenantDomains.index.url()}
                        >
                            Configurações → Domínios
                        </a>{' '}
                        para personalizar este link.
                    </p>
                )}
            </div>
            <div className="rounded-xl border border-border bg-muted/40 px-4 py-3 font-mono text-sm break-all text-muted-foreground">
                {displayedPublicUrl ??
                    'Disponível quando a unidade estiver pronta para publicar.'}
            </div>
            <div className="flex flex-col gap-2 sm:flex-row">
                <Button
                    type="button"
                    variant="outline"
                    className="flex-1"
                    onClick={() => onCopy(displayedPublicUrl)}
                    disabled={!displayedPublicUrl}
                >
                    {copied ? (
                        <Check aria-hidden="true" />
                    ) : (
                        <Copy aria-hidden="true" />
                    )}
                    {copied ? 'Copiado' : 'Copiar link'}
                </Button>
                {displayedPublicUrl ? (
                    <Button
                        type="button"
                        variant="secondary"
                        className="flex-1"
                        asChild
                    >
                        <a
                            href={displayedPublicUrl}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <ExternalLink aria-hidden="true" />
                            Abrir página
                        </a>
                    </Button>
                ) : (
                    <Button
                        type="button"
                        variant="secondary"
                        className="flex-1"
                        disabled
                    >
                        <ExternalLink aria-hidden="true" />
                        Abrir página
                    </Button>
                )}
            </div>
        </SectionCard>
    );
}
