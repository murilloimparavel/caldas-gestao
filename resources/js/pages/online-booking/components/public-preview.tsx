import { useState } from 'react';
import { Monitor, Smartphone, Telescope } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { bookingTokens } from './design-tokens';

export function PublicPreview({ previewUrl }: { previewUrl?: string | null }) {
    const [viewport, setViewport] = useState<'mobile' | 'desktop'>('mobile');

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
                        className={`h-[36rem] rounded-2xl border border-border bg-background transition-[width] ${viewport === 'mobile' ? 'w-full max-w-[20rem]' : 'w-full'}`}
                    />
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="border-primary/20 shadow-sm xl:sticky xl:top-20">
            <CardHeader className="border-b border-border/60 bg-muted/20 pb-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <CardTitle className={bookingTokens.type.sectionTitle}>
                            Prévia pública
                        </CardTitle>
                        <CardDescription className="mt-1">
                            A prévia mostra a página real do agendamento.
                        </CardDescription>
                    </div>
                    <div
                        className={`flex shrink-0 items-center gap-1 ${bookingTokens.radius.icon} border border-border/70 p-1`}
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
            <CardContent className="flex justify-center bg-muted/10 p-4 sm:p-6">
                <div
                    role="status"
                    className={`flex min-h-64 w-full flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-background px-5 py-8 text-center transition-[max-width] sm:px-8 ${viewport === 'mobile' ? 'max-w-sm' : 'max-w-3xl'}`}
                >
                    <div className="mb-4 flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <Telescope aria-hidden="true" className="size-6" />
                    </div>
                    <h3 className="text-sm font-semibold text-foreground sm:text-base">
                        Sua prévia fiel está quase pronta
                    </h3>
                    <p className="mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                        Salve o primeiro rascunho para carregar aqui a página
                        pública real, sem uma simulação que possa divergir do
                        agendamento.
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}
