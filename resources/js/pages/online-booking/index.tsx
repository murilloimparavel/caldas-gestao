import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    BellRing,
    CalendarDays,
    Check,
    CheckCircle2,
    ClipboardCheck,
    Clock3,
    Copy,
    ExternalLink,
    GalleryHorizontalEnd,
    Globe2,
    ImagePlus,
    Link2,
    Palette,
    Scissors,
    Share2,
    Smartphone,
    UserRound,
    UsersRound,
    XCircle,
    Settings2,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
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
import galleryRoutes from '@/routes/online_booking/gallery';
import tenantDomains from '@/routes/tenant-domains';
import type { SharedPageProps } from '@/types';

type BookingItem = {
    id: string;
    name: string;
    status: 'active' | 'inactive';
    online_booking_enabled: boolean;
    lock_version: number;
    description?: string | null;
    duration_minutes?: number;
    price_cents?: number;
    image_url?: string | null;
    avatar_url?: string | null;
};
type Readiness = {
    unit_enabled: boolean;
    has_active_service: boolean;
    has_active_professional: boolean;
    has_service_professional_pair: boolean;
    publishable: boolean;
};
type PublicSettings = {
    public_domain_id?: string | null;
    description?: string | null;
    logo_url?: string | null;
    cover_url?: string | null;
    cover_image_url?: string | null;
    whatsapp?: string | null;
    phone?: string | null;
    instagram?: string | null;
    facebook?: string | null;
    website?: string | null;
    brand_color?: string | null;
    accent_color?: string | null;
    booking_flow?: 'service_first' | 'professional_first';
    flow?: 'service_first' | 'professional_first';
    minimum_notice_minutes?: number | null;
    public_slug?: string | null;
    public_hours?: Record<
        string,
        { enabled?: boolean; starts_at?: string; ends_at?: string }
    > | null;
};
type Props = {
    unit: {
        id: string;
        name: string;
        slug: string;
        online_booking_enabled: boolean;
        lock_version: number;
        address?: string | Record<string, string> | null;
        settings?: PublicSettings | null;
    };
    publicUrl: string | null;
    publicDomains: { id: string; hostname: string }[];
    services: BookingItem[];
    professionals: BookingItem[];
    readiness: Readiness;
    publication?: {
        id: string;
        status: 'unpublished' | 'published';
        draft_revision: number;
        published_at?: string | null;
        unpublished_at?: string | null;
        lock_version: number;
    } | null;
    draft?: { revision: number } | null;
    activePublication?: {
        id: string;
        version: number;
        source_revision: number;
        published_at?: string | null;
        template_key: string;
    } | null;
    settings?: PublicSettings | null;
    cover?: string | null;
    coverUploadUrl?: string | null;
    coverDeleteUrl?: string | null;
    gallery?: {
        id: string;
        url?: string | null;
        path?: string | null;
        alt?: string | null;
        alt_text?: string | null;
        position?: number;
    }[];
};
type TabKey =
    | 'details'
    | 'settings'
    | 'link'
    | 'gallery'
    | 'services'
    | 'hours'
    | 'confirmation';
const tabs: { key: TabKey; label: string; icon: typeof Globe2 }[] = [
    { key: 'details', label: 'Detalhes', icon: Globe2 },
    { key: 'settings', label: 'Configurações', icon: Settings2 },
    { key: 'link', label: 'Link público', icon: Link2 },
    { key: 'gallery', label: 'Galeria', icon: GalleryHorizontalEnd },
    { key: 'services', label: 'Serviços', icon: Scissors },
    { key: 'hours', label: 'Horários', icon: Clock3 },
    { key: 'confirmation', label: 'Confirmação', icon: BellRing },
];

function SelectionCard({ item, group }: { item: BookingItem; group: string }) {
    const active = item.status === 'active';
    const id = `${group}-${item.id}`;

    return (
        <label
            htmlFor={id}
            className={`flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border p-3 transition-colors ${active ? 'border-border bg-background hover:border-primary/50 has-checked:border-primary/70 has-checked:bg-primary/5' : 'cursor-not-allowed border-border/60 bg-muted/30 opacity-65'}`}
        >
            <input
                id={id}
                name={`${group}_ids[]`}
                type="checkbox"
                value={item.id}
                defaultChecked={active && item.online_booking_enabled}
                disabled={!active}
                className="size-5 rounded border-input accent-primary focus-visible:ring-2 focus-visible:ring-ring"
            />
            <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">
                {item.name}
            </span>
            <Badge variant="outline" className="shrink-0 text-[10px]">
                {active ? 'Ativo' : 'Inativo'}
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
            <span
                className={ready ? 'text-foreground' : 'text-muted-foreground'}
            >
                {label}
            </span>
        </li>
    );
}
function Field({
    label,
    name,
    defaultValue,
    placeholder,
    type = 'text',
    readOnly = false,
}: {
    label: string;
    name: string;
    defaultValue?: string | null;
    placeholder?: string;
    type?: string;
    readOnly?: boolean;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                defaultValue={defaultValue ?? ''}
                placeholder={placeholder}
                readOnly={readOnly}
                aria-readonly={readOnly}
                className={readOnly ? 'bg-muted/30' : undefined}
            />
        </div>
    );
}
function SectionCard({
    icon: Icon,
    title,
    description,
    children,
}: {
    icon: typeof Globe2;
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <Card className="overflow-hidden">
            <CardHeader className="border-b border-border/60 bg-muted/20">
                <CardTitle className="flex items-center gap-2 text-base">
                    <span className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Icon aria-hidden="true" className="size-4" />
                    </span>
                    {title}
                </CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-5 pt-5">{children}</CardContent>
        </Card>
    );
}

type CoverEditorProps = {
    url?: string | null;
    uploadUrl?: string | null;
    deleteUrl?: string | null;
    onUploaded: () => void;
};

function CoverEditor({
    url,
    uploadUrl,
    deleteUrl,
    onUploaded,
}: CoverEditorProps) {
    const [message, setMessage] = useState<string | null>(null);

    const upload = (file: File): void => {
        if (!uploadUrl) {
            setMessage('A capa ainda não está habilitada para esta unidade.');

            return;
        }

        const data = new FormData();
        data.append('image', file);
        router.post(uploadUrl, data, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setMessage('Capa atualizada.');
                onUploaded();
            },
        });
    };

    const remove = (): void => {
        if (!deleteUrl) {
            setMessage('A remoção da capa ainda não está habilitada.');

            return;
        }

        router.delete(deleteUrl, {
            preserveScroll: true,
            onSuccess: () => {
                setMessage('Capa removida.');
                onUploaded();
            },
        });
    };

    return (
        <div className="flex flex-col items-center gap-3 border-b border-border/60 pb-5 sm:flex-row sm:items-start">
            <div className="relative flex h-36 w-full max-w-60 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-border bg-muted/40 shadow-sm sm:h-32 sm:w-52">
                {url ? (
                    <img
                        src={url}
                        alt="Capa atual da unidade"
                        className="size-full object-cover"
                    />
                ) : (
                    <div className="flex flex-col items-center gap-2 px-4 text-center text-muted-foreground">
                        <ImagePlus aria-hidden="true" className="size-8" />
                        <span className="text-xs">Nenhuma capa definida</span>
                    </div>
                )}
            </div>
            <div className="flex min-w-0 flex-1 flex-col items-center gap-2 text-center sm:items-start sm:text-left">
                <div>
                    <p className="text-sm font-semibold">Imagem principal</p>
                    <p className="mt-1 max-w-md text-xs leading-5 text-muted-foreground">
                        É a imagem em destaque no topo do canal público. A
                        galeria continua independente e reúne as demais fotos.
                    </p>
                </div>
                <div className="flex flex-wrap justify-center gap-2 sm:justify-start">
                    <label className="inline-flex cursor-pointer items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground shadow-xs transition hover:bg-primary/90 has-disabled:pointer-events-none has-disabled:opacity-50">
                        <ImagePlus aria-hidden="true" className="size-4" />
                        Alterar
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            className="sr-only"
                            disabled={!uploadUrl}
                            onChange={(event) => {
                                const file = event.target.files?.[0];

                                if (file) {
                                    upload(file);
                                }

                                event.currentTarget.value = '';
                            }}
                        />
                    </label>
                    {url && (
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={remove}
                            disabled={!deleteUrl}
                        >
                            Remover
                        </Button>
                    )}
                </div>
                <p className="text-[11px] text-muted-foreground">
                    JPG, PNG ou WEBP · até 5 MB
                </p>
                {message && (
                    <p role="status" className="text-xs text-primary">
                        {message}
                    </p>
                )}
            </div>
        </div>
    );
}

function PublicPreview({
    unit,
    settings,
    gallery,
    services,
}: {
    unit: Props['unit'];
    settings: PublicSettings;
    gallery: NonNullable<Props['gallery']>;
    services: BookingItem[];
}) {
    const coverUrl =
        settings.cover_url ?? settings.cover_image_url ?? settings.logo_url;
    const visibleServices = services.filter(
        (item) => item.status === 'active' && item.online_booking_enabled,
    );

    return (
        <Card className="border-primary/20 shadow-sm xl:sticky xl:top-20 xl:max-h-[calc(100dvh-6rem)] xl:overflow-y-auto">
            <CardHeader className="border-b border-border/60 bg-muted/20 pb-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <CardTitle className="text-base">
                            Prévia pública
                        </CardTitle>
                        <CardDescription className="mt-1">
                            Veja como o celular do cliente exibirá a unidade.
                        </CardDescription>
                    </div>
                    <Smartphone
                        aria-hidden="true"
                        className="size-4 text-primary"
                    />
                </div>
            </CardHeader>
            <CardContent className="flex justify-center bg-muted/10 p-5">
                <div className="w-full max-w-[18rem] rounded-[2rem] border-[7px] border-slate-950 bg-slate-950 p-1 shadow-2xl dark:border-slate-700">
                    <div className="relative flex h-[33rem] flex-col overflow-hidden rounded-[1.45rem] bg-background">
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
                        <div className="flex gap-4 overflow-hidden border-b border-border/70 px-4 pt-3 text-[11px] font-semibold text-muted-foreground">
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
                                    <p className="mt-1 line-clamp-2 text-[11px] leading-4 text-muted-foreground">
                                        {settings.description ||
                                            'Escolha um serviço e reserve seu horário.'}
                                    </p>
                                </div>
                                <div className="rounded-xl border border-border/70 p-3">
                                    <p className="text-[11px] font-bold">
                                        Contato
                                    </p>
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        {settings.whatsapp ||
                                            settings.phone ||
                                            'WhatsApp não informado'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[11px] font-bold">
                                        Serviços
                                    </p>
                                    <div className="mt-2 space-y-2">
                                        {visibleServices
                                            .slice(0, 3)
                                            .map((service) => (
                                                <div
                                                    key={service.id}
                                                    className="flex items-center justify-between gap-2 rounded-lg border border-border/70 px-2.5 py-2"
                                                >
                                                    <span className="truncate text-[11px] font-medium">
                                                        {service.name}
                                                    </span>
                                                    {service.price_cents !==
                                                        undefined && (
                                                        <span className="shrink-0 text-[10px] text-muted-foreground">
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
                                            <p className="text-[11px] text-muted-foreground">
                                                Cadastre um serviço ativo.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="border-t border-border/70 bg-background p-3">
                            <div className="rounded-xl bg-primary px-4 py-2.5 text-center text-xs font-semibold text-primary-foreground shadow-sm">
                                Agendar agora
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function OnlineBookingIndex({
    unit,
    publicUrl,
    publicDomains,
    services,
    professionals,
    readiness,
    publication = null,
    draft = null,
    activePublication = null,
    settings: rootSettings,
    gallery = [],
    cover,
    coverUploadUrl: coverUploadUrlProp,
    coverDeleteUrl: coverDeleteUrlProp,
}: Props) {
    const { flash } = usePage<SharedPageProps>().props;
    const settings = rootSettings ?? unit.settings ?? {};
    const [activeTab, setActiveTab] = useState<TabKey>('details');
    const [copied, setCopied] = useState(false);
    const [publicationError, setPublicationError] = useState<string | null>(
        null,
    );
    const [publicationProcessing, setPublicationProcessing] = useState(false);
    const [galleryItems, setGalleryItems] = useState(gallery);
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
    const copyPublicUrl = async () => {
        if (!publicUrl || !navigator.clipboard) {
            return;
        }

        await navigator.clipboard.writeText(publicUrl);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2200);
    };
    const previewUrl = publicUrl
        ? `${publicUrl}${publicUrl.includes('?') ? '&' : '?'}utm_source=caldas_gestao&utm_medium=preview&utm_campaign=online_booking`
        : null;
    const publishDraft = (): void => {
        if (!draft) {
            return;
        }

        setPublicationError(null);
        setPublicationProcessing(true);
        router.post(
            onlineBooking.publish.url(),
            { revision: draft.revision },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setPublicationError(
                        errors.publication ??
                            'Não foi possível publicar. Revise os requisitos e tente novamente.',
                    ),
                onFinish: () => setPublicationProcessing(false),
            },
        );
    };
    const unpublishSite = (): void => {
        if (!window.confirm('Retirar esta página do ar agora?')) {
            return;
        }

        setPublicationError(null);
        setPublicationProcessing(true);
        router.post(
            onlineBooking.unpublish.url(),
            {},
            {
                preserveScroll: true,
                onError: () =>
                    setPublicationError(
                        'Não foi possível retirar a página do ar. Tente novamente.',
                    ),
                onFinish: () => setPublicationProcessing(false),
            },
        );
    };
    const uploadGalleryImage = (file: File): void => {
        const data = new FormData();
        data.append('image', file);
        router.post(galleryRoutes.store.url(), data, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['gallery'] }),
        });
    };
    const updateGalleryAlt = (id: string, alt_text: string): void => {
        router.patch(
            galleryRoutes.update.url(id),
            { alt_text },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setGalleryItems((items) =>
                        items.map((item) =>
                            item.id === id
                                ? { ...item, alt: alt_text, alt_text }
                                : item,
                        ),
                    ),
            },
        );
    };
    const deleteGalleryImage = (id: string): void => {
        router.delete(galleryRoutes.destroy.url(id), {
            preserveScroll: true,
            onSuccess: () =>
                setGalleryItems((items) =>
                    items.filter((item) => item.id !== id),
                ),
        });
    };
    const moveGalleryImage = (id: string, direction: -1 | 1): void => {
        const index = galleryItems.findIndex((item) => item.id === id);
        const nextIndex = index + direction;

        if (index < 0 || nextIndex < 0 || nextIndex >= galleryItems.length) {
            return;
        }

        const reordered = [...galleryItems];
        [reordered[index], reordered[nextIndex]] = [
            reordered[nextIndex],
            reordered[index],
        ];
        setGalleryItems(reordered);
        router.post(
            galleryRoutes.reorder.url(),
            { image_ids: reordered.map((item) => item.id) },
            { preserveScroll: true, onError: () => setGalleryItems(gallery) },
        );
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
                            <div className="overflow-x-auto border-b border-border/70">
                                <nav
                                    aria-label="Configuração do agendamento online"
                                    className="flex min-w-max gap-1"
                                    role="tablist"
                                >
                                    {tabs.map(({ key, label, icon: Icon }) => (
                                        <button
                                            key={key}
                                            type="button"
                                            role="tab"
                                            aria-selected={activeTab === key}
                                            onClick={() => setActiveTab(key)}
                                            className={`relative flex items-center gap-2 px-3 py-3 text-sm font-medium transition-colors after:absolute after:inset-x-2 after:bottom-0 after:h-0.5 ${activeTab === key ? 'text-primary after:bg-primary' : 'text-muted-foreground after:bg-transparent hover:text-foreground'}`}
                                        >
                                            <Icon
                                                aria-hidden="true"
                                                className="size-4"
                                            />
                                            {label}
                                        </button>
                                    ))}
                                </nav>
                            </div>
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
                                                                    className="h-7 text-[11px]"
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
                                                    <span className="text-[11px] text-muted-foreground">
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
