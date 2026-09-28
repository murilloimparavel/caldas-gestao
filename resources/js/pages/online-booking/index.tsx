import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    ImagePlus,
    Scissors,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { FormErrorSummary, PageCanvas } from '@/components/operational';
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
import { PublicPreview } from './components/public-preview';
import { TabNavigation } from './components/tab-navigation';
import { bookingUi } from './components/design-tokens';
import { BookingHero } from './components/booking-hero';
import { IdentitySettingsPanel } from './components/identity-settings-panel';
import { OperationalSettingsPanel } from './components/operational-settings-panel';
import { PublicLinkSettingsPanel } from './components/public-link-settings-panel';
import { GallerySettingsPanel } from './components/gallery-settings-panel';
import { HoursSettingsPanel } from './components/hours-settings-panel';
import { CatalogSettingsPanel } from './components/catalog-settings-panel';
import { PublicationToolbar } from './components/publication-toolbar';
import { PublicationSettingsPanel } from './components/publication-settings-panel';
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
                                <div
                                    id="online-booking-tabpanel"
                                    role="region"
                                    aria-labelledby={`booking-tab-${activeTab}`}
                                    className="min-w-0 space-y-5"
                                >
                                    <Card hidden={activeTab !== 'settings'}>
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
                                    <div hidden={activeTab !== 'details'}>
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
                                    </div>
                                    <div hidden={activeTab !== 'settings'}>
                                        <OperationalSettingsPanel
                                            settings={settings}
                                        />
                                    </div>
                                    <div hidden={activeTab !== 'link'}>
                                        <PublicLinkSettingsPanel
                                            settings={settings}
                                            unitSlug={unit.slug}
                                            publicDomains={publicDomains}
                                            publicUrl={publicUrl}
                                            copied={copied}
                                            onCopy={copyPublicUrl}
                                        />
                                    </div>
                                    {activeTab === 'gallery' && (
                                        <GallerySettingsPanel
                                            galleryItems={galleryItems}
                                            onUpload={uploadGalleryImage}
                                            onUpdateAlt={updateGalleryAlt}
                                            onDelete={deleteGalleryImage}
                                            onMove={moveGalleryImage}
                                        />
                                    )}
                                    <div hidden={activeTab !== 'services'}>
                                        <CatalogSettingsPanel
                                            services={services}
                                            professionals={professionals}
                                        />
                                    </div>
                                    <div hidden={activeTab !== 'hours'}>
                                        <HoursSettingsPanel
                                            settings={settings}
                                        />
                                    </div>
                                    {activeTab === 'publication' && (
                                        <PublicationSettingsPanel
                                            readiness={readiness}
                                            activeServicesCount={
                                                activeServices.length
                                            }
                                            activeProfessionalsCount={
                                                activeProfessionals.length
                                            }
                                            hasPendingChanges={
                                                hasPendingChanges
                                            }
                                            draftDiff={draftDiff ?? []}
                                            publicationHistory={
                                                publicationHistory
                                            }
                                            onRestore={(publicationId) =>
                                                router.post(
                                                    onlineBooking.publications.restore.url(
                                                        publicationId,
                                                    ),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        />
                                    )}
                                </div>
                                <aside className="min-w-0 space-y-5 xl:self-start">
                                    <PublicPreview
                                        previewUrl={signedPreviewUrl}
                                    />
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
                                    O link público só muda quando você publicar.
                                </p>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full sm:w-auto"
                                >
                                    {processing
                                        ? 'Salvando…'
                                        : 'Salvar rascunho'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </PageCanvas>
        </>
    );
}
