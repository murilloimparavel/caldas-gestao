import { useState } from 'react';
import { ImagePlus, Monitor, Smartphone, UserRound } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { bookingTokens } from './design-tokens';
import type { BookingItem, OnlineBookingProps, PublicSettings } from '../types';

export function PublicPreview({
    unit,
    settings,
    gallery,
    services,
    previewUrl,
}: {
    unit: OnlineBookingProps['unit'];
    settings: PublicSettings;
    gallery: NonNullable<OnlineBookingProps['gallery']>;
    services: BookingItem[];
    previewUrl?: string | null;
}) {
    const [viewport, setViewport] = useState<'mobile' | 'desktop'>('mobile');
    const coverUrl =
        settings.cover_url ?? settings.cover_image_url ?? settings.logo_url;
    const visibleServices = services.filter(
        (item) => item.status === 'active' && item.online_booking_enabled,
    );

    if (previewUrl) {
        return (
            <Card className="border-primary/20 shadow-sm xl:sticky xl:top-20">
                <CardHeader className="border-b border-border/60 bg-muted/20 pb-4">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <CardTitle
                                className={bookingTokens.type.sectionTitle}
                            >
                                Prévia pública
                            </CardTitle>
                            <CardDescription className="mt-1">
                                Renderização real do rascunho atual.
                            </CardDescription>
                        </div>
                        <div
                            className={`flex items-center gap-1 ${bookingTokens.radius.icon} border border-border/70 p-1`}
                            role="group"
                            aria-label="Tamanho da prévia"
                        >
                            <button
                                type="button"
                                aria-pressed={viewport === 'mobile'}
                                onClick={() => setViewport('mobile')}
                                className={`rounded-md px-2 py-1 text-xs ${viewport === 'mobile' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'}`}
                            >
                                Mobile
                            </button>
                            <button
                                type="button"
                                aria-pressed={viewport === 'desktop'}
                                onClick={() => setViewport('desktop')}
                                className={`rounded-md px-2 py-1 text-xs ${viewport === 'desktop' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'}`}
                            >
                                Desktop
                            </button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="flex justify-center bg-muted/10 p-3">
                    <iframe
                        title="Prévia pública do agendamento online"
                        src={previewUrl}
                        className={`h-[36rem] rounded-2xl border border-border bg-background transition-[width] ${viewport === 'mobile' ? 'w-[20rem]' : 'w-full'}`}
                    />
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="border-primary/20 shadow-sm xl:sticky xl:top-20 xl:max-h-[calc(100dvh-6rem)] xl:overflow-y-auto">
            <CardHeader className="border-b border-border/60 bg-muted/20 pb-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <CardTitle className={bookingTokens.type.sectionTitle}>
                            Prévia pública
                        </CardTitle>
                        <CardDescription className="mt-1">
                            Veja como o celular do cliente exibirá a unidade.
                        </CardDescription>
                    </div>
                    <div
                        className={`flex items-center gap-1 ${bookingTokens.radius.icon} border border-border/70 p-1`}
                        role="group"
                        aria-label="Tamanho da prévia"
                    >
                        <button
                            type="button"
                            aria-pressed={viewport === 'mobile'}
                            aria-label="Prévia em celular"
                            onClick={() => setViewport('mobile')}
                            className={`rounded-md p-1.5 ${viewport === 'mobile' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'}`}
                        >
                            <Smartphone aria-hidden="true" className="size-4" />
                        </button>
                        <button
                            type="button"
                            aria-pressed={viewport === 'desktop'}
                            aria-label="Prévia em desktop"
                            onClick={() => setViewport('desktop')}
                            className={`rounded-md p-1.5 ${viewport === 'desktop' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'}`}
                        >
                            <Monitor aria-hidden="true" className="size-4" />
                        </button>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="flex justify-center bg-muted/10 p-5">
                <div
                    className={`w-full rounded-[2rem] border-[7px] border-slate-950 bg-slate-950 p-1 shadow-2xl transition-[max-width] dark:border-slate-700 ${viewport === 'mobile' ? 'max-w-[18rem]' : 'max-w-[42rem]'}`}
                >
                    <div
                        className={`relative flex flex-col overflow-hidden rounded-[1.45rem] bg-background ${viewport === 'mobile' ? 'h-[33rem]' : 'h-[28rem]'}`}
                    >
                        <div className="flex items-center justify-between border-b border-border/70 px-4 py-3">
                            <span className="max-w-[12rem] truncate text-xs font-semibold">
                                {unit.name}
                            </span>
                            <span className="flex size-6 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <UserRound
                                    aria-hidden="true"
                                    className="size-3.5"
                                />
                            </span>
                        </div>
                        <div className="flex gap-4 overflow-hidden border-b border-border/70 px-4 pt-3 text-3xs font-semibold text-muted-foreground">
                            <span className="border-b-2 border-primary pb-2 text-foreground">
                                Detalhes
                            </span>
                            <span className="pb-2">Serviços</span>
                            <span className="pb-2">Profissionais</span>
                            <span className="pb-2">Avaliações</span>
                        </div>
                        <div className="min-h-0 flex-1 overflow-hidden">
                            {coverUrl ? (
                                <img
                                    src={coverUrl}
                                    alt=""
                                    className="h-36 w-full object-cover"
                                />
                            ) : gallery[0]?.url ? (
                                <img
                                    src={gallery[0].url}
                                    alt=""
                                    className="h-36 w-full object-cover"
                                />
                            ) : (
                                <div className="flex h-36 items-center justify-center bg-primary/10 text-primary">
                                    <ImagePlus
                                        aria-hidden="true"
                                        className="size-8"
                                    />
                                </div>
                            )}
                            <div className="space-y-4 px-4 py-4">
                                <div>
                                    <p className="text-sm font-bold">
                                        {unit.name}
                                    </p>
                                    <p className="mt-1 line-clamp-2 text-3xs leading-4 text-muted-foreground">
                                        {settings.description ||
                                            'Escolha um serviço e reserve seu horário.'}
                                    </p>
                                </div>
                                <div
                                    className={`${bookingTokens.radius.control} border border-border/70 ${bookingTokens.space.control}`}
                                >
                                    <p className="text-3xs font-bold">
                                        Contato
                                    </p>
                                    <p className="mt-1 text-3xs text-muted-foreground">
                                        {settings.whatsapp ||
                                            settings.phone ||
                                            'WhatsApp não informado'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-3xs font-bold">
                                        Serviços
                                    </p>
                                    <div className="mt-2 space-y-2">
                                        {visibleServices
                                            .slice(0, 3)
                                            .map((service) => (
                                                <div
                                                    key={service.id}
                                                    className={`flex items-center justify-between gap-2 ${bookingTokens.radius.icon} border border-border/70 px-2.5 py-2`}
                                                >
                                                    <span className="truncate text-3xs font-medium">
                                                        {service.name}
                                                    </span>
                                                    {service.price_cents !==
                                                        undefined && (
                                                        <span className="shrink-0 text-2xs text-muted-foreground">
                                                            {(
                                                                service.price_cents /
                                                                100
                                                            ).toLocaleString(
                                                                'pt-BR',
                                                                {
                                                                    style: 'currency',
                                                                    currency:
                                                                        'BRL',
                                                                },
                                                            )}
                                                        </span>
                                                    )}
                                                </div>
                                            ))}
                                        {!visibleServices.length && (
                                            <p className="text-3xs text-muted-foreground">
                                                Cadastre um serviço ativo.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="border-t border-border/70 bg-background p-3">
                            <div
                                className={`${bookingTokens.radius.control} bg-primary px-4 py-2.5 text-center ${bookingTokens.type.caption} font-semibold text-primary-foreground ${bookingTokens.shadow.section}`}
                            >
                                Agendar agora
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
