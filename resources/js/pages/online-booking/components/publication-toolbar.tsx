import { Link } from '@inertiajs/react';
import { ExternalLink, Globe2, Share2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import onlineBooking from '@/routes/online_booking';
import { bookingUi } from './design-tokens';

type PublicationStatus = 'unpublished' | 'published';

export function PublicationToolbar({
    status,
    publicationLabel,
    hasPendingChanges,
    publicationError,
    publicUrl,
    previewUrl,
    processing,
    onPublish,
    onUnpublish,
    canPublish,
}: {
    status?: PublicationStatus;
    publicationLabel: string;
    hasPendingChanges: boolean;
    publicationError: string | null;
    publicUrl: string | null;
    previewUrl: string | null;
    processing: boolean;
    onPublish: () => void;
    onUnpublish: () => void;
    canPublish: boolean;
}) {
    const isPublished = status === 'published';

    return (
        <div
            className={`mb-5 grid gap-4 ${bookingUi.publication} p-4 sm:p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center`}
        >
            <div className="flex items-start gap-3">
                <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <Globe2 aria-hidden="true" className="size-4" />
                </span>
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-semibold text-foreground">
                            Página pública
                        </p>
                        <Badge
                            variant={
                                isPublished && !hasPendingChanges
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {publicationLabel}
                        </Badge>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {isPublished && !hasPendingChanges
                            ? 'Seus clientes estão vendo a última versão publicada.'
                            : 'Suas alterações ficam no rascunho até você publicar.'}
                    </p>
                </div>
            </div>
            <div className="flex flex-col gap-2 sm:flex-row lg:justify-end">
                {publicationError ? (
                    <p
                        role="alert"
                        className="text-sm text-destructive sm:mr-2 sm:self-center"
                    >
                        {publicationError}
                    </p>
                ) : null}
                <Button asChild type="button" variant="ghost">
                    <Link href={onlineBooking.campaign_links.index.url()}>
                        <Share2 aria-hidden="true" />
                        Links de divulgação
                    </Link>
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                        publicUrl &&
                        window.open(
                            previewUrl ?? publicUrl,
                            '_blank',
                            'noopener,noreferrer',
                        )
                    }
                    disabled={!publicUrl}
                >
                    <ExternalLink aria-hidden="true" />
                    Visualizar página
                </Button>
                {isPublished ? (
                    <>
                        {hasPendingChanges ? (
                            <Button
                                type="button"
                                onClick={onPublish}
                                disabled={!canPublish || processing}
                            >
                                Publicar alterações
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={onUnpublish}
                            disabled={processing}
                        >
                            Retirar do ar
                        </Button>
                    </>
                ) : (
                    <Button
                        type="button"
                        onClick={onPublish}
                        disabled={!canPublish || processing}
                    >
                        Publicar página
                    </Button>
                )}
            </div>
        </div>
    );
}
