import { Head, router } from '@inertiajs/react';
import { Check, Copy, Link2, Plus, Share2, Trash2 } from 'lucide-react';
import QRCode from 'qrcode';
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
import onlineBooking from '@/routes/online_booking';

type CampaignLink = {
    id: string;
    name: string;
    utm_source: string;
    utm_medium: string;
    utm_campaign: string;
    utm_term?: string | null;
    utm_content?: string | null;
    url: string;
    is_active: boolean;
    visits_count: number;
    appointments_count: number;
};

type Props = { campaignLinks: CampaignLink[] };

export default function CampaignLinks({ campaignLinks }: Props) {
    const [copiedId, setCopiedId] = useState<string | null>(null);
    const [qrLinkId, setQrLinkId] = useState<string | null>(null);
    const [qrDataUrl, setQrDataUrl] = useState<string | null>(null);
    const copy = async (link: CampaignLink): Promise<void> => {
        await navigator.clipboard.writeText(link.url);
        setCopiedId(link.id);
        window.setTimeout(() => setCopiedId(null), 1800);
    };
    const toggleQr = async (link: CampaignLink): Promise<void> => {
        if (qrLinkId === link.id) {
            setQrLinkId(null);
            setQrDataUrl(null);

            return;
        }

        const dataUrl = await QRCode.toDataURL(link.url, {
            errorCorrectionLevel: 'M',
            margin: 2,
            width: 256,
        });
        setQrLinkId(link.id);
        setQrDataUrl(dataUrl);
    };
    const toggle = (link: CampaignLink): void => {
        router.patch(
            onlineBooking.campaign_links.toggle.url(link.id),
            {},
            { preserveScroll: true },
        );
    };
    const remove = (link: CampaignLink): void => {
        if (window.confirm(`Excluir o link “${link.name}”?`)) {
            router.delete(onlineBooking.campaign_links.destroy.url(link.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="Links de divulgação" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Agendamento online"
                    title="Links de divulgação"
                    description="Crie links rastreáveis para entender de onde vêm seus agendamentos."
                    status={
                        <Badge variant="outline">
                            <Share2
                                aria-hidden="true"
                                className="mr-1 size-3"
                            />
                            UTM
                        </Badge>
                    }
                />
                <div className="grid gap-5 lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Novo link
                            </CardTitle>
                            <CardDescription>
                                O endereço oficial continua limpo; os parâmetros
                                ficam apenas neste link.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                action={onlineBooking.campaign_links.store.url()}
                                method="post"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    const data = new FormData(
                                        event.currentTarget,
                                    );
                                    router.post(
                                        onlineBooking.campaign_links.store.url(),
                                        Object.fromEntries(data.entries()),
                                        {
                                            preserveScroll: true,
                                            onSuccess: () =>
                                                event.currentTarget.reset(),
                                        },
                                    );
                                }}
                                className="space-y-4"
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="campaign-name">
                                        Nome interno
                                    </Label>
                                    <Input
                                        id="campaign-name"
                                        name="name"
                                        placeholder="Instagram — bio"
                                        required
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="utm-source">
                                            Origem
                                        </Label>
                                        <Input
                                            id="utm-source"
                                            name="utm_source"
                                            placeholder="instagram"
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="utm-medium">
                                            Canal
                                        </Label>
                                        <Input
                                            id="utm-medium"
                                            name="utm_medium"
                                            placeholder="social"
                                            required
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="utm-campaign">
                                        Campanha
                                    </Label>
                                    <Input
                                        id="utm-campaign"
                                        name="utm_campaign"
                                        placeholder="setembro"
                                        required
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="utm-term">
                                            Termo{' '}
                                            <span className="text-muted-foreground">
                                                (opcional)
                                            </span>
                                        </Label>
                                        <Input id="utm-term" name="utm_term" />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="utm-content">
                                            Variação{' '}
                                            <span className="text-muted-foreground">
                                                (opcional)
                                            </span>
                                        </Label>
                                        <Input
                                            id="utm-content"
                                            name="utm_content"
                                        />
                                    </div>
                                </div>
                                <Button type="submit" className="w-full">
                                    <Plus aria-hidden="true" />
                                    Criar link
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Links criados
                            </CardTitle>
                            <CardDescription>
                                Copie e compartilhe o link adequado para cada
                                canal.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {campaignLinks.length === 0 ? (
                                <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                    <Link2 className="mx-auto mb-2 size-5" />
                                    Nenhum link de divulgação criado ainda.
                                </div>
                            ) : (
                                campaignLinks.map((link) => (
                                    <div
                                        key={link.id}
                                        className="rounded-xl border p-4"
                                    >
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-medium">
                                                        {link.name}
                                                    </p>
                                                    <Badge
                                                        variant={
                                                            link.is_active
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {link.is_active
                                                            ? 'Ativo'
                                                            : 'Inativo'}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 text-xs break-all text-muted-foreground">
                                                    {link.utm_source} /{' '}
                                                    {link.utm_medium} /{' '}
                                                    {link.utm_campaign}
                                                </p>
                                                <div className="mt-2 flex gap-3 text-xs text-muted-foreground">
                                                    <span>
                                                        {link.visits_count}{' '}
                                                        visitas
                                                    </span>
                                                    <span>
                                                        {
                                                            link.appointments_count
                                                        }{' '}
                                                        agendamentos
                                                    </span>
                                                </div>
                                                <p className="mt-2 rounded-lg bg-muted/40 px-2 py-1.5 text-xs break-all">
                                                    {link.url}
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => copy(link)}
                                            >
                                                {copiedId === link.id ? (
                                                    <Check aria-hidden="true" />
                                                ) : (
                                                    <Copy aria-hidden="true" />
                                                )}
                                                {copiedId === link.id
                                                    ? 'Copiado'
                                                    : 'Copiar'}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    void toggleQr(link)
                                                }
                                            >
                                                QR Code
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => toggle(link)}
                                            >
                                                {link.is_active
                                                    ? 'Desativar'
                                                    : 'Ativar'}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="text-destructive"
                                                onClick={() => remove(link)}
                                            >
                                                <Trash2 aria-hidden="true" />
                                                Excluir
                                            </Button>
                                        </div>
                                        {qrLinkId === link.id && qrDataUrl ? (
                                            <div className="mt-4 flex flex-col items-center gap-3 rounded-xl border border-dashed bg-white p-4">
                                                <img
                                                    src={qrDataUrl}
                                                    alt={`QR Code para ${link.name}`}
                                                    width={256}
                                                    height={256}
                                                />
                                                <a
                                                    className="text-sm font-medium text-primary underline underline-offset-4"
                                                    href={qrDataUrl}
                                                    download={`qr-${link.name.toLowerCase().replace(/[^a-z0-9]+/g, '-')}.png`}
                                                >
                                                    Baixar QR Code
                                                </a>
                                            </div>
                                        ) : null}
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>
            </PageCanvas>
        </>
    );
}
