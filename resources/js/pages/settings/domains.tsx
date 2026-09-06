import { Form, Head } from '@inertiajs/react';
import { CheckCircle2, Copy, Globe2, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { PageCanvas, ResourceHeader } from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import tenantDomains from '@/routes/tenant-domains';

type Domain = {
    id: string;
    hostname: string;
    kind: 'management' | 'public';
    status: 'pending' | 'verified' | 'active' | 'disabled' | 'suspended';
    verification_token: string;
    expected_cname: string;
    last_dns_error?: string | null;
};

type Props = { domains: Domain[]; expectedCname: string };

const statusLabels: Record<Domain['status'], string> = {
    pending: 'Aguardando DNS',
    verified: 'DNS verificado',
    active: 'Ativo',
    disabled: 'Desativado',
    suspended: 'Suspenso',
};

export default function Domains({ domains, expectedCname }: Props) {
    const [copied, setCopied] = useState<string | null>(null);
    const copy = async (value: string) => {
        await navigator.clipboard.writeText(value);
        setCopied(value);
        window.setTimeout(() => setCopied(null), 1500);
    };

    return (
        <PageCanvas>
            <Head title="Domínios personalizados" />
            <ResourceHeader
                eyebrow="Personalização"
                title="Domínio próprio"
                description="Use o painel do seu negócio em um endereço personalizado."
                icon={Globe2}
            />
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <Card>
                    <CardHeader>
                        <CardTitle>Domínios cadastrados</CardTitle>
                        <CardDescription>
                            O domínio só ficará ativo depois da confirmação do
                            DNS e do certificado SSL.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {domains.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nenhum domínio personalizado cadastrado.
                            </p>
                        ) : (
                            domains.map((domain) => (
                                <div
                                    key={domain.id}
                                    className="grid gap-3 rounded-xl border p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {domain.hostname}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {domain.last_dns_error ??
                                                'Configure os registros indicados ao lado.'}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={
                                                domain.status === 'active'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {statusLabels[domain.status]}
                                        </Badge>
                                        <Form
                                            {...tenantDomains.verify.form(
                                                domain.id,
                                            )}
                                        >
                                            <Button
                                                type="submit"
                                                size="sm"
                                                variant="outline"
                                            >
                                                <RefreshCw className="mr-2 size-4" />
                                                Verificar
                                            </Button>
                                        </Form>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
                <div className="grid content-start gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Cadastrar domínio</CardTitle>
                            <CardDescription>
                                Use um subdomínio, por exemplo
                                `gestao.seunegocio.com`.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...tenantDomains.store.form()}
                                className="grid gap-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="hostname">
                                                Hostname
                                            </Label>
                                            <Input
                                                id="hostname"
                                                name="hostname"
                                                placeholder="gestao.seunegocio.com"
                                                required
                                            />
                                            {errors.hostname && (
                                                <p className="text-sm text-destructive">
                                                    {errors.hostname}
                                                </p>
                                            )}
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Cadastrar domínio
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Configuração DNS</CardTitle>
                            <CardDescription>
                                Após cadastrar, crie estes registros no provedor
                                do domínio.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 text-sm">
                            <DnsValue
                                label="CNAME"
                                value={`gestao → ${expectedCname}`}
                                copy={copy}
                                copied={copied}
                            />
                            <p className="text-muted-foreground">
                                Para validar a posse, crie também o TXT mostrado
                                no domínio cadastrado.
                            </p>
                            {domains.map((domain) => (
                                <div
                                    key={domain.id}
                                    className="grid gap-2 rounded-lg border border-dashed p-3"
                                >
                                    <span className="text-xs font-medium text-muted-foreground">
                                        TXT para {domain.hostname}
                                    </span>
                                    <DnsValue
                                        label="_caldas-gestao-verification"
                                        value={domain.verification_token}
                                        copy={copy}
                                        copied={copied}
                                    />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </PageCanvas>
    );
}

function DnsValue({
    label,
    value,
    copy,
    copied,
}: {
    label: string;
    value: string;
    copy: (value: string) => void;
    copied: string | null;
}) {
    return (
        <div className="grid gap-1">
            <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </span>
            <button
                type="button"
                onClick={() => copy(value)}
                className="flex items-center justify-between gap-3 rounded-lg border bg-muted/30 p-3 text-left font-mono text-xs hover:bg-muted/60"
            >
                <span>{value}</span>
                {copied === value ? (
                    <CheckCircle2 className="size-4 text-emerald-600" />
                ) : (
                    <Copy className="size-4" />
                )}
            </button>
        </div>
    );
}
