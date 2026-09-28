import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    ClipboardCheck,
    ExternalLink,
    History,
    ImagePlus,
    Scissors,
    UserRound,
    RotateCcw,
} from 'lucide-react';
import { useState } from 'react';
import { FormErrorSummary, PageCanvas } from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import onlineBooking from '@/routes/online_booking';
import coverRoutes from '@/routes/online_booking/cover';
import type { SharedPageProps } from '@/types';
import { AppearanceEditor } from './components/appearance-editor';
import { ReadinessRow } from './components/booking-form-primitives';
import { PublicPreview } from './components/public-preview';
import { TabNavigation } from './components/tab-navigation';
import { bookingUi } from './components/design-tokens';
import { BookingHero } from './components/booking-hero';
import { IdentitySettingsPanel } from './components/identity-settings-panel';
import { OperationalSettingsPanel } from './components/operational-settings-panel';
import { PublicLinkSettingsPanel } from './components/public-link-settings-panel';
import { GallerySettingsPanel } from './components/gallery-settings-panel';
import { HoursSettingsPanel } from './components/hours-settings-panel';
import { ConfirmationSettingsPanel } from './components/confirmation-settings-panel';
import { CatalogSettingsPanel } from './components/catalog-settings-panel';
import { PublicationToolbar } from './components/publication-toolbar';
import { useOnlineBookingActions } from './hooks/use-online-booking-actions';
import type { OnlineBookingProps, TabKey } from './types';

type Props = OnlineBookingProps;

export default function OnlineBookingIndex({
    unit,
    template_key: rootTemplateKey = null,
    publicUrl,
    previewUrl: signedPreviewUrl = null,
    publicDomains,
    services,
    professionals,
    readiness,
    publication = null,
    draft = null,
    activePublication = null,
    publicationHistory = [],
    draftDiff = [],
    settings: rootSettings,
    gallery = [],
    cover,
    coverUploadUrl: coverUploadUrlProp,
    coverDeleteUrl: coverDeleteUrlProp,
}: Props) {
    const { flash } = usePage<SharedPageProps>().props;
    const settings = rootSettings ?? unit.settings ?? {};
    const [activeTab, setActiveTab] = useState<TabKey>('details');
    const {
        copied,
        publicationError,
        publicationProcessing,
        galleryItems,
        copyPublicUrl,
        publishDraft,
        unpublishSite,
        uploadGalleryImage,
        updateGalleryAlt,
        deleteGalleryImage,
        moveGalleryImage,
    } = useOnlineBookingActions({ draft, publicUrl, gallery });
    const coverUrl =
        cover ??
        settings.cover_url ??
        settings.cover_image_url ??
        settings.logo_url;
    const resolvedCoverUploadUrl =
        coverUploadUrlProp ?? coverRoutes.store.url();
    const resolvedCoverDeleteUrl =
        coverDeleteUrlProp ??
        (coverUrl ? coverRoutes.destroy.url() : undefined);
    const activeServices = services.filter((item) => item.status === 'active');
    const activeProfessionals = professionals.filter(
        (item) => item.status === 'active',
    );
    const hasPendingChanges = Boolean(
        publication?.status === 'published' &&
        draft &&
        activePublication &&
        draft.revision > activePublication.source_revision,
    );
    const publicationLabel =
        !publication || publication.status === 'unpublished'
            ? draft
                ? 'Rascunho salvo'
                : 'Não publicada'
            : hasPendingChanges
              ? 'Alterações para publicar'
              : 'Publicada';

    return (
        <>
            <Head title="Agendamento online" />
            <PageCanvas className={bookingUi.shell}>
                <BookingHero
                    unitName={unit.name}
                    unitSlug={unit.slug}
                    settings={settings}
                    readiness={readiness}
                />
                <PublicationToolbar
                    status={publication?.status}
                    publicationLabel={publicationLabel}
                    hasPendingChanges={hasPendingChanges}
                    publicationError={publicationError}
                    publicUrl={publicUrl}
                    previewUrl={signedPreviewUrl}
                    processing={publicationProcessing}
                    onPublish={publishDraft}
                    onUnpublish={unpublishSite}
                    canPublish={Boolean(draft && readiness.publishable)}
                />
                {hasPendingChanges && draftDiff?.length ? (
                    <div className="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-100">
                        <div className="flex items-start gap-3">
                            <AlertCircle
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <div>
                                <p className="text-sm font-semibold">
                                    Alterações aguardando publicação
                                </p>
                                <p className="mt-1 text-xs opacity-80">
                                    A versão pública continua intacta até você
                                    publicar.
                                </p>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {draftDiff.map((label: string) => (
                                        <Badge
                                            key={label}
                                            variant="outline"
                                            className="border-current/30"
                                        >
                                            {label}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                ) : null}
                {publicationHistory.length > 0 ? (
                    <Card className="mb-5">
                        <CardHeader className="border-b border-border/60 bg-muted/20">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <History
                                    aria-hidden="true"
                                    className="size-4 text-primary"
                                />
                                Histórico de publicações
                            </CardTitle>
                            <CardDescription>
                                Cada versão é imutável. Restaurar cria um novo
                                rascunho sem alterar o histórico.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="divide-y divide-border/60 p-0">
                            {publicationHistory.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                            v{item.version}
                                        </span>
                                        <div>
                                            <p className="text-sm font-medium">
                                                Publicada em{' '}
                                                {item.published_at
                                                    ? new Date(
                                                          item.published_at,
                                                      ).toLocaleString('pt-BR')
                                                    : 'data indisponível'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Revisão {item.source_revision}
                                                {item.published_by?.name
                                                    ? ` · ${item.published_by.name}`
                                                    : ''}
                                                {item.superseded_at
                                                    ? ' · substituída'
                                                    : ' · ativa'}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={item.preview_url ?? '#'}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <ExternalLink aria-hidden="true" />
                                                Visualizar
                                            </a>
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    onlineBooking.publications.restore.url(
                                                        item.id,
                                                    ),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <RotateCcw aria-hidden="true" />
                                            Restaurar como rascunho
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ) : null}
                {draft ? (
                    <div className="mb-5 space-y-5">
                        <AppearanceEditor
                            draft={draft}
                            settings={settings}
                            unitName={unit.name}
                            logoUrl={
                                settings.logo_image_url ??
                                unit.logo_image_url ??
                                null
                            }
                        />
                    </div>
                ) : null}
                <Form
                    {...onlineBooking.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-5"
                >
                    {({ errors, processing, wasSuccessful }) => (
                        <>
                            <FormErrorSummary errors={errors} />
                            {flash.error ? (
                                <div
                                    role="alert"
                                    className="flex items-start gap-3 rounded-xl border border-amber-300/70 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/70 dark:bg-amber-950/30 dark:text-amber-200"
                                >
                                    <AlertCircle
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 shrink-0"
                                    />
                                    <div>
                                        <p className="font-semibold">
                                            Configuração atualizada em outro
                                            lugar
                                        </p>
                                        <p className="mt-0.5 text-xs leading-5 opacity-90">
                                            {flash.error}
                                        </p>
                                    </div>
                                </div>
                            ) : null}
                            <input
                                type="hidden"
                                name="online_booking_enabled"
                                value="0"
                            />
                            {wasSuccessful && (
                                <div
                                    role="status"
                                    className="flex items-center gap-2 rounded-xl border border-emerald-300/60 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/30 dark:text-emerald-200"
                                >
                                    <CheckCircle2
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                    Configurações salvas com sucesso.
                                </div>
                            )}
                            <div className="mb-3 flex items-end justify-between gap-3">
                                <div>
                                    <p className="text-sm font-semibold text-slate-900">
                                        Configuração rápida
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        Ajuste identidade, catálogo e horários
                                        em um só lugar.
                                    </p>
                                </div>
                                <span className="hidden text-xs font-medium text-slate-400 sm:block">
                                    As alterações ficam no rascunho
                                </span>
                            </div>
                            <TabNavigation
                                activeTab={activeTab}
                                onChange={setActiveTab}
                            />
                            <div className="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.8fr)] xl:items-start">
                                <div className="space-y-5">
                                    {activeTab === 'details' && (
                                        <IdentitySettingsPanel
                                            unit={unit}
                                            settings={settings}
                                            templateKey={rootTemplateKey}
                                            coverUrl={coverUrl}
                                            coverUploadUrl={
                                                resolvedCoverUploadUrl
                                            }
                                            coverDeleteUrl={
                                                resolvedCoverDeleteUrl
                                            }
                                        />
                                    )}
                                    {activeTab === 'settings' && (
                                        <OperationalSettingsPanel
                                            settings={settings}
                                        />
                                    )}
                                    {activeTab === 'link' && (
                                        <PublicLinkSettingsPanel
                                            settings={settings}
                                            unitSlug={unit.slug}
                                            publicDomains={publicDomains}
                                            publicUrl={publicUrl}
                                            copied={copied}
                                            onCopy={copyPublicUrl}
                                        />
                                    )}
                                    {activeTab === 'gallery' && (
                                        <GallerySettingsPanel
                                            galleryItems={galleryItems}
                                            onUpload={uploadGalleryImage}
                                            onUpdateAlt={updateGalleryAlt}
                                            onDelete={deleteGalleryImage}
                                            onMove={moveGalleryImage}
                                        />
                                    )}
                                    {activeTab === 'services' && (
                                        <CatalogSettingsPanel
                                            services={services}
                                            professionals={professionals}
                                        />
                                    )}
                                    {activeTab === 'hours' && (
                                        <HoursSettingsPanel
                                            settings={settings}
                                        />
                                    )}
                                    {activeTab === 'confirmation' && (
                                        <ConfirmationSettingsPanel />
                                    )}
                                </div>
                                <aside className="min-w-0 space-y-5 xl:self-start">
                                    <PublicPreview
                                        unit={unit}
                                        settings={settings}
                                        gallery={galleryItems}
                                        services={services}
                                        previewUrl={signedPreviewUrl}
                                    />
                                    <Card>
                                        <CardContent className="pt-5">
                                            <label className="flex items-center gap-3 rounded-xl border border-border bg-muted/20 p-3">
                                                <input
                                                    type="checkbox"
                                                    name="online_booking_enabled"
                                                    value="1"
                                                    defaultChecked={
                                                        unit.online_booking_enabled
                                                    }
                                                    className="size-5 accent-primary"
                                                />
                                                <span>
                                                    <span className="block text-sm font-semibold">
                                                        Permitir agendamentos
                                                        públicos
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        Clientes poderão
                                                        consultar ofertas e
                                                        solicitar um horário.
                                                    </span>
                                                </span>
                                            </label>
                                        </CardContent>
                                    </Card>
                                    <Card className="overflow-hidden">
                                        <CardHeader className="bg-muted/40">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <ClipboardCheck
                                                    aria-hidden="true"
                                                    className="size-4 text-primary"
                                                />
                                                Checklist de publicação
                                            </CardTitle>
                                            <CardDescription>
                                                Resolva os itens abaixo para
                                                liberar o link público.
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="pt-5">
                                            <ul className="space-y-3">
                                                <ReadinessRow
                                                    label="Unidade habilitada"
                                                    ready={
                                                        readiness.unit_enabled
                                                    }
                                                />
                                                <ReadinessRow
                                                    label={`Serviço ativo selecionado (${activeServices.length})`}
                                                    ready={
                                                        readiness.has_active_service
                                                    }
                                                />
                                                <ReadinessRow
                                                    label={`Profissional ativo selecionado (${activeProfessionals.length})`}
                                                    ready={
                                                        readiness.has_active_professional
                                                    }
                                                />
                                                <ReadinessRow
                                                    label="Existe serviço e profissional compatíveis"
                                                    ready={
                                                        readiness.has_service_professional_pair
                                                    }
                                                />
                                            </ul>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-base">
                                                Visão rápida
                                            </CardTitle>
                                            <CardDescription>
                                                Resumo do que os clientes
                                                encontrarão.
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            <div className="flex items-center justify-between rounded-lg bg-muted/40 px-3 py-2 text-sm">
                                                <span className="flex items-center gap-2 text-muted-foreground">
                                                    <Scissors className="size-4" />
                                                    Serviços
                                                </span>
                                                <span className="font-semibold">
                                                    {activeServices.length}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between rounded-lg bg-muted/40 px-3 py-2 text-sm">
                                                <span className="flex items-center gap-2 text-muted-foreground">
                                                    <UserRound className="size-4" />
                                                    Profissionais
                                                </span>
                                                <span className="font-semibold">
                                                    {activeProfessionals.length}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between rounded-lg bg-muted/40 px-3 py-2 text-sm">
                                                <span className="flex items-center gap-2 text-muted-foreground">
                                                    <ImagePlus className="size-4" />
                                                    Fotos
                                                </span>
                                                <span className="font-semibold">
                                                    {gallery.length}
                                                </span>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </aside>
                            </div>
                            <input
                                type="hidden"
                                name="lock_version"
                                value={unit.lock_version}
                            />
                            <div className="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:items-center sm:justify-end">
                                <p className="mr-auto text-xs text-muted-foreground">
                                    As alterações serão aplicadas à unidade
                                    atual.
                                </p>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full sm:w-auto"
                                >
                                    {processing
                                        ? 'Salvando…'
                                        : 'Salvar configurações'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </PageCanvas>
        </>
    );
}
