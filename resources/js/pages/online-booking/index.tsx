import { Form, Head } from '@inertiajs/react';
import {
    Check,
    CheckCircle2,
    ClipboardCheck,
    Copy,
    ExternalLink,
    Globe2,
    Scissors,
    UsersRound,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import {
    FormErrorSummary,
    PageCanvas,
    ResourceHeader,
} from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import onlineBooking from '@/routes/online_booking';

type BookingItem = {
    id: string;
    name: string;
    status: 'active' | 'inactive';
    online_booking_enabled: boolean;
    lock_version: number;
};

type Readiness = {
    unit_enabled: boolean;
    has_active_service: boolean;
    has_active_professional: boolean;
    has_service_professional_pair: boolean;
    publishable: boolean;
};

type Props = {
    unit: {
        id: string;
        name: string;
        slug: string;
        online_booking_enabled: boolean;
        lock_version: number;
    };
    tenant: { slug: string };
    publicUrl: string | null;
    services: BookingItem[];
    professionals: BookingItem[];
    readiness: Readiness;
};

function SelectionCard({ item, group }: { item: BookingItem; group: string }) {
    const inputId = `${group}-${item.id}`;
    const isActive = item.status === 'active';

    return (
        <label
            htmlFor={inputId}
            className={`flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border p-3 transition-colors ${
                isActive
                    ? 'border-border bg-background hover:border-primary/50 has-checked:border-primary/70 has-checked:bg-primary/5'
                    : 'cursor-not-allowed border-border/60 bg-muted/30 opacity-65'
            }`}
        >
            <input
                id={inputId}
                name={`${group}_ids[]`}
                type="checkbox"
                value={item.id}
                defaultChecked={isActive && item.online_booking_enabled}
                disabled={!isActive}
                className="size-5 rounded border-input accent-primary focus-visible:ring-2 focus-visible:ring-ring"
            />
            <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">
                {item.name}
            </span>
            <Badge variant="outline" className="shrink-0 text-[10px]">
                {isActive ? 'Ativo' : 'Inativo'}
            </Badge>
        </label>
    );
}

function ReadinessRow({ label, ready }: { label: string; ready: boolean }) {
    return (
        <li className="flex items-start gap-3 text-sm">
            {ready ? (
                <CheckCircle2
                    aria-hidden="true"
                    className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                />
            ) : (
                <XCircle
                    aria-hidden="true"
                    className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                />
            )}
            <span className={ready ? 'text-foreground' : 'text-muted-foreground'}>
                {label}
            </span>
        </li>
    );
}

export default function OnlineBookingIndex({
    unit,
    publicUrl,
    services,
    professionals,
    readiness,
}: Props) {
    const [copied, setCopied] = useState(false);
    const activeServices = services.filter((item) => item.status === 'active');
    const activeProfessionals = professionals.filter((item) => item.status === 'active');

    const copyPublicUrl = async () => {
        if (!publicUrl || !navigator.clipboard) {
            return;
        }

        await navigator.clipboard.writeText(publicUrl);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2200);
    };

    return (
        <>
            <Head title="Agendamento online" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Gestão"
                    title="Agendamento online"
                    description={`Escolha o que ${unit.name} oferece para clientes e profissionais no canal público.`}
                    status={
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Badge variant={readiness.publishable ? 'default' : 'secondary'}>
                                {readiness.publishable ? 'Publicável' : 'Em preparação'}
                            </Badge>
                            <Badge variant="outline">/{unit.slug}</Badge>
                        </div>
                    }
                />

                <Form
                    {...onlineBooking.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-5"
                >
                    {({ errors, processing, wasSuccessful }) => (
                        <>
                            <FormErrorSummary errors={errors} />
                            {wasSuccessful ? (
                                <div
                                    role="status"
                                    className="flex items-center gap-2 rounded-xl border border-emerald-300/60 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/30 dark:text-emerald-200"
                                >
                                    <CheckCircle2 aria-hidden="true" className="size-4" />
                                    Configurações salvas com sucesso.
                                </div>
                            ) : null}

                            <div className="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.8fr)]">
                                <div className="space-y-5">
                                    <Card>
                                        <CardHeader>
                                            <div className="flex items-start gap-3">
                                                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                                    <Globe2 aria-hidden="true" className="size-5" />
                                                </div>
                                                <div className="space-y-1">
                                                    <CardTitle>Publicar unidade</CardTitle>
                                                    <CardDescription>
                                                        Ative o canal quando catálogo e equipe estiverem prontos.
                                                    </CardDescription>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent>
                                            <label className="flex min-h-14 items-center gap-3 rounded-xl border border-border bg-muted/30 p-3">
                                                <input
                                                    type="hidden"
                                                    name="online_booking_enabled"
                                                    value="0"
                                                />
                                                <input
                                                    id="online_booking_enabled"
                                                    name="online_booking_enabled"
                                                    type="checkbox"
                                                    value="1"
                                                    defaultChecked={unit.online_booking_enabled}
                                                    className="size-5 rounded border-input accent-primary focus-visible:ring-2 focus-visible:ring-ring"
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <span className="block text-sm font-semibold text-foreground">
                                                        Permitir agendamentos públicos
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        Clientes poderão consultar ofertas e solicitar um horário.
                                                    </span>
                                                </span>
                                            </label>
                                        </CardContent>
                                    </Card>

                                    <div className="grid gap-5 md:grid-cols-2">
                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="flex items-center gap-2 text-base">
                                                    <Scissors aria-hidden="true" className="size-4 text-primary" />
                                                    Serviços publicados
                                                </CardTitle>
                                                <CardDescription>
                                                    Apenas serviços ativos podem aparecer no agendamento.
                                                </CardDescription>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="grid gap-2" aria-label="Serviços disponíveis">
                                                    {services.length > 0 ? services.map((item) => (
                                                        <SelectionCard key={item.id} item={item} group="service" />
                                                    )) : (
                                                        <p className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                                                            Cadastre um serviço ativo para começar.
                                                        </p>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>

                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="flex items-center gap-2 text-base">
                                                    <UsersRound aria-hidden="true" className="size-4 text-primary" />
                                                    Profissionais publicados
                                                </CardTitle>
                                                <CardDescription>
                                                    Selecione quem pode receber solicitações online.
                                                </CardDescription>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="grid gap-2" aria-label="Profissionais disponíveis">
                                                    {professionals.length > 0 ? professionals.map((item) => (
                                                        <SelectionCard key={item.id} item={item} group="professional" />
                                                    )) : (
                                                        <p className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                                                            Cadastre um profissional ativo para começar.
                                                        </p>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                </div>

                                <aside className="space-y-5">
                                    <Card className="overflow-hidden">
                                        <CardHeader className="bg-muted/40">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <ClipboardCheck aria-hidden="true" className="size-4 text-primary" />
                                                Checklist de publicação
                                            </CardTitle>
                                            <CardDescription>
                                                Resolva os itens abaixo para liberar o link público.
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="pt-5">
                                            <ul className="space-y-3">
                                                <ReadinessRow label="Unidade habilitada" ready={readiness.unit_enabled} />
                                                <ReadinessRow label={`Serviço ativo selecionado (${activeServices.length})`} ready={readiness.has_active_service} />
                                                <ReadinessRow label={`Profissional ativo selecionado (${activeProfessionals.length})`} ready={readiness.has_active_professional} />
                                                <ReadinessRow label="Existe serviço e profissional compatíveis" ready={readiness.has_service_professional_pair} />
                                            </ul>
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-base">Link público</CardTitle>
                                            <CardDescription>
                                                Compartilhe este endereço em redes sociais e mensagens.
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            <div className="rounded-lg border border-border bg-muted/40 px-3 py-2 font-mono text-xs break-all text-muted-foreground">
                                                {publicUrl ?? 'Disponível quando a unidade estiver pronta para publicar.'}
                                            </div>
                                            <div className="flex flex-col gap-2 sm:flex-row">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="flex-1"
                                                    onClick={copyPublicUrl}
                                                    disabled={!publicUrl}
                                                >
                                                    {copied ? <Check aria-hidden="true" /> : <Copy aria-hidden="true" />}
                                                    {copied ? 'Copiado' : 'Copiar link'}
                                                </Button>
                                                {publicUrl ? (
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                        className="flex-1"
                                                        asChild
                                                    >
                                                        <a href={publicUrl} target="_blank" rel="noreferrer">
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
                                            <p aria-live="polite" className="min-h-5 text-xs text-muted-foreground">
                                                {copied ? 'Link copiado para a área de transferência.' : !publicUrl ? 'Selecione um serviço e um profissional compatíveis para habilitar o link.' : ''}
                                            </p>
                                        </CardContent>
                                    </Card>
                                </aside>
                            </div>

                            <input type="hidden" name="lock_version" value={unit.lock_version} />
                            <div className="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:items-center sm:justify-end">
                                <p className="mr-auto text-xs text-muted-foreground">
                                    As alterações serão aplicadas à unidade atual.
                                </p>
                                <Button type="submit" disabled={processing} className="w-full sm:w-auto">
                                    {processing ? 'Salvando…' : 'Salvar configurações'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </PageCanvas>
        </>
    );
}
