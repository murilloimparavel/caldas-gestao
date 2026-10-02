import { useEffect, useState } from 'react';
import { Monitor, Smartphone, Telescope } from 'lucide-react';
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
} from '@/components/ui/dialog';
import { bookingTokens } from './design-tokens';

const DESKTOP_PREVIEW_WIDTH = 1280;
const PREVIEW_LOAD_TIMEOUT_MS = 15_000;

type Viewport = 'mobile' | 'desktop';
type PreviewIssue = 'error' | 'slow' | null;

export function PublicPreview({ previewUrl }: { previewUrl?: string | null }) {
    const [viewport, setViewport] = useState<Viewport>('mobile');
    const [desktopPreviewOpen, setDesktopPreviewOpen] = useState(false);
    const [frameLoaded, setFrameLoaded] = useState(false);
    const [frameIssue, setFrameIssue] = useState<PreviewIssue>(null);
    const [frameAttempt, setFrameAttempt] = useState(0);

    useEffect(() => {
        const previewMounted = viewport === 'mobile' || desktopPreviewOpen;

        if (!previewUrl || !previewMounted || frameLoaded || frameIssue) {
            return;
        }

        const timeout = window.setTimeout(() => {
            setFrameIssue('slow');
        }, PREVIEW_LOAD_TIMEOUT_MS);

        return () => window.clearTimeout(timeout);
    }, [
        desktopPreviewOpen,
        frameAttempt,
        frameIssue,
        frameLoaded,
        previewUrl,
        viewport,
    ]);

    const chooseMobile = (): void => {
        if (viewport !== 'mobile' || desktopPreviewOpen) {
            setFrameLoaded(false);
            setFrameIssue(null);
        }

        setViewport('mobile');
        setDesktopPreviewOpen(false);
    };

    const openDesktopPreview = (): void => {
        setViewport('desktop');

        if (!previewUrl) {
            return;
        }

        setDesktopPreviewOpen(true);
        setFrameLoaded(false);
        setFrameIssue(null);
    };

    const retryPreview = (): void => {
        setFrameAttempt((attempt) => attempt + 1);
        setFrameLoaded(false);
        setFrameIssue(null);
    };

    const frame = (mode: Viewport) => (
        <iframe
            key={`${previewUrl}-${mode}-${frameAttempt}`}
            title={`Prévia pública do agendamento online em ${mode === 'mobile' ? 'celular' : 'computador'}`}
            src={previewUrl ?? undefined}
            onLoad={() => {
                setFrameLoaded(true);
                setFrameIssue(null);
            }}
            onError={() => {
                setFrameLoaded(true);
                setFrameIssue('error');
            }}
            className={
                mode === 'mobile'
                    ? 'h-[36rem] w-full max-w-[24.375rem] rounded-2xl border border-border bg-background'
                    : 'h-[78dvh] max-h-[56rem] w-[1280px] max-w-none rounded-xl border border-border bg-background'
            }
        />
    );

    return (
        <>
            <Card className="border-primary/20 shadow-sm xl:sticky xl:top-20">
                <CardHeader className="border-b border-border/60 bg-muted/20 pb-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <CardTitle
                                className={bookingTokens.type.sectionTitle}
                            >
                                Prévia pública
                            </CardTitle>
                            <CardDescription className="mt-1">
                                {previewUrl
                                    ? 'Renderização real do rascunho atual.'
                                    : 'A prévia mostra a página real do agendamento.'}
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
                                onClick={chooseMobile}
                                className={`flex min-h-9 items-center gap-1.5 rounded-md px-2.5 text-xs transition-colors ${viewport === 'mobile' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
                            >
                                <Smartphone
                                    aria-hidden="true"
                                    className="size-4"
                                />
                                <span>Celular</span>
                            </button>
                            <button
                                type="button"
                                aria-pressed={viewport === 'desktop'}
                                aria-label="Prévia em computador"
                                onClick={openDesktopPreview}
                                className={`flex min-h-9 items-center gap-1.5 rounded-md px-2.5 text-xs transition-colors ${viewport === 'desktop' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
                            >
                                <Monitor
                                    aria-hidden="true"
                                    className="size-4"
                                />
                                <span>Computador</span>
                            </button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="bg-muted/10 p-3 sm:p-4">
                    {previewUrl && viewport === 'mobile' ? (
                        <div className="flex min-w-0 flex-col items-center gap-3">
                            {frame('mobile')}
                            <PreviewStatus
                                issue={frameIssue}
                                loaded={frameLoaded}
                                onRetry={retryPreview}
                                previewUrl={previewUrl}
                            />
                        </div>
                    ) : previewUrl ? (
                        <div
                            role="status"
                            className="flex min-h-64 flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-background px-5 py-8 text-center"
                        >
                            <div className="mb-4 flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <Monitor
                                    aria-hidden="true"
                                    className="size-6"
                                />
                            </div>
                            <h3 className="text-sm font-semibold text-foreground sm:text-base">
                                Prévia ampliada no computador
                            </h3>
                            <p className="mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                                A página será renderizada com viewport de{' '}
                                {DESKTOP_PREVIEW_WIDTH} px para exibir o layout
                                computador.
                            </p>
                            <Button
                                type="button"
                                className="mt-4"
                                onClick={openDesktopPreview}
                            >
                                Abrir prévia no computador
                            </Button>
                        </div>
                    ) : (
                        <div
                            role="status"
                            className={`flex min-h-64 w-full flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-background px-5 py-8 text-center transition-[max-width] sm:px-8 ${viewport === 'mobile' ? 'max-w-sm' : 'max-w-3xl'}`}
                        >
                            <div className="mb-4 flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <Telescope
                                    aria-hidden="true"
                                    className="size-6"
                                />
                            </div>
                            <h3 className="text-sm font-semibold text-foreground sm:text-base">
                                Sua prévia fiel está quase pronta
                            </h3>
                            <p className="mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                                Salve o primeiro rascunho para carregar aqui a
                                página pública real, sem uma simulação que possa
                                divergir do agendamento.
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>
            {previewUrl ? (
                <Dialog
                    open={desktopPreviewOpen}
                    onOpenChange={(open) => {
                        setDesktopPreviewOpen(open);

                        if (open) {
                            setFrameLoaded(false);
                            setFrameIssue(null);
                        }
                    }}
                >
                    <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-hidden p-4 sm:max-w-[min(96vw,1360px)] sm:p-6">
                        <DialogHeader className="pr-10 text-left">
                            <DialogTitle>Prévia em computador</DialogTitle>
                            <DialogDescription>
                                A página pública está usando um viewport de{' '}
                                {DESKTOP_PREVIEW_WIDTH} px. Em telas menores,
                                deslize horizontalmente para ver toda a largura.
                            </DialogDescription>
                        </DialogHeader>
                        <div
                            data-testid="desktop-preview-scroll-area"
                            className="max-h-[78dvh] overflow-auto rounded-xl bg-muted/20 p-2 sm:p-4"
                        >
                            {frame('desktop')}
                        </div>
                        <PreviewStatus
                            issue={frameIssue}
                            loaded={frameLoaded}
                            onRetry={retryPreview}
                            previewUrl={previewUrl}
                        />
                    </DialogContent>
                </Dialog>
            ) : null}
        </>
    );
}

function PreviewStatus({
    issue,
    loaded,
    onRetry,
    previewUrl,
}: {
    issue: PreviewIssue;
    loaded: boolean;
    onRetry: () => void;
    previewUrl: string;
}) {
    if (issue) {
        return (
            <div
                role="alert"
                className="flex w-full flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
            >
                <span>
                    {issue === 'error'
                        ? 'O navegador informou que a prévia não pôde ser carregada.'
                        : 'A prévia está demorando mais que o esperado.'}
                </span>
                <div className="flex shrink-0 items-center gap-3">
                    <button
                        type="button"
                        onClick={onRetry}
                        className="min-h-9 font-semibold underline underline-offset-4"
                    >
                        Tentar novamente
                    </button>
                    <a
                        href={previewUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex min-h-9 items-center font-semibold underline underline-offset-4"
                    >
                        Abrir em nova aba
                    </a>
                </div>
            </div>
        );
    }

    if (!loaded) {
        return (
            <p
                role="status"
                aria-live="polite"
                className="text-xs text-muted-foreground"
            >
                Carregando a prévia…
            </p>
        );
    }

    return null;
}
