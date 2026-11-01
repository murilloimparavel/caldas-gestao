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
    version,
    draftDiff,
    draftDiffDetails,
    unlinkedServices,
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
    version?: number;
    draftDiff: string[];
    draftDiffDetails: { label: string; added: string[]; removed: string[] }[];
    unlinkedServices: { id: string; name: string }[];
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
                            : 'Alterações salvas no rascunho ainda não aparecem para seus clientes.'}
                        {isPublished && version
                            ? ` Versão pública: ${version}.`
                            : ''}
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
                    asChild
                    type="button"
                    variant="outline"
                    disabled={!previewUrl}
                >
                    {previewUrl ? (
                        <a
                            href={previewUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <ExternalLink aria-hidden="true" />
                            Prévia do rascunho
                        </a>
                    ) : (
                        <span>Prévia do rascunho</span>
                    )}
                </Button>
                {publicUrl ? (
                    <Button asChild type="button" variant="outline">
                        <a
                            href={publicUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <ExternalLink aria-hidden="true" />
                            Ver site publicado
                        </a>
                    </Button>
                ) : null}
                {isPublished ? (
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={onUnpublish}
                        disabled={processing}
                    >
                        Retirar do ar
                    </Button>
                ) : null}
                {!isPublished || hasPendingChanges ? (
                    <Button
                        type="button"
                        onClick={onPublish}
                        disabled={!canPublish || processing}
                    >
                        {isPublished
                            ? 'Publicar alterações'
                            : 'Publicar página'}
                    </Button>
                ) : null}
            </div>
            {hasPendingChanges ? (
                <div
                    role="status"
                    className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 lg:col-span-2 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    <p className="font-semibold">
                        Alterações salvas, ainda não publicadas
                    </p>
                    <p className="mt-1">
                        A publicação aplicará o rascunho salvo. Salve as edições
                        atuais antes de publicar.
                    </p>
                    {draftDiff.length ? (
                        <p className="mt-1">
                            Áreas alteradas: {draftDiff.join(', ')}.
                        </p>
                    ) : null}
                    {draftDiffDetails.map((detail) => (
                        <div key={detail.label} className="mt-2">
                            <p className="font-medium">{detail.label}</p>
                            {detail.added.length ? (
                                <p>Adicionados: {detail.added.join(', ')}</p>
                            ) : null}
                            {detail.removed.length ? (
                                <p>Removidos: {detail.removed.join(', ')}</p>
                            ) : null}
                        </div>
                    ))}
                </div>
            ) : null}
            {unlinkedServices.length ? (
                <div
                    role="status"
                    className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 lg:col-span-2 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    Serviços do rascunho sem profissional ativo selecionado e
                    vinculado não aparecerão no site:{' '}
                    <strong>
                        {unlinkedServices
                            .map((service) => service.name)
                            .join(', ')}
                    </strong>
                    .
                </div>
            ) : null}
        </div>
    );
}
