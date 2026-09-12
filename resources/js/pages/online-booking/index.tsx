import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    BellRing,
    CalendarDays,
    Check,
    CheckCircle2,
    ClipboardCheck,
    Copy,
    ExternalLink,
    GalleryHorizontalEnd,
    History,
    Globe2,
    ImagePlus,
    Link2,
    Palette,
    Scissors,
    Share2,
    UserRound,
    UsersRound,
    RotateCcw,
} from 'lucide-react';
import { useState } from 'react';
import {
    FormErrorSummary,
    PageCanvas,
    ResourceHeader,
} from '@/components/operational';
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
import { Textarea } from '@/components/ui/textarea';
import onlineBooking from '@/routes/online_booking';
import coverRoutes from '@/routes/online_booking/cover';
import tenantDomains from '@/routes/tenant-domains';
import type { SharedPageProps } from '@/types';
import { SectionVisibilityEditor } from './components/section-visibility-editor';
import { CoverEditor } from './components/cover-editor';
import {
    Field,
    ReadinessRow,
    SectionCard,
    SelectionCard,
} from './components/booking-form-primitives';
import { PublicPreview } from './components/public-preview';
import { TabNavigation } from './components/tab-navigation';
import { useOnlineBookingActions } from './hooks/use-online-booking-actions';
import type { OnlineBookingProps, TabKey } from './types';

type Props = OnlineBookingProps;

export default function OnlineBookingIndex({
    unit,
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
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Gestão"
                    title="Agendamento online"
                    description={`Escolha o que ${unit.name} oferece para clientes e profissionais no canal público.`}
                    status={
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Badge
                                variant={
                                    readiness.publishable
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {readiness.publishable
                                    ? 'Publicável'
                                    : 'Em preparação'}
                            </Badge>
                            <Badge variant="outline">
                                /{settings.public_slug ?? unit.slug}
                            </Badge>
                        </div>
                    }
                />
                <div className="mb-5 grid gap-4 rounded-2xl border border-border/70 bg-card p-4 shadow-sm sm:p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
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
                                        publication?.status === 'published' &&
                                        !hasPendingChanges
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {publicationLabel}
                                </Badge>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {publication?.status === 'published' &&
                                !hasPendingChanges
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
                            <Link
                                href={onlineBooking.campaign_links.index.url()}
                            >
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
                                    signedPreviewUrl ?? publicUrl,
                                    '_blank',
                                    'noopener,noreferrer',
                                )
                            }
                            disabled={!publicUrl}
                        >
                            <ExternalLink aria-hidden="true" />
                            Visualizar página
                        </Button>
                        {publication?.status === 'published' ? (
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={unpublishSite}
                                disabled={publicationProcessing}
                            >
                                Retirar do ar
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                onClick={publishDraft}
                                disabled={
                                    !draft ||
                                    !readiness.publishable ||
                                    publicationProcessing
                                }
                            >
                                Publicar página
                            </Button>
                        )}
                    </div>
                </div>
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
                {draft ? <SectionVisibilityEditor draft={draft} /> : null}
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
                            <TabNavigation
                                activeTab={activeTab}
                                onChange={setActiveTab}
                            />
                            <div className="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.8fr)] xl:items-start">
                                <div className="space-y-5">
                                    {activeTab === 'details' && (
                                        <SectionCard
                                            icon={Globe2}
                                            title="Identidade pública"
                                            description="Essas informações aparecem no perfil do seu negócio."
                                        >
                                            <div className="grid gap-5 sm:grid-cols-2">
                                                <Field
                                                    label="Nome da unidade"
                                                    name="unit_name"
                                                    defaultValue={unit.name}
                                                    readOnly
                                                />
                                                <Field
                                                    label="WhatsApp"
                                                    name="whatsapp_phone"
                                                    defaultValue={
                                                        settings.whatsapp
                                                    }
                                                    placeholder="(00) 00000-0000"
                                                />
                                                <Field
                                                    label="Telefone"
                                                    name="phone"
                                                    defaultValue={
                                                        settings.phone
                                                    }
                                                />
                                                <Field
                                                    label="Instagram"
                                                    name="instagram_url"
                                                    defaultValue={
                                                        settings.instagram
                                                    }
                                                    placeholder="instagram.com/seunegocio"
                                                />
                                                <Field
                                                    label="Facebook"
                                                    name="facebook_url"
                                                    defaultValue={
                                                        settings.facebook
                                                    }
                                                />
                                                <Field
                                                    label="Site"
                                                    name="website_url"
                                                    defaultValue={
                                                        settings.website
                                                    }
                                                    placeholder="https://seusite.com.br"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="description">
                                                    Descrição
                                                </Label>
                                                <Textarea
                                                    id="description"
                                                    name="description"
                                                    defaultValue={
                                                        settings.description ??
                                                        ''
                                                    }
                                                    placeholder="Conte um pouco sobre seu negócio e propósito."
                                                    rows={4}
                                                />
                                            </div>
                                            <CoverEditor
                                                url={coverUrl}
                                                uploadUrl={
                                                    resolvedCoverUploadUrl
                                                }
                                                deleteUrl={
                                                    resolvedCoverDeleteUrl
                                                }
                                                onUploaded={() =>
                                                    router.reload({
                                                        only: [
                                                            'settings',
                                                            'gallery',
                                                            'publicUrl',
                                                            'canonicalUrl',
                                                        ],
                                                    })
                                                }
                                            />
                                        </SectionCard>
                                    )}
                                    {activeTab === 'settings' && (
                                        <SectionCard
                                            icon={Palette}
                                            title="Experiência do canal"
                                            description="Defina como o público encontrará sua unidade."
                                        >
                                            <div className="grid gap-5 sm:grid-cols-2">
                                                <Field
                                                    label="Cor de destaque"
                                                    name="brand_color"
                                                    defaultValue={
                                                        settings.brand_color ??
                                                        settings.accent_color ??
                                                        '#5b6cff'
                                                    }
                                                    placeholder="#5b6cff"
                                                />
                                                <div className="space-y-2">
                                                    <Label htmlFor="booking_flow">
                                                        Ordem do agendamento
                                                    </Label>
                                                    <select
                                                        id="booking_flow"
                                                        name="booking_flow"
                                                        defaultValue={
                                                            settings.booking_flow ??
                                                            settings.flow ??
                                                            'service_first'
                                                        }
                                                        className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                                    >
                                                        <option value="service_first">
                                                            Serviço primeiro
                                                        </option>
                                                        <option value="professional_first">
                                                            Profissional
                                                            primeiro
                                                        </option>
                                                    </select>
                                                </div>
                                                <Field
                                                    label="Antecedência mínima (minutos)"
                                                    name="minimum_notice_minutes"
                                                    type="number"
                                                    defaultValue={settings.minimum_notice_minutes?.toString()}
                                                    placeholder="30"
                                                />
                                            </div>
                                        </SectionCard>
                                    )}
                                    {activeTab === 'link' && (
                                        <SectionCard
                                            icon={Link2}
                                            title="Link público"
                                            description="Compartilhe este endereço em redes sociais e mensagens."
                                        >
                                            <Field
                                                label="Slug público"
                                                name="public_slug"
                                                defaultValue={
                                                    settings.public_slug ??
                                                    unit.slug
                                                }
                                                placeholder="minha-unidade"
                                            />
                                            <div className="space-y-2">
                                                <Label htmlFor="public_domain_id">
                                                    Domínio do link
                                                </Label>
                                                <select
                                                    id="public_domain_id"
                                                    name="public_domain_id"
                                                    defaultValue={
                                                        settings.public_domain_id ??
                                                        ''
                                                    }
                                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                    disabled={
                                                        publicDomains.length ===
                                                        0
                                                    }
                                                >
                                                    <option value="">
                                                        Domínio padrão do
                                                        sistema
                                                    </option>
                                                    {publicDomains.map(
                                                        (domain) => (
                                                            <option
                                                                key={domain.id}
                                                                value={
                                                                    domain.id
                                                                }
                                                            >
                                                                {
                                                                    domain.hostname
                                                                }
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                {publicDomains.length > 0 ? (
                                                    <p className="text-xs text-muted-foreground">
                                                        O link será aberto neste
                                                        domínio público ativo.
                                                    </p>
                                                ) : (
                                                    <p className="text-xs text-muted-foreground">
                                                        Cadastre e ative um
                                                        domínio público em{' '}
                                                        <a
                                                            className="text-primary underline underline-offset-4"
                                                            href={tenantDomains.index.url()}
                                                        >
                                                            Configurações →
                                                            Domínios
                                                        </a>{' '}
                                                        para personalizar este
                                                        link.
                                                    </p>
                                                )}
                                            </div>
                                            <div className="rounded-xl border border-border bg-muted/40 px-4 py-3 font-mono text-sm break-all text-muted-foreground">
                                                {publicUrl ??
                                                    'Disponível quando a unidade estiver pronta para publicar.'}
                                            </div>
                                            <div className="flex flex-col gap-2 sm:flex-row">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="flex-1"
                                                    onClick={copyPublicUrl}
                                                    disabled={!publicUrl}
                                                >
                                                    {copied ? (
                                                        <Check aria-hidden="true" />
                                                    ) : (
                                                        <Copy aria-hidden="true" />
                                                    )}
                                                    {copied
                                                        ? 'Copiado'
                                                        : 'Copiar link'}
                                                </Button>
                                                {publicUrl ? (
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                        className="flex-1"
                                                        asChild
                                                    >
                                                        <a
                                                            href={publicUrl}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
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
                                            <div className="grid gap-2 pt-2 sm:grid-cols-3">
                                                <div className="rounded-lg border border-border p-3">
                                                    <Share2 className="mb-2 size-4 text-primary" />
                                                    <p className="text-xs font-semibold">
                                                        Geral
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Link para compartilhar
                                                    </p>
                                                </div>
                                                <div className="rounded-lg border border-border p-3">
                                                    <BellRing className="mb-2 size-4 text-primary" />
                                                    <p className="text-xs font-semibold">
                                                        WhatsApp
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Enviar aos clientes
                                                    </p>
                                                </div>
                                                <div className="rounded-lg border border-border p-3">
                                                    <ExternalLink className="mb-2 size-4 text-primary" />
                                                    <p className="text-xs font-semibold">
                                                        Redes sociais
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Adicionar à bio
                                                    </p>
                                                </div>
                                            </div>
                                        </SectionCard>
                                    )}
                                    {activeTab === 'gallery' && (
                                        <SectionCard
                                            icon={GalleryHorizontalEnd}
                                            title="Galeria de fotos"
                                            description="Mostre o ambiente e a experiência da sua unidade."
                                        >
                                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                                {galleryItems.map(
                                                    (image, index) => (
                                                        <div
                                                            key={image.id}
                                                            className="relative aspect-square overflow-hidden rounded-xl border border-border bg-muted"
                                                        >
                                                            {image.url ? (
                                                                <img
                                                                    src={
                                                                        image.url
                                                                    }
                                                                    alt={
                                                                        image.alt ??
                                                                        ''
                                                                    }
                                                                    className="size-full object-cover"
                                                                />
                                                            ) : image.path ? (
                                                                <div className="flex size-full items-center justify-center px-3 text-center text-xs text-muted-foreground">
                                                                    Imagem
                                                                    enviada
                                                                </div>
                                                            ) : (
                                                                <div className="flex size-full items-center justify-center text-muted-foreground">
                                                                    <ImagePlus className="size-6" />
                                                                </div>
                                                            )}
                                                            <div className="absolute inset-x-0 bottom-0 space-y-1 bg-background/90 p-2 backdrop-blur-sm">
                                                                <Input
                                                                    aria-label={`Texto alternativo da imagem ${index + 1}`}
                                                                    defaultValue={
                                                                        image.alt ??
                                                                        image.alt_text ??
                                                                        ''
                                                                    }
                                                                    onBlur={(
                                                                        event,
                                                                    ) =>
                                                                        updateGalleryAlt(
                                                                            image.id,
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="Texto alternativo"
                                                                    className="h-7 text-3xs"
                                                                />
                                                                <div className="flex gap-1">
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="ghost"
                                                                        disabled={
                                                                            index ===
                                                                            0
                                                                        }
                                                                        onClick={() =>
                                                                            moveGalleryImage(
                                                                                image.id,
                                                                                -1,
                                                                            )
                                                                        }
                                                                        aria-label="Mover imagem para cima"
                                                                    >
                                                                        ↑
                                                                    </Button>
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="ghost"
                                                                        disabled={
                                                                            index ===
                                                                            galleryItems.length -
                                                                                1
                                                                        }
                                                                        onClick={() =>
                                                                            moveGalleryImage(
                                                                                image.id,
                                                                                1,
                                                                            )
                                                                        }
                                                                        aria-label="Mover imagem para baixo"
                                                                    >
                                                                        ↓
                                                                    </Button>
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="ghost"
                                                                        className="ml-auto text-destructive"
                                                                        onClick={() =>
                                                                            deleteGalleryImage(
                                                                                image.id,
                                                                            )
                                                                        }
                                                                        aria-label="Excluir imagem"
                                                                    >
                                                                        Excluir
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                                <label className="flex aspect-square cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-muted/20 text-center hover:border-primary/60">
                                                    <ImagePlus className="size-6 text-primary" />
                                                    <span className="text-xs font-medium">
                                                        Adicionar foto
                                                    </span>
                                                    <span className="text-3xs text-muted-foreground">
                                                        JPG, PNG, WEBP até 5MB
                                                    </span>
                                                    <input
                                                        type="file"
                                                        accept="image/jpeg,image/png,image/webp"
                                                        className="sr-only"
                                                        onChange={(event) => {
                                                            const file =
                                                                event.target
                                                                    .files?.[0];

                                                            if (file) {
                                                                uploadGalleryImage(
                                                                    file,
                                                                );
                                                            }

                                                            event.currentTarget.value =
                                                                '';
                                                        }}
                                                    />
                                                </label>
                                            </div>
                                        </SectionCard>
                                    )}
                                    {activeTab === 'services' && (
                                        <div className="space-y-5">
                                            <SectionCard
                                                icon={Scissors}
                                                title="Serviços publicados"
                                                description="Escolha as ofertas que aparecerão no canal."
                                            >
                                                <div
                                                    className="grid gap-2"
                                                    aria-label="Serviços disponíveis"
                                                >
                                                    {services.length > 0 ? (
                                                        services.map((item) => (
                                                            <SelectionCard
                                                                key={item.id}
                                                                item={item}
                                                                group="service"
                                                            />
                                                        ))
                                                    ) : (
                                                        <p className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                                                            Cadastre um serviço
                                                            ativo para começar.
                                                        </p>
                                                    )}
                                                </div>
                                            </SectionCard>
                                            <SectionCard
                                                icon={UsersRound}
                                                title="Profissionais publicados"
                                                description="Selecione quem pode receber solicitações online."
                                            >
                                                <div
                                                    className="grid gap-2"
                                                    aria-label="Profissionais disponíveis"
                                                >
                                                    {professionals.length >
                                                    0 ? (
                                                        professionals.map(
                                                            (item) => (
                                                                <SelectionCard
                                                                    key={
                                                                        item.id
                                                                    }
                                                                    item={item}
                                                                    group="professional"
                                                                />
                                                            ),
                                                        )
                                                    ) : (
                                                        <p className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                                                            Cadastre um
                                                            profissional ativo
                                                            para começar.
                                                        </p>
                                                    )}
                                                </div>
                                            </SectionCard>
                                        </div>
                                    )}
                                    {activeTab === 'hours' && (
                                        <SectionCard
                                            icon={CalendarDays}
                                            title="Horário de atendimento"
                                            description="A disponibilidade pública respeita a agenda e os bloqueios da equipe."
                                        >
                                            <div className="space-y-2">
                                                {[
                                                    'Segunda-feira',
                                                    'Terça-feira',
                                                    'Quarta-feira',
                                                    'Quinta-feira',
                                                    'Sexta-feira',
                                                    'Sábado',
                                                    'Domingo',
                                                ].map((day, index) => (
                                                    <div
                                                        key={day}
                                                        className="grid grid-cols-[1fr_auto_auto] items-center gap-3 rounded-xl border border-border p-3"
                                                    >
                                                        <label className="flex items-center gap-2 text-sm font-medium">
                                                            <input
                                                                type="hidden"
                                                                name={`public_hours[${index}][enabled]`}
                                                                value="0"
                                                            />
                                                            <input
                                                                type="checkbox"
                                                                name={`public_hours[${index}][enabled]`}
                                                                value="1"
                                                                defaultChecked={
                                                                    settings
                                                                        .public_hours?.[
                                                                        String(
                                                                            index,
                                                                        )
                                                                    ]
                                                                        ?.enabled ??
                                                                    index < 6
                                                                }
                                                                className="size-4 accent-primary"
                                                            />
                                                            {day}
                                                        </label>
                                                        <Input
                                                            aria-label={`${day} início`}
                                                            type="time"
                                                            name={`public_hours[${index}][starts_at]`}
                                                            defaultValue={
                                                                settings
                                                                    .public_hours?.[
                                                                    String(
                                                                        index,
                                                                    )
                                                                ]?.starts_at ??
                                                                (index < 6
                                                                    ? '08:00'
                                                                    : '')
                                                            }
                                                            className="h-8 w-28"
                                                        />
                                                        <Input
                                                            aria-label={`${day} fim`}
                                                            type="time"
                                                            name={`public_hours[${index}][ends_at]`}
                                                            defaultValue={
                                                                settings
                                                                    .public_hours?.[
                                                                    String(
                                                                        index,
                                                                    )
                                                                ]?.ends_at ??
                                                                (index < 6
                                                                    ? '18:00'
                                                                    : '')
                                                            }
                                                            className="h-8 w-28"
                                                        />
                                                    </div>
                                                ))}
                                            </div>
                                        </SectionCard>
                                    )}
                                    {activeTab === 'confirmation' && (
                                        <SectionCard
                                            icon={BellRing}
                                            title="Confirmação via WhatsApp"
                                            description="O pedido reserva o horário e abre uma conversa para confirmação manual."
                                        >
                                            <div className="rounded-xl border border-primary/20 bg-primary/5 p-4">
                                                <div className="flex gap-3">
                                                    <AlertCircle className="mt-0.5 size-5 shrink-0 text-primary" />
                                                    <div className="space-y-1">
                                                        <p className="text-sm font-semibold">
                                                            Sem pagamento online
                                                        </p>
                                                        <p className="text-xs leading-5 text-muted-foreground">
                                                            Após o agendamento,
                                                            o cliente será
                                                            encaminhado ao
                                                            WhatsApp do
                                                            profissional. Se ele
                                                            não tiver número,
                                                            usamos o WhatsApp da
                                                            unidade.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <p className="rounded-xl border border-border bg-muted/20 p-4 text-sm leading-6 text-muted-foreground">
                                                O fluxo atual registra o pedido
                                                como agendamento e abre o
                                                WhatsApp do profissional. Esta
                                                tela é apenas informativa; não
                                                há pagamento, cancelamento ou
                                                configuração adicional nesta
                                                etapa.
                                            </p>
                                        </SectionCard>
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
