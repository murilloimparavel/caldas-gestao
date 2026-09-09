import { Head, useHttp } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock3,
    Instagram,
    MapPin,
    MessageCircle,
    Star,
    UserRound,
    Globe2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useInitials } from '@/hooks/use-initials';
import { availability } from '@/routes/public_booking';
import { store } from '@/routes/public_booking/appointments';

type Address = {
    street?: string;
    number?: string;
    neighborhood?: string;
    city?: string;
    state?: string;
    postal_code?: string;
};
type Unit = {
    tenant_slug: string;
    slug: string;
    name: string;
    timezone: string;
    address: Address | null;
    description?: string | null;
    cover_image_url?: string | null;
    cover_url?: string | null;
    brand_color?: string | null;
    contacts?: {
        phone?: string | null;
        whatsapp?: string | null;
        instagram_url?: string | null;
        facebook_url?: string | null;
        website_url?: string | null;
    };
    gallery?: {
        url?: string | null;
        thumbnail_url?: string | null;
        alt_text?: string | null;
    }[];
    public_hours?: Record<
        string,
        {
            enabled?: boolean;
            starts_at?: string;
            ends_at?: string;
            start?: string;
            end?: string;
        }
    >;
    booking_flow?: 'service_first' | 'professional_first';
    seo?: { title?: string | null; description?: string | null };
    canonical_url?: string | null;
    is_preview?: boolean;
    sections?: Partial<Record<'hero' | 'services' | 'professionals' | 'gallery' | 'hours' | 'contact', boolean>>;
};
type Professional = { id: string; name: string; avatar_url?: string | null };
type Service = {
    id: string;
    name: string;
    description: string | null;
    duration_minutes: number;
    image_url?: string | null;
    thumbnail_url?: string | null;
    photo_url?: string | null;
    price_cents: number;
    professionals: Professional[];
};
type Props = {
    unit: Unit;
    services: Service[];
    professionals?: Professional[];
};
type AvailabilityQuery = {
    service_id: string;
    professional_id: string;
    date: string;
    from?: string;
    to?: string;
};
type AvailabilityResponse = {
    date: string;
    timezone: string;
    slots: { starts_at: string; ends_at: string }[];
};
type AppointmentData = {
    service_id: string;
    professional_id: string;
    starts_at: string;
    name: string;
    phone: string;
};
type AppointmentResponse = {
    appointment: { id: string; status: string };
    replayed: boolean;
    whatsapp_url?: string | null;
};
type Tab = 'details' | 'services' | 'professionals' | 'reviews';

const money = (cents: number): string =>
    new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(cents / 100);
const time = (iso: string, timezone: string): string =>
    new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: timezone,
    }).format(new Date(iso));
const today = (): string => new Date().toISOString().slice(0, 10);
const limit = (): string => {
    const date = new Date();
    date.setDate(date.getDate() + 31);

    return date.toISOString().slice(0, 10);
};
const addressLabel = (address: Address | null): string | null =>
    address
        ? [
              [address.street, address.number].filter(Boolean).join(', '),
              [address.neighborhood, address.city].filter(Boolean).join(' · '),
              [address.state, address.postal_code].filter(Boolean).join(' · '),
          ]
              .filter(Boolean)
              .join(' — ')
        : null;
const dayNames = [
    'Domingo',
    'Segunda-feira',
    'Terça-feira',
    'Quarta-feira',
    'Quinta-feira',
    'Sexta-feira',
    'Sábado',
];
const clockMinutes = (value: string | undefined): number | null => {
    if (!value) {
        return null;
    }

    const [hours, minutes] = value.split(':').map(Number);

    return Number.isFinite(hours) && Number.isFinite(minutes)
        ? hours * 60 + minutes
        : null;
};
const currentBusinessStatus = (
    unit: Unit,
): { label: string; open: boolean } => {
    if (!unit.public_hours) {
        return { label: 'Consulte a disponibilidade', open: false };
    }

    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: unit.timezone,
        weekday: 'short',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(new Date());
    const weekday = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(
        parts.find((part) => part.type === 'weekday')?.value ?? 'Sun',
    );
    const current =
        Number(parts.find((part) => part.type === 'hour')?.value ?? 0) * 60 +
        Number(parts.find((part) => part.type === 'minute')?.value ?? 0);
    const hours =
        unit.public_hours[String(weekday)] ?? unit.public_hours[weekday];
    const start = clockMinutes(hours?.starts_at ?? hours?.start);
    const end = clockMinutes(hours?.ends_at ?? hours?.end);
    const open = Boolean(
        hours &&
        hours.enabled !== false &&
        start !== null &&
        end !== null &&
        current >= start &&
        current < end,
    );

    return { label: open ? 'Aberto agora' : 'Fechado agora', open };
};
const routeArgs = (
    unit: Pick<Unit, 'slug' | 'tenant_slug'>,
): [string, string] => {
    if (typeof window === 'undefined') {
        return [unit.tenant_slug, unit.slug];
    }

    const parts = window.location.pathname.split('/').filter(Boolean);
    const index = parts.indexOf('book');

    return index >= 0 && parts[index + 2]
        ? [parts[index + 1], parts[index + 2]]
        : [unit.tenant_slug, unit.slug];
};

export default function PublicBooking({
    unit,
    services,
    professionals = [],
}: Props) {
    const [tab, setTab] = useState<Tab>('details');
    const [serviceId, setServiceId] = useState('');
    const [professionalId, setProfessionalId] = useState('');
    const [date, setDate] = useState('');
    const [slot, setSlot] = useState('');
    const [submitted, setSubmitted] = useState(false);
    const [whatsappUrl, setWhatsappUrl] = useState<string | null>(null);
    const [query, setQuery] = useState('');
    const getInitials = useInitials();
    const args = routeArgs(unit);
    const bookingFlow = unit.booking_flow ?? 'service_first';
    const sectionEnabled = (key: keyof NonNullable<Unit['sections']>): boolean =>
        unit.sections?.[key] !== false;
    const selectedService = useMemo(
        () => services.find((item) => item.id === serviceId) ?? null,
        [serviceId, services],
    );
    const selectedProfessional = selectedService?.professionals.find(
        (item) => item.id === professionalId,
    );
    const visibleServices = services.filter(
        (item) =>
            item.name.toLowerCase().includes(query.toLowerCase()) &&
            (bookingFlow === 'service_first' ||
                !professionalId ||
                item.professionals.some(
                    (person) => person.id === professionalId,
                )),
    );
    const address = addressLabel(unit.address);
    const businessStatus = currentBusinessStatus(unit);
    const coverUrl =
        unit.cover_image_url ?? unit.cover_url ?? unit.gallery?.[0]?.url;
    const availabilityRequest = useHttp<
        AvailabilityQuery,
        AvailabilityResponse
    >({ service_id: '', professional_id: '', date: '' });
    const appointmentRequest = useHttp<AppointmentData, AppointmentResponse>({
        service_id: '',
        professional_id: '',
        starts_at: '',
        name: '',
        phone: '',
    });
    useEffect(() => {
        if (!serviceId || !professionalId || !date) {
            return;
        }

        availabilityRequest.setData({
            service_id: serviceId,
            professional_id: professionalId,
            date,
        });
        void availabilityRequest.get(
            availability.url(args, {
                query: {
                    service_id: serviceId,
                    professional_id: professionalId,
                    date,
                },
            }),
            { onError: () => setSlot('') },
        ); // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [date, professionalId, serviceId]);
    const chooseService = (id: string): void => {
        setServiceId(id);

        if (bookingFlow === 'service_first') {
            setProfessionalId('');
        }

        setDate('');
        setSlot('');
        setTab('services');
        setSubmitted(false);
    };
    const chooseProfessional = (id: string): void => {
        setProfessionalId(id);
        setDate('');
        setSlot('');
    };
    const submit = async (
        event: React.FormEvent<HTMLFormElement>,
    ): Promise<void> => {
        event.preventDefault();

        if (!slot) {
            return;
        }

        appointmentRequest.setData((current) => ({
            ...current,
            service_id: serviceId,
            professional_id: professionalId,
            starts_at: slot,
        }));
        await appointmentRequest.post(store.url(args), {
            headers: {
                'X-Idempotency-Key':
                    typeof crypto.randomUUID === 'function'
                        ? crypto.randomUUID()
                        : `${Date.now()}-${Math.random()}`,
            },
            onSuccess: (response) => {
                setWhatsappUrl(response.whatsapp_url ?? null);
                setSubmitted(true);
            },
        });
    };
    const focusBooking = (): void => {
        setTab('services');
        window.setTimeout(() => {
            document.getElementById('booking-flow')?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }, 0);
    };

    if (submitted) {
        return (
            <PublicShell unit={unit}>
                <Head title={`Agendamento confirmado · ${unit.name}`} />
                <section className="mx-auto max-w-xl rounded-3xl border border-emerald-200 bg-white p-8 text-center shadow-sm dark:border-emerald-900 dark:bg-slate-900">
                    <CheckCircle2 className="mx-auto size-12 text-emerald-500" />
                    <p className="mt-4 text-sm font-semibold tracking-[0.16em] text-emerald-700 uppercase dark:text-emerald-300">
                        Pedido recebido
                    </p>
                    <h1 className="mt-3 font-display text-3xl font-semibold text-slate-950 dark:text-white">
                        Seu horário está reservado.
                    </h1>
                    <p className="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                        {selectedService?.name} com {selectedProfessional?.name}
                        , às {time(slot, unit.timezone)}. Aguarde a confirmação
                        do profissional.
                    </p>
                    {whatsappUrl ? (
                        <Button asChild className="mt-6 w-full">
                            <a
                                href={whatsappUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <MessageCircle />
                                Falar pelo WhatsApp
                            </a>
                        </Button>
                    ) : (
                        <p className="mt-6 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                            O negócio ainda não cadastrou um WhatsApp para
                            contato.
                        </p>
                    )}
                </section>
            </PublicShell>
        );
    }

    return (
        <PublicShell unit={unit}>
            <Head title={unit.seo?.title || `Agendar · ${unit.name}`}>
                <meta
                    name="description"
                    content={
                        unit.seo?.description ||
                        unit.description ||
                        `Agende seu horário em ${unit.name}.`
                    }
                />
                {unit.canonical_url ? (
                    <link rel="canonical" href={unit.canonical_url} />
                ) : null}
                {unit.is_preview ? (
                    <meta name="robots" content="noindex,nofollow,noarchive" />
                ) : null}
                {unit.cover_image_url ? (
                    <meta property="og:image" content={unit.cover_image_url} />
                ) : null}
                <meta
                    property="og:title"
                    content={unit.seo?.title || unit.name}
                />
                <meta
                    property="og:description"
                    content={
                        unit.seo?.description ||
                        unit.description ||
                        `Agende seu horário em ${unit.name}.`
                    }
                />
                <script type="application/ld+json">
                    {JSON.stringify({
                        '@context': 'https://schema.org',
                        '@type': 'BeautySalon',
                        name: unit.name,
                        description:
                            unit.seo?.description || unit.description || undefined,
                        url: unit.canonical_url || undefined,
                        image: unit.cover_image_url || undefined,
                        telephone: unit.contacts?.phone || unit.contacts?.whatsapp || undefined,
                        address: unit.address
                            ? {
                                  '@type': 'PostalAddress',
                                  streetAddress: [unit.address.street, unit.address.number]
                                      .filter(Boolean)
                                      .join(', '),
                                  addressLocality: unit.address.city,
                                  addressRegion: unit.address.state,
                                  postalCode: unit.address.postal_code,
                              }
                            : undefined,
                    })}
                </script>
            </Head>
            <div className="mx-auto max-w-6xl space-y-6">
                <header
                    className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900"
                    style={{
                        borderTopColor: unit.brand_color ?? '#2563eb',
                        borderTopWidth: '4px',
                    }}
                >
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400">
                                Atendimento com hora marcada
                            </p>
                            <h1 className="mt-2 max-w-3xl font-display text-3xl leading-tight font-semibold tracking-[-0.04em] sm:text-4xl">
                                {unit.name}
                            </h1>
                        </div>
                        <span
                            className={`inline-flex w-fit items-center rounded-full px-3 py-1.5 text-xs font-semibold ${businessStatus.open ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'}`}
                        >
                            <span
                                className={`mr-2 size-1.5 rounded-full ${businessStatus.open ? 'bg-emerald-500' : 'bg-slate-400'}`}
                            />
                            {businessStatus.label}
                        </span>
                    </div>
                    {unit.description && (
                        <p className="mt-4 max-w-2xl text-sm leading-7 text-slate-600 dark:text-slate-300">
                            {unit.description}
                        </p>
                    )}
                    {address && (
                        <p className="mt-5 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <MapPin className="size-4" />
                            {address}
                        </p>
                    )}
                </header>
                <nav
                    aria-label="Navegação pública"
                    className="flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1 dark:border-slate-800 dark:bg-slate-900"
                >
                    {(
                        [
                            ['details', 'Detalhes'],
                            ...(sectionEnabled('services') ? [['services', 'Serviços'] as [Tab, string]] : []),
                            ...(sectionEnabled('professionals') ? [['professionals', 'Profissionais'] as [Tab, string]] : []),
                            ['reviews', 'Avaliações'],
                        ] as [Tab, string][]
                    ).map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            role="tab"
                            aria-selected={tab === key}
                            aria-controls={`public-booking-panel-${key}`}
                            onClick={() => setTab(key)}
                            className={`rounded-xl px-4 py-2.5 text-sm font-medium whitespace-nowrap transition ${tab === key ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950' : 'text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white'}`}
                        >
                            {label}
                        </button>
                    ))}
                </nav>
                {tab === 'details' && (
                    <section className="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
                        <div className="space-y-5">
                            <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                {coverUrl ? (
                                    <img
                                        src={coverUrl}
                                        alt={`Imagem de ${unit.name}`}
                                        width={1280}
                                        height={640}
                                        fetchPriority="high"
                                        decoding="async"
                                        className="h-52 w-full object-cover sm:h-72"
                                    />
                                ) : (
                                    <div
                                        className="flex h-52 items-center justify-center text-white sm:h-72"
                                        style={{
                                            backgroundColor:
                                                unit.brand_color ?? '#2563eb',
                                        }}
                                    >
                                        <Globe2
                                            aria-hidden="true"
                                            className="size-12 opacity-70"
                                        />
                                    </div>
                                )}
                            </div>
                            {sectionEnabled('gallery') && unit.gallery?.length ? (
                                <InfoCard title="Galeria">
                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                        {unit.gallery.map((image, index) => (
                                            <a
                                                key={`${image.url}-${index}`}
                                                href={image.url ?? undefined}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="group block overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700"
                                            >
                                                <img
                                                    src={
                                                        image.thumbnail_url ??
                                                        image.url ??
                                                        undefined
                                                    }
                                                    alt={
                                                        image.alt_text ??
                                                        `Imagem ${index + 1} de ${unit.name}`
                                                    }
                                                    width={400}
                                                    height={400}
                                                    loading="lazy"
                                                    decoding="async"
                                                    className="aspect-square w-full object-cover transition group-hover:scale-105"
                                                />
                                            </a>
                                        ))}
                                    </div>
                                </InfoCard>
                            ) : null}
                            <InfoCard title="Sobre o espaço">
                                <p className="text-sm leading-7 text-slate-600 dark:text-slate-300">
                                    {unit.description ||
                                        'Conheça nosso espaço e escolha o melhor momento para seu atendimento.'}
                                </p>
                            </InfoCard>
                            {sectionEnabled('contact') && <InfoCard title="Contato">
                                <div className="space-y-3 text-sm text-slate-600 dark:text-slate-300">
                                    {unit.contacts?.phone && (
                                        <p>Telefone: {unit.contacts.phone}</p>
                                    )}
                                    {unit.contacts?.whatsapp && (
                                        <p className="flex items-center gap-2">
                                            <MessageCircle className="size-4" />
                                            WhatsApp: {unit.contacts.whatsapp}
                                        </p>
                                    )}
                                    {unit.contacts?.instagram_url && (
                                        <a
                                            href={unit.contacts.instagram_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex items-center gap-2 underline-offset-4 hover:underline"
                                        >
                                            <Instagram className="size-4" />
                                            Instagram
                                        </a>
                                    )}
                                    {unit.contacts?.website_url && (
                                        <a
                                            href={unit.contacts.website_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex items-center gap-2 underline-offset-4 hover:underline"
                                        >
                                            Site
                                        </a>
                                    )}
                                    {!unit.contacts?.phone &&
                                        !unit.contacts?.whatsapp &&
                                        !unit.contacts?.instagram_url &&
                                        !unit.contacts?.website_url && (
                                            <p>Contato ainda não informado.</p>
                                        )}
                                </div>
                            </InfoCard>}
                        </div>
                        <div className="space-y-5">
                            {sectionEnabled('hours') && <InfoCard title="Horário de atendimento">
                                <div className="mb-4 flex items-center gap-2 text-sm font-semibold">
                                    <span
                                        className={`size-2 rounded-full ${businessStatus.open ? 'bg-emerald-500' : 'bg-slate-400'}`}
                                    />
                                    <span>{businessStatus.label}</span>
                                </div>
                                <div className="space-y-1 text-sm text-slate-600 dark:text-slate-300">
                                    {Object.entries(unit.public_hours ?? {})
                                        .sort(
                                            ([first], [second]) =>
                                                Number(first) - Number(second),
                                        )
                                        .map(([day, hours]) => {
                                            const start =
                                                hours.starts_at ?? hours.start;
                                            const end =
                                                hours.ends_at ?? hours.end;

                                            return hours.enabled !== false &&
                                                start &&
                                                end ? (
                                                <p
                                                    key={day}
                                                    className="flex justify-between gap-4 border-b border-slate-100 py-2 last:border-0 dark:border-slate-800"
                                                >
                                                    <span>
                                                        {dayNames[
                                                            Number(day)
                                                        ] ?? day}
                                                    </span>
                                                    <span className="font-medium text-slate-950 dark:text-white">
                                                        {start} – {end}
                                                    </span>
                                                </p>
                                            ) : null;
                                        })}
                                    {!Object.keys(unit.public_hours ?? {})
                                        .length && (
                                        <p>
                                            Consulte os horários disponíveis
                                            durante o agendamento.
                                        </p>
                                    )}
                                </div>
                            </InfoCard>}
                            {address && (
                                <InfoCard title="Onde estamos">
                                    <p className="flex gap-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                        <MapPin className="size-4 shrink-0" />
                                        {address}
                                    </p>
                                </InfoCard>
                            )}
                        </div>
                    </section>
                )}
                {tab === 'reviews' && (
                    <InfoCard title="Avaliações">
                        <div className="flex items-center gap-3">
                            <Star className="size-5 fill-amber-400 text-amber-400" />
                            <span className="text-sm text-slate-600 dark:text-slate-300">
                                As avaliações dos clientes aparecerão aqui.
                            </span>
                        </div>
                    </InfoCard>
                )}
                {tab === 'professionals' && sectionEnabled('professionals') && (
                    <InfoCard title="Nossa equipe">
                        <div className="grid gap-3 sm:grid-cols-2">
                            {(professionals.length
                                ? professionals
                                : services
                                      .flatMap((item) => item.professionals)
                                      .filter(
                                          (person, index, all) =>
                                              all.findIndex(
                                                  (candidate) =>
                                                      candidate.id ===
                                                      person.id,
                                              ) === index,
                                      )
                            ).map((person) => (
                                <div
                                    key={person.id}
                                    className="flex items-center gap-3 rounded-2xl border border-slate-200 p-3 dark:border-slate-700"
                                >
                                    <Avatar>
                                        <AvatarImage
                                            src={person.avatar_url ?? undefined}
                                            alt={person.name}
                                        />
                                        <AvatarFallback>
                                            {getInitials(person.name) || (
                                                <UserRound className="size-4" />
                                            )}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="text-sm font-semibold">
                                        {person.name}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </InfoCard>
                )}
                {tab === 'services' && sectionEnabled('services') && (
                    <form
                        id="booking-flow"
                        onSubmit={submit}
                        className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.75fr)]"
                    >
                        <div className="flex flex-col gap-5">
                            <InfoCard
                                className={
                                    bookingFlow === 'service_first'
                                        ? 'order-1'
                                        : 'order-2'
                                }
                                title="Escolha um serviço"
                            >
                                <Input
                                    aria-label="Buscar serviço"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Procurar serviço"
                                    className="mb-4"
                                />
                                <div className="grid gap-3">
                                    {visibleServices.length ? (
                                        visibleServices.map((service) => (
                                            <button
                                                type="button"
                                                key={service.id}
                                                onClick={() =>
                                                    chooseService(service.id)
                                                }
                                                aria-pressed={
                                                    serviceId === service.id
                                                }
                                                className={`flex items-start gap-3 rounded-2xl border p-4 text-left transition ${serviceId === service.id ? 'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950' : 'border-slate-200 bg-white hover:border-slate-400 dark:border-slate-700 dark:bg-slate-900'}`}
                                            >
                                                {(service.thumbnail_url ||
                                                    service.image_url) && (
                                                    <img
                                                        src={
                                                            service.thumbnail_url ||
                                                            service.image_url ||
                                                            undefined
                                                        }
                                                        alt=""
                                                        width={56}
                                                        height={56}
                                                        loading="lazy"
                                                        decoding="async"
                                                        className="size-14 shrink-0 rounded-xl object-cover"
                                                    />
                                                )}
                                                <span className="min-w-0 flex-1">
                                                    <span className="block font-semibold">
                                                        {service.name}
                                                    </span>
                                                    {service.description && (
                                                        <span className="mt-1 block text-sm opacity-70">
                                                            {
                                                                service.description
                                                            }
                                                        </span>
                                                    )}
                                                    <span className="mt-2 flex items-center gap-2 text-xs opacity-70">
                                                        <Clock3 className="size-3" />
                                                        {
                                                            service.duration_minutes
                                                        }{' '}
                                                        min
                                                    </span>
                                                </span>
                                                <span className="shrink-0 text-sm font-semibold">
                                                    {money(service.price_cents)}
                                                </span>
                                            </button>
                                        ))
                                    ) : (
                                        <p className="rounded-xl bg-slate-50 p-4 text-sm text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                            Nenhum serviço encontrado.
                                        </p>
                                    )}
                                </div>
                            </InfoCard>
                            <InfoCard
                                className={
                                    bookingFlow === 'professional_first'
                                        ? 'order-1'
                                        : 'order-2'
                                }
                                title="Escolha o profissional"
                            >
                                {(!professionalId &&
                                    bookingFlow === 'professional_first') ||
                                (!selectedService &&
                                    bookingFlow === 'service_first') ? (
                                    <p className="text-sm text-slate-600 dark:text-slate-300">
                                        {bookingFlow === 'professional_first'
                                            ? 'Escolha um profissional primeiro.'
                                            : 'Escolha um serviço primeiro.'}
                                    </p>
                                ) : (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {(bookingFlow === 'professional_first'
                                            ? professionals
                                            : (selectedService?.professionals ??
                                              [])
                                        ).map((person) => (
                                            <button
                                                type="button"
                                                key={person.id}
                                                onClick={() =>
                                                    chooseProfessional(
                                                        person.id,
                                                    )
                                                }
                                                aria-pressed={
                                                    professionalId === person.id
                                                }
                                                className={`flex items-center gap-3 rounded-2xl border p-3 text-left ${professionalId === person.id ? 'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950' : 'border-slate-200 dark:border-slate-700'}`}
                                            >
                                                <Avatar>
                                                    <AvatarImage
                                                        src={
                                                            person.avatar_url ??
                                                            undefined
                                                        }
                                                        alt={person.name}
                                                    />
                                                    <AvatarFallback>
                                                        {getInitials(
                                                            person.name,
                                                        )}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <span className="truncate text-sm font-semibold">
                                                    {person.name}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </InfoCard>
                        </div>
                        <div className="space-y-5">
                            <InfoCard title="Data e horário">
                                <Label htmlFor="date">Data</Label>
                                <Input
                                    id="date"
                                    type="date"
                                    min={today()}
                                    max={limit()}
                                    value={date}
                                    onChange={(event) => {
                                        setDate(event.target.value);
                                        setSlot('');
                                    }}
                                    disabled={!professionalId}
                                    className="mt-2"
                                />
                                {availabilityRequest.processing && (
                                    <p
                                        role="status"
                                        className="mt-3 text-sm text-slate-500"
                                    >
                                        Buscando horários…
                                    </p>
                                )}
                                {availabilityRequest.response?.slots?.length ? (
                                    <div className="mt-4 grid grid-cols-2 gap-2">
                                        {availabilityRequest.response.slots.map(
                                            (item) => (
                                                <button
                                                    type="button"
                                                    key={item.starts_at}
                                                    onClick={() =>
                                                        setSlot(item.starts_at)
                                                    }
                                                    className={`rounded-xl border px-3 py-3 text-sm font-semibold ${slot === item.starts_at ? 'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950' : 'border-slate-200 dark:border-slate-700'}`}
                                                >
                                                    {time(
                                                        item.starts_at,
                                                        availabilityRequest
                                                            .response
                                                            ?.timezone ??
                                                            unit.timezone,
                                                    )}
                                                </button>
                                            ),
                                        )}
                                    </div>
                                ) : null}
                            </InfoCard>
                            <InfoCard title="Seus dados">
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="name">Nome</Label>
                                        <Input
                                            id="name"
                                            value={appointmentRequest.data.name}
                                            onChange={(event) =>
                                                appointmentRequest.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                            disabled={!slot}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="phone">Telefone</Label>
                                        <Input
                                            id="phone"
                                            value={
                                                appointmentRequest.data.phone
                                            }
                                            onChange={(event) =>
                                                appointmentRequest.setData(
                                                    'phone',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                            disabled={!slot}
                                        />
                                    </div>
                                    <Textarea className="hidden" name="notes" />
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={
                                            !slot ||
                                            !appointmentRequest.data.name ||
                                            !appointmentRequest.data.phone ||
                                            appointmentRequest.processing
                                        }
                                    >
                                        <MessageCircle />
                                        {appointmentRequest.processing
                                            ? 'Confirmando…'
                                            : 'Confirmar e chamar no WhatsApp'}
                                    </Button>
                                </div>
                            </InfoCard>
                        </div>
                    </form>
                )}
                <button
                    type="button"
                    onClick={focusBooking}
                    className="fixed right-4 bottom-4 left-4 z-20 rounded-2xl px-5 py-3.5 text-sm font-semibold text-white shadow-xl transition hover:brightness-110 focus-visible:ring-2 focus-visible:ring-offset-2 sm:hidden"
                    style={{ backgroundColor: unit.brand_color ?? '#111827' }}
                >
                    Agendar agora
                </button>
            </div>
        </PublicShell>
    );
}

function InfoCard({
    className,
    title,
    children,
}: {
    className?: string;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section
            className={`rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900 ${className ?? ''}`}
        >
            <h2 className="font-display text-xl font-semibold text-slate-950 dark:text-white">
                {title}
            </h2>
            <div className="mt-5">{children}</div>
        </section>
    );
}
function PublicShell({
    unit,
    children,
}: {
    unit: Unit;
    children: React.ReactNode;
}) {
    return (
        <main className="min-h-dvh bg-[#f7f5f0] px-4 py-5 text-slate-950 sm:px-6 sm:py-8 dark:bg-slate-950 dark:text-white">
            <div className="mx-auto mb-6 flex max-w-6xl items-center justify-between">
                <span className="font-display text-lg font-semibold tracking-tight">
                    {unit.name}
                </span>
                <span className="rounded-full border border-slate-200 bg-white/70 px-3 py-1 text-xs font-medium text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                    Agendamento online
                </span>
            </div>
            {children}
            <footer className="mx-auto mt-10 max-w-6xl text-center text-xs text-slate-500 dark:text-slate-400">
                Ao confirmar, seus dados serão usados somente para organizar
                este atendimento.
            </footer>
        </main>
    );
}

PublicBooking.layout = null;
