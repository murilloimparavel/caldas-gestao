import { Head, useHttp } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock3,
    ChevronRight,
    ArrowLeft,
    CalendarDays,
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
import {
    AtelierProgress,
    AtelierSectionHeading,
} from '@/pages/public-booking/components/atelier-booking-ui';

type Address = {
    street?: string;
    number?: string;
    neighborhood?: string;
    city?: string;
    state?: string;
    postal_code?: string;
};
type BookingAppearance = {
    brand_name?: string | null;
    headline?: string | null;
    subheadline?: string | null;
    primary_color?: string | null;
    background_color?: string | null;
    cta_label?: string | null;
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
    template_key?: 'essential' | 'atelier-barber' | string | null;
    logo_image_url?: string | null;
    appearance?: BookingAppearance | null;
    settings?: {
        appearance?: BookingAppearance | null;
        logo_image_url?: string | null;
    } | null;
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
    sections?: Partial<
        Record<
            | 'hero'
            | 'services'
            | 'professionals'
            | 'gallery'
            | 'hours'
            | 'contact',
            boolean
        >
    >;
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
    category_name?: string | null;
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
    email?: string;
    notes?: string;
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
const formatDateTimeSlot = (iso: string, timezone: string): string => {
    const formattedDate = new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        timeZone: timezone,
    }).format(new Date(iso));

    return `${formattedDate} às ${time(iso, timezone)}`;
};
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
const safeColor = (
    value: string | null | undefined,
    fallback: string,
): string => (value && /^#[0-9a-fA-F]{6}$/.test(value) ? value : fallback);
const errorText = (value: unknown): string | null => {
    if (Array.isArray(value)) {
        return value.length > 0 ? String(value[0]) : null;
    }

    return typeof value === 'string' && value.trim() !== '' ? value : null;
};
type ResolvedBookingAppearance = Record<keyof BookingAppearance, string>;
const resolveAppearance = (unit: Unit): ResolvedBookingAppearance => ({
    brand_name:
        unit.appearance?.brand_name?.trim() ||
        unit.settings?.appearance?.brand_name?.trim() ||
        unit.name,
    headline:
        unit.appearance?.headline?.trim() ||
        unit.settings?.appearance?.headline?.trim() ||
        'Agende seu horário.',
    subheadline:
        unit.appearance?.subheadline?.trim() ||
        unit.settings?.appearance?.subheadline?.trim() ||
        'Escolha um serviço para começar seu agendamento.',
    primary_color: safeColor(
        unit.appearance?.primary_color ??
            unit.settings?.appearance?.primary_color ??
            unit.brand_color,
        unit.template_key === 'atelier-barber' ? '#d4af37' : '#2563eb',
    ),
    background_color: safeColor(
        unit.appearance?.background_color ??
            unit.settings?.appearance?.background_color,
        unit.template_key === 'atelier-barber' ? '#0d0d0c' : '#f7f5f0',
    ),
    cta_label:
        unit.appearance?.cta_label?.trim() ||
        unit.settings?.appearance?.cta_label?.trim() ||
        'Confirmar agendamento',
});
const appearanceStyle = (
    appearance: ResolvedBookingAppearance,
): React.CSSProperties =>
    ({
        '--booking-primary': appearance.primary_color,
        '--booking-background': appearance.background_color,
    }) as React.CSSProperties;
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
    const appearance = resolveAppearance(unit);
    const [tab, setTab] = useState<Tab>('details');
    const [serviceId, setServiceId] = useState('');
    const [selectedServiceIds, setSelectedServiceIds] = useState<string[]>([]);
    const [professionalId, setProfessionalId] = useState('');
    const [date, setDate] = useState('');
    const [slot, setSlot] = useState('');
    const [submitted, setSubmitted] = useState(false);
    const [whatsappUrl, setWhatsappUrl] = useState<string | null>(null);
    const [bookingError, setBookingError] = useState<string | null>(null);
    const [bookingErrorKind, setBookingErrorKind] = useState<
        'catalog' | 'slot' | 'generic' | null
    >(null);
    const [query, setQuery] = useState('');
    const [serviceCategory, setServiceCategory] = useState('Todos');
    const getInitials = useInitials();
    const args = routeArgs(unit);
    const bookingFlow = unit.booking_flow ?? 'service_first';
    const sectionEnabled = (
        key: keyof NonNullable<Unit['sections']>,
    ): boolean => unit.sections?.[key] !== false;
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
        email: '',
        notes: '',
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
            {
                onError: () => {
                    setSlot('');
                    setBookingError(
                        'Não foi possível carregar os horários. Escolha outra data ou tente novamente.',
                    );
                    setBookingErrorKind('slot');
                },
            },
        ); // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [date, professionalId, serviceId]);
    const chooseService = (id: string): void => {
        const nextIds = selectedServiceIds.includes(id)
            ? selectedServiceIds.filter((selectedId) => selectedId !== id)
            : [...selectedServiceIds, id];

        setSelectedServiceIds(nextIds);
        setServiceId(nextIds[0] ?? '');

        if (bookingFlow === 'service_first') {
            setProfessionalId('');
        }

        setDate('');
        setSlot('');
        setBookingError(null);
        setBookingErrorKind(null);
        setTab('services');
        setSubmitted(false);
    };
    const chooseProfessional = (id: string): void => {
        setProfessionalId(id);
        setDate('');
        setSlot('');
        setBookingError(null);
        setBookingErrorKind(null);
    };
    const submit = async (
        event: React.FormEvent<HTMLFormElement>,
    ): Promise<void> => {
        event.preventDefault();

        if (!slot) {
            return;
        }

        appointmentRequest.transform((data) => {
            const payload: Record<string, unknown> = { ...data };

            if (!data.email?.trim()) {
                delete payload.email;
            }

            if (!data.notes?.trim()) {
                delete payload.notes;
            }

            return payload;
        });

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
                setBookingError(null);
                setBookingErrorKind(null);
                setWhatsappUrl(response.whatsapp_url ?? null);
                setSubmitted(true);
            },
            onError: (errors) => {
                const catalogError =
                    errorText(errors.service_id) ||
                    errorText(errors.professional_id);
                const slotError = errorText(errors.starts_at);

                if (catalogError) {
                    setBookingError(
                        catalogError ||
                            'Este serviço ou profissional não está mais disponível para agendamento.',
                    );
                    setBookingErrorKind('catalog');
                } else if (slotError) {
                    setBookingError(
                        slotError ||
                            'Esse horário acabou de ficar indisponível. Escolha outro horário.',
                    );
                    setBookingErrorKind('slot');
                    setSlot('');
                } else {
                    setBookingError(
                        errorText(
                            (errors as Record<string, unknown>).message,
                        ) ||
                            'Não foi possível concluir o agendamento. Revise os dados e tente novamente.',
                    );
                    setBookingErrorKind('generic');
                }
            },
        });
    };
    const recoverBookingError = (): void => {
        setBookingError(null);
        setBookingErrorKind(null);
        setSlot('');

        if (bookingErrorKind === 'catalog') {
            setServiceId('');
            setProfessionalId('');
            setDate('');
        }
    };
    const rawContactPhone = unit.contacts?.whatsapp || unit.contacts?.phone;
    const cleanPhone = rawContactPhone
        ? rawContactPhone.replace(/\D/g, '')
        : '';
    const waPhone = cleanPhone
        ? cleanPhone.length <= 11 && !cleanPhone.startsWith('55')
            ? `55${cleanPhone}`
            : cleanPhone
        : '';

    const formattedSlotDate = slot
        ? new Intl.DateTimeFormat('pt-BR', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
              timeZone: unit.timezone,
          }).format(new Date(slot))
        : '';
    const formattedSlotTime = slot ? time(slot, unit.timezone) : '';

    const preformattedWaMessage = [
        `Olá! Acabei de agendar um horário em *${unit.name}*:`,
        '',
        `👤 *Cliente:* ${appointmentRequest.data.name || 'Cliente'}`,
        `✂️ *Serviço:* ${selectedService?.name || 'Serviço'}`,
        selectedProfessional
            ? `💈 *Profissional:* ${selectedProfessional.name}`
            : null,
        formattedSlotDate ? `📅 *Data:* ${formattedSlotDate}` : null,
        formattedSlotTime ? `⏰ *Horário:* ${formattedSlotTime}` : null,
        '',
        'Gostaria de confirmar o agendamento!',
    ]
        .filter((line): line is string => line !== null)
        .join('\n');

    const generatedWhatsappUrl = waPhone
        ? `https://wa.me/${waPhone}?text=${encodeURIComponent(preformattedWaMessage)}`
        : null;

    const finalWhatsappUrl = whatsappUrl || generatedWhatsappUrl;

    if (unit.template_key === 'atelier-barber') {
        return (
            <AtelierBarberView
                unit={unit}
                appearance={appearance}
                logoUrl={unit.logo_image_url ?? unit.settings?.logo_image_url}
                services={services}
                professionals={professionals}
                selectedService={selectedService}
                selectedServiceIds={selectedServiceIds}
                serviceCategory={serviceCategory}
                selectedProfessional={selectedProfessional}
                serviceId={serviceId}
                professionalId={professionalId}
                date={date}
                slot={slot}
                query={query}
                slots={availabilityRequest.response?.slots ?? []}
                availabilityTimezone={
                    availabilityRequest.response?.timezone ?? unit.timezone
                }
                customerName={appointmentRequest.data.name}
                customerPhone={appointmentRequest.data.phone}
                customerEmail={appointmentRequest.data.email ?? ''}
                customerNotes={appointmentRequest.data.notes ?? ''}
                processing={appointmentRequest.processing}
                submitted={submitted}
                bookingError={bookingError}
                bookingErrorKind={bookingErrorKind}
                finalWhatsappUrl={finalWhatsappUrl}
                onQueryChange={setQuery}
                onCategoryChange={setServiceCategory}
                onServiceChange={chooseService}
                onProfessionalChange={chooseProfessional}
                onDateChange={setDate}
                onSlotChange={setSlot}
                onCustomerChange={(field, value) =>
                    appointmentRequest.setData(field, value)
                }
                onRecoverBookingError={recoverBookingError}
                onSubmit={submit}
            />
        );
    }

    const scrollToStep = (elementId: string): void => {
        if (tab !== 'services') {
            setTab('services');
        }

        window.setTimeout(() => {
            const el = document.getElementById(elementId);

            if (el) {
                el.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            }
        }, 50);
    };

    const handleBottomBarAction = (): void => {
        if (!selectedService) {
            scrollToStep('booking-step-service');
        } else if (!slot) {
            scrollToStep('booking-step-datetime');
        } else {
            scrollToStep('booking-step-customer');
            window.setTimeout(() => {
                document.getElementById('name')?.focus();
            }, 300);
        }
    };

    const ctaLabel = !selectedService
        ? 'Escolha o serviço'
        : !slot
          ? 'Escolher horário →'
          : 'Finalizar agendamento →';

    if (submitted) {
        return (
            <PublicShell
                appearance={appearance}
                logoUrl={unit.logo_image_url ?? unit.settings?.logo_image_url}
            >
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
                    {finalWhatsappUrl ? (
                        <Button
                            asChild
                            className="mt-6 w-full bg-[#25D366] py-6 text-base font-bold text-black shadow-md hover:bg-[#20bd5a]"
                        >
                            <a
                                href={finalWhatsappUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="flex items-center justify-center gap-2"
                            >
                                <MessageCircle className="size-5 fill-black/20" />
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
        <PublicShell
            appearance={appearance}
            logoUrl={unit.logo_image_url ?? unit.settings?.logo_image_url}
        >
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
                            unit.seo?.description ||
                            unit.description ||
                            undefined,
                        url: unit.canonical_url || undefined,
                        image: unit.cover_image_url || undefined,
                        telephone:
                            unit.contacts?.phone ||
                            unit.contacts?.whatsapp ||
                            undefined,
                        address: unit.address
                            ? {
                                  '@type': 'PostalAddress',
                                  streetAddress: [
                                      unit.address.street,
                                      unit.address.number,
                                  ]
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
                        borderTopColor: appearance.primary_color,
                        borderTopWidth: '4px',
                    }}
                >
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400">
                                Atendimento com hora marcada
                            </p>
                            <h1 className="mt-2 max-w-3xl font-display text-3xl leading-tight font-semibold tracking-[-0.04em] sm:text-4xl">
                                {appearance.headline}
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
                    {(unit.description || appearance.subheadline) && (
                        <p className="mt-4 max-w-2xl text-sm leading-7 text-slate-600 dark:text-slate-300">
                            {unit.description || appearance.subheadline}
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
                            ...(sectionEnabled('services')
                                ? [['services', 'Serviços'] as [Tab, string]]
                                : []),
                            ...(sectionEnabled('professionals')
                                ? [
                                      ['professionals', 'Profissionais'] as [
                                          Tab,
                                          string,
                                      ],
                                  ]
                                : []),
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
                            className={`rounded-xl px-4 py-2.5 text-sm font-medium whitespace-nowrap transition ${tab === key ? 'bg-foreground text-background' : 'text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white'}`}
                        >
                            {label}
                        </button>
                    ))}
                </nav>
                {bookingError ? (
                    <div
                        role="alert"
                        className="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
                    >
                        <p>{bookingError}</p>
                        <Button
                            type="button"
                            variant="outline"
                            className="shrink-0 border-amber-300 dark:border-amber-800"
                            onClick={recoverBookingError}
                        >
                            {bookingErrorKind === 'catalog'
                                ? 'Escolher outro serviço'
                                : 'Escolher outro horário'}
                        </Button>
                    </div>
                ) : null}
                {tab === 'details' && (
                    <section
                        id="public-booking-panel-details"
                        className="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]"
                    >
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
                                                appearance.primary_color,
                                        }}
                                    >
                                        <Globe2
                                            aria-hidden="true"
                                            className="size-12 opacity-70"
                                        />
                                    </div>
                                )}
                            </div>
                            {sectionEnabled('gallery') &&
                            unit.gallery?.length ? (
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
                            {sectionEnabled('contact') && (
                                <InfoCard title="Contato">
                                    <div className="space-y-3 text-sm text-slate-600 dark:text-slate-300">
                                        {unit.contacts?.phone && (
                                            <p>
                                                Telefone: {unit.contacts.phone}
                                            </p>
                                        )}
                                        {unit.contacts?.whatsapp && (
                                            <p className="flex items-center gap-2">
                                                <MessageCircle className="size-4" />
                                                WhatsApp:{' '}
                                                {unit.contacts.whatsapp}
                                            </p>
                                        )}
                                        {unit.contacts?.instagram_url && (
                                            <a
                                                href={
                                                    unit.contacts.instagram_url
                                                }
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
                                                <p>
                                                    Contato ainda não informado.
                                                </p>
                                            )}
                                    </div>
                                </InfoCard>
                            )}
                        </div>
                        <div className="space-y-5">
                            {sectionEnabled('hours') && (
                                <InfoCard title="Horário de atendimento">
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
                                                    Number(first) -
                                                    Number(second),
                                            )
                                            .map(([day, hours]) => {
                                                const start =
                                                    hours.starts_at ??
                                                    hours.start;
                                                const end =
                                                    hours.ends_at ?? hours.end;

                                                return hours.enabled !==
                                                    false &&
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
                                </InfoCard>
                            )}
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
                    <InfoCard
                        id="public-booking-panel-reviews"
                        title="Avaliações"
                    >
                        <div className="flex items-center gap-3">
                            <Star className="size-5 fill-amber-400 text-amber-400" />
                            <span className="text-sm text-slate-600 dark:text-slate-300">
                                As avaliações dos clientes aparecerão aqui.
                            </span>
                        </div>
                    </InfoCard>
                )}
                {tab === 'professionals' && sectionEnabled('professionals') && (
                    <InfoCard
                        id="public-booking-panel-professionals"
                        title="Nossa equipe"
                    >
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
                        id="public-booking-panel-services"
                        onSubmit={submit}
                        className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.75fr)]"
                    >
                        <div className="flex flex-col gap-5">
                            <InfoCard
                                id="booking-step-service"
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
                            <InfoCard
                                id="booking-step-datetime"
                                title="Data e horário"
                            >
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
                            <InfoCard
                                id="booking-step-customer"
                                title="Seus dados"
                            >
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
                                    <div className="space-y-2">
                                        <Label htmlFor="email">
                                            E-mail{' '}
                                            <span className="font-normal text-muted-foreground">
                                                (opcional)
                                            </span>
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={
                                                appointmentRequest.data.email ??
                                                ''
                                            }
                                            onChange={(event) =>
                                                appointmentRequest.setData(
                                                    'email',
                                                    event.target.value,
                                                )
                                            }
                                            disabled={!slot}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="notes">
                                            Observação{' '}
                                            <span className="font-normal text-muted-foreground">
                                                (opcional)
                                            </span>
                                        </Label>
                                        <Textarea
                                            id="notes"
                                            maxLength={500}
                                            value={
                                                appointmentRequest.data.notes ??
                                                ''
                                            }
                                            onChange={(event) =>
                                                appointmentRequest.setData(
                                                    'notes',
                                                    event.target.value,
                                                )
                                            }
                                            disabled={!slot}
                                            placeholder="Alguma informação para o atendimento?"
                                        />
                                    </div>
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
                {/* Bottom Bar Mobile Persistente e Inteligente */}
                <div className="fixed right-0 bottom-0 left-0 z-40 flex items-center justify-between border-t border-border bg-background/95 p-3.5 shadow-2xl backdrop-blur-md sm:hidden">
                    <div className="flex min-w-0 flex-1 flex-col justify-center pr-3">
                        <span className="truncate text-xs font-bold text-foreground">
                            {selectedService?.name || 'Selecione um serviço'}
                        </span>
                        <span className="truncate text-2xs text-muted-foreground">
                            {selectedService ? (
                                <>
                                    <span>
                                        {selectedService.duration_minutes} min
                                    </span>
                                    <span className="mx-1">·</span>
                                    <span className="font-semibold text-foreground">
                                        {money(selectedService.price_cents)}
                                    </span>
                                    {slot ? (
                                        <>
                                            <span className="mx-1">·</span>
                                            <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                                {formatDateTimeSlot(
                                                    slot,
                                                    unit.timezone,
                                                )}
                                            </span>
                                        </>
                                    ) : null}
                                </>
                            ) : (
                                'Escolha o atendimento'
                            )}
                        </span>
                    </div>
                    <Button
                        type="button"
                        onClick={handleBottomBarAction}
                        className="shrink-0 rounded-xl px-4 py-2 text-xs font-bold text-white shadow-md transition hover:brightness-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                        style={{
                            backgroundColor: unit.brand_color ?? '#111827',
                        }}
                    >
                        {appearance.cta_label || ctaLabel}
                    </Button>
                </div>
            </div>
        </PublicShell>
    );
}

type AtelierBarberViewProps = {
    unit: Unit;
    appearance: ResolvedBookingAppearance;
    logoUrl?: string | null;
    services: Service[];
    professionals: Professional[];
    selectedService: Service | null;
    selectedServiceIds: string[];
    serviceCategory: string;
    selectedProfessional?: Professional;
    serviceId: string;
    professionalId: string;
    date: string;
    slot: string;
    query: string;
    slots: { starts_at: string; ends_at: string }[];
    availabilityTimezone: string;
    customerName: string;
    customerPhone: string;
    customerEmail: string;
    customerNotes: string;
    processing: boolean;
    submitted: boolean;
    bookingError: string | null;
    bookingErrorKind: 'catalog' | 'slot' | 'generic' | null;
    finalWhatsappUrl: string | null;
    onQueryChange: (value: string) => void;
    onCategoryChange: (value: string) => void;
    onServiceChange: (id: string) => void;
    onProfessionalChange: (id: string) => void;
    onDateChange: (value: string) => void;
    onSlotChange: (value: string) => void;
    onCustomerChange: (
        field: 'name' | 'phone' | 'email' | 'notes',
        value: string,
    ) => void;
    onRecoverBookingError: () => void;
    onSubmit: (event: React.FormEvent<HTMLFormElement>) => Promise<void>;
};

function AtelierBarberView({
    unit,
    appearance,
    logoUrl,
    services,
    professionals,
    selectedService,
    selectedServiceIds,
    serviceCategory,
    selectedProfessional,
    serviceId,
    professionalId,
    date,
    slot,
    query,
    slots,
    availabilityTimezone,
    customerName,
    customerPhone,
    customerEmail,
    customerNotes,
    processing,
    submitted,
    bookingError,
    bookingErrorKind,
    finalWhatsappUrl,
    onQueryChange,
    onCategoryChange,
    onServiceChange,
    onProfessionalChange,
    onDateChange,
    onSlotChange,
    onCustomerChange,
    onRecoverBookingError,
    onSubmit,
}: AtelierBarberViewProps) {
    const [activeStep, setActiveStep] = useState(submitted ? 4 : 1);
    const serviceProfessionals = selectedService?.professionals.length
        ? selectedService.professionals
        : professionals;
    const filteredServices = services.filter((service) => {
        const category = (service.category_name ?? service.name).toLowerCase();
        const categoryMatches =
            serviceCategory === 'Todos' ||
            category.includes(serviceCategory.toLowerCase());

        return (
            categoryMatches &&
            service.name.toLowerCase().includes(query.toLowerCase())
        );
    });
    const selectedServices = services.filter((service) =>
        selectedServiceIds.includes(service.id),
    );
    const selectedTotalCents = selectedServices.reduce(
        (total, service) => total + service.price_cents,
        0,
    );
    const selectedTotalMinutes = selectedServices.reduce(
        (total, service) => total + service.duration_minutes,
        0,
    );
    const stepLabels = ['Serviços', 'Profissional', 'Horário', 'Confirmar'];

    const goBack = (): void => {
        if (activeStep === 2) {
            onServiceChange('');
            setActiveStep(1);
        } else if (activeStep === 3) {
            onProfessionalChange('');
            setActiveStep(2);
        } else if (activeStep === 4) {
            onSlotChange('');
            setActiveStep(3);
        }
    };

    const advanceStep = (): void => {
        if (activeStep === 1 && serviceId) {
            setActiveStep(2);
        } else if (activeStep === 2 && professionalId) {
            setActiveStep(3);
        } else if (activeStep === 3 && slot) {
            setActiveStep(4);
        }
    };

    const selectedSummary = selectedService ? (
        <div className="flex items-center justify-between gap-3 border-t border-[#373229] pt-4 font-['DM_Sans'] text-xs text-[#a9a39a]">
            <span className="min-w-0 truncate">
                {selectedService.name} · {selectedService.duration_minutes} min
            </span>
            <span className="shrink-0 font-['Space_Grotesk'] font-semibold text-[#d4af37]">
                {money(selectedService.price_cents)}
            </span>
        </div>
    ) : null;

    const stepCta =
        activeStep === 1
            ? 'Escolher profissional'
            : activeStep === 2
              ? 'Escolher horário'
              : activeStep === 3
                ? 'Continuar'
                : appearance.cta_label;

    return (
        <main
            className="mx-auto min-h-dvh w-full max-w-[390px] bg-[#131313] px-0 pb-36 text-[#f0e0d0] selection:bg-[#d4af37]/30 md:max-w-6xl md:bg-[#0e0e0e] md:pb-32"
            style={appearanceStyle(appearance)}
        >
            <Head title={`Agendar · ${unit.name}`}>
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link
                    rel="preconnect"
                    href="https://fonts.gstatic.com"
                    crossOrigin="anonymous"
                />
                <link
                    rel="stylesheet"
                    href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400..900;1,6..96,400..900&family=Manrope:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
                />
            </Head>
            <div className="w-full">
                <header className="fixed top-0 right-0 left-0 z-40 mx-auto flex h-16 w-full max-w-[390px] items-center justify-between border-b border-[#4d4635]/20 bg-[#0e0e0e]/95 px-4 shadow-[0_12px_36px_rgba(0,0,0,0.65)] backdrop-blur-xl md:max-w-6xl md:px-10">
                    <div className="flex items-center justify-between gap-4">
                        {activeStep > 1 && !submitted ? (
                            <button
                                type="button"
                                onClick={goBack}
                                className="flex size-9 items-center justify-center rounded-full border border-[#4d4635]/30 bg-[#1f2020] text-[#d4af37] transition hover:border-[#d4af37] hover:text-[#d4af37]"
                                aria-label="Voltar"
                            >
                                <ArrowLeft className="size-4" />
                            </button>
                        ) : (
                            <span className="size-9" />
                        )}
                        <div className="text-center">
                            {logoUrl ? (
                                <img
                                    src={logoUrl}
                                    alt={appearance.brand_name}
                                    className="mx-auto max-h-10 max-w-36 object-contain"
                                />
                            ) : (
                                <p className="font-['Bodoni_Moda'] text-[18px] font-semibold tracking-[0.2em] text-[#ffe9b0] uppercase">
                                    {appearance.brand_name}
                                </p>
                            )}
                            <p className="mt-0.5 font-['Manrope'] text-[9px] tracking-[0.18em] text-[#e9c176] uppercase">
                                agendamento online
                            </p>
                        </div>
                        <span className="flex size-9 items-center justify-center rounded border border-[#d4af37]/30 bg-[#d4af37]/10 font-['Manrope'] text-[10px] font-semibold text-[#ffe9b0]">
                            {activeStep}/4
                        </span>
                    </div>
                    <div className="hidden" />
                </header>
                <div className="px-4 pt-20 md:px-10 md:pt-24">
                    <AtelierProgress
                        activeStep={activeStep}
                        label={stepLabels[activeStep - 1]}
                    />
                </div>
                {bookingError ? (
                    <div
                        role="alert"
                        className="mx-4 mb-4 flex flex-col gap-3 rounded-xl border border-[#8b6530] bg-[#2b2114] p-4 font-['DM_Sans'] text-xs leading-5 text-[#f3dca4]"
                    >
                        <p>{bookingError}</p>
                        <button
                            type="button"
                            onClick={onRecoverBookingError}
                            className="self-start rounded-lg border border-[#d4af37]/60 px-3 py-2 font-['Space_Grotesk'] text-[10px] font-semibold tracking-[0.08em] text-[#ffe9b0] uppercase transition hover:bg-[#d4af37]/10"
                        >
                            {bookingErrorKind === 'catalog'
                                ? 'Escolher outro serviço'
                                : 'Escolher outro horário'}
                        </button>
                    </div>
                ) : null}
                {submitted ? (
                    <section className="rounded-xl border border-[#d4af37]/30 bg-[#1a181c] p-7 text-center shadow-[0_24px_80px_rgba(0,0,0,0.3)] sm:p-10">
                        <div className="mx-auto flex size-16 items-center justify-center rounded-full border border-[#d4af37]/50 bg-[#d4af37]/10">
                            <CheckCircle2 className="size-8 text-[#d4af37]" />
                        </div>
                        <p className="mt-6 font-['Manrope'] text-[10px] font-semibold tracking-[0.24em] text-[#d4af37] uppercase">
                            Agendamento confirmado
                        </p>
                        <h2 className="mt-3 font-['Bodoni_Moda'] text-4xl text-[#f8f2e8]">
                            Até breve.
                        </h2>
                        <p className="mx-auto mt-3 max-w-sm font-['DM_Sans'] text-sm leading-6 text-[#a9a39a]">
                            {selectedService?.name}{' '}
                            {selectedProfessional
                                ? `com ${selectedProfessional.name}`
                                : ''}
                            <br />
                            {slot
                                ? formatDateTimeSlot(slot, unit.timezone)
                                : ''}
                            .
                        </p>
                        {finalWhatsappUrl ? (
                            <a
                                href={finalWhatsappUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-7 flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[#d4af37] px-5 font-['Space_Grotesk'] text-sm font-bold text-[#17140d] transition hover:bg-[#edca55] focus-visible:ring-2 focus-visible:ring-[#d4af37] focus-visible:ring-offset-2 focus-visible:ring-offset-[#171612]"
                            >
                                <MessageCircle className="size-4" /> Falar pelo
                                WhatsApp
                            </a>
                        ) : null}
                    </section>
                ) : (
                    <form onSubmit={onSubmit} className="mx-auto space-y-5 px-4 md:max-w-5xl md:px-0">
                        {activeStep === 1 ? (
                            <section className="overflow-hidden bg-[#131313]">
                                {unit.cover_image_url || unit.cover_url ? (
                                    <div className="relative h-32 overflow-hidden">
                                        <img
                                            src={
                                                unit.cover_image_url ??
                                                unit.cover_url ??
                                                undefined
                                            }
                                            alt=""
                                            className="size-full object-cover opacity-55"
                                        />
                                        <div className="absolute inset-0 bg-gradient-to-r from-[#0e0e0e]/95 via-[#0e0e0e]/65 to-transparent" />
                                    </div>
                                ) : null}
                                <div className="pt-5">
                                    <p className="font-['Manrope'] text-[10px] font-bold tracking-[0.18em] text-[#e9c176] uppercase">
                                        {appearance.brand_name}
                                    </p>
                                    <h1 className="mt-2 font-['Bodoni_Moda'] text-[28px] leading-[1.15] tracking-[0.04em] text-[#fdfbf7]">
                                        {appearance.headline ||
                                            'O que você quer fazer hoje?'}
                                    </h1>
                                    <p className="mt-2 font-['Manrope'] text-sm leading-6 text-[#d0c5af]">
                                        {appearance.subheadline ||
                                            'Escolha um ou mais serviços para o seu atendimento.'}
                                    </p>
                                    <div className="mt-5 flex gap-2 overflow-x-auto pb-1">
                                        {[
                                            'Todos',
                                            'Cabelo',
                                            'Barba',
                                            'Cuidados',
                                        ].map((category) => (
                                            <button
                                                type="button"
                                                key={category}
                                                onClick={() => {
                                                    onCategoryChange(category);
                                                    if (category === 'Todos') {
                                                        onQueryChange('');
                                                    }
                                                }}
                                                className={`shrink-0 rounded-full px-4 py-2 font-['Manrope'] text-xs font-semibold transition ${serviceCategory === category ? 'bg-[#ffe9b0] text-[#261900]' : 'bg-[#1f2020] text-[#d0c5af] hover:bg-[#353535]'}`}
                                            >
                                                {category}
                                            </button>
                                        ))}
                                    </div>
                                    <div className="mt-7">
                                        <AtelierSectionHeading eyebrow="Mais escolhidos">
                                            Seleção múltipla
                                        </AtelierSectionHeading>
                                    </div>
                                    <div className="mt-3 grid gap-3 md:grid-cols-2">
                                        {filteredServices.map((service) => (
                                            <button
                                                type="button"
                                                key={service.id}
                                                onClick={() =>
                                                    onServiceChange(service.id)
                                                }
                                                aria-pressed={
                                                    selectedServiceIds.includes(
                                                        service.id,
                                                    )
                                                }
                                                className={`flex min-h-[122px] items-center gap-3 rounded-xl border p-4 text-left shadow-[0_4px_20px_rgba(5,4,3,0.55)] transition ${selectedServiceIds.includes(service.id) ? 'border-[#d4af37]/40 bg-[#353535]' : 'border-transparent bg-[#1f1f1f] hover:border-[#8b7635]'}`}
                                            >
                                                {service.thumbnail_url ||
                                                service.image_url ? (
                                                    <img
                                                        src={
                                                            service.thumbnail_url ||
                                                            service.image_url ||
                                                            undefined
                                                        }
                                                        alt=""
                                                        className="size-14 shrink-0 rounded-lg object-cover"
                                                    />
                                                ) : (
                                                    <span className="flex size-12 shrink-0 items-center justify-center rounded-lg border border-[#4a4332]">
                                                        <Star className="size-4 text-[#d4af37]" />
                                                    </span>
                                                )}
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate font-['Bodoni_Moda'] text-lg font-semibold text-[#fdfbf7]">
                                                        {service.name}
                                                    </span>
                                                    <span className="mt-1 flex items-center gap-2 font-['Manrope'] text-xs text-[#d0c5af]">
                                                        <Clock3 className="size-3" />
                                                        {
                                                            service.duration_minutes
                                                        }{' '}
                                                        min
                                                    </span>
                                                </span>
                                                <span className="font-['Plus_Jakarta_Sans'] text-base font-extrabold text-[#ffe9b0]">
                                                    {money(service.price_cents)}
                                                </span>
                                                <span
                                                    className={`flex size-8 shrink-0 items-center justify-center rounded-full ${selectedServiceIds.includes(service.id) ? 'bg-[#ffe9b0] text-[#261900] shadow-[0_0_8px_rgba(242,202,80,0.5)]' : 'bg-[#353535] text-[#d0c5af]'}`}
                                                >
                                                    {selectedServiceIds.includes(
                                                        service.id,
                                                    ) ? (
                                                        <CheckCircle2 className="size-5" />
                                                    ) : (
                                                        <span className="text-xl leading-none">
                                                            +
                                                        </span>
                                                    )}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </section>
                        ) : null}

                        {activeStep === 2 ? (
                            <section className="bg-[#131313] p-0">
                                <p className="font-['Space_Grotesk'] text-[9px] font-semibold tracking-[0.2em] text-[#d4af37] uppercase">
                                    Seu serviço
                                </p>
                                <h1 className="mt-2 font-['Bodoni_Moda'] text-[28px] leading-[1.15] text-[#f8f2e8]">
                                    Com quem você{' '}
                                    <em className="text-[#d4af37]">prefere?</em>
                                </h1>
                                <p className="mt-3 font-['DM_Sans'] text-sm leading-6 text-[#a9a39a]">
                                    Escolha o profissional que cuidará do seu
                                    atendimento.
                                </p>
                                <div className="mt-6 grid gap-3 md:grid-cols-2">
                                    {serviceProfessionals.map((person) => (
                                        <button
                                            type="button"
                                            key={person.id}
                                            disabled={!serviceId}
                                            onClick={() =>
                                                onProfessionalChange(person.id)
                                            }
                                            aria-pressed={
                                                professionalId === person.id
                                            }
                                            className={`flex items-center gap-3 rounded-xl border p-3 text-left transition ${professionalId === person.id ? 'border-[#d4af37] bg-[#282317]' : 'border-[#39362f] bg-[#1d1b17] hover:border-[#8b7635]'}`}
                                        >
                                            <span className="flex size-10 items-center justify-center overflow-hidden rounded-full bg-[#302c22] font-['DM_Sans'] text-xs font-semibold text-[#d4af37]">
                                                {person.avatar_url ? (
                                                    <img
                                                        src={person.avatar_url}
                                                        alt=""
                                                        className="size-full object-cover"
                                                    />
                                                ) : (
                                                    <UserRound className="size-4" />
                                                )}
                                            </span>
                                            <span className="min-w-0 flex-1 font-['DM_Sans'] text-sm font-medium text-[#eee7dc]">
                                                {person.name}
                                                <span className="mt-1 block font-['Space_Grotesk'] text-[10px] tracking-wide text-[#8f887b]">
                                                    Atendimento presencial
                                                </span>
                                            </span>
                                            {professionalId === person.id ? (
                                                <CheckCircle2 className="size-4 shrink-0 text-[#d4af37]" />
                                            ) : null}
                                        </button>
                                    ))}
                                </div>
                                {selectedSummary}
                            </section>
                        ) : null}

                        {activeStep === 3 ? (
                            <section className="bg-[#131313] p-0">
                                <p className="font-['Space_Grotesk'] text-[9px] font-semibold tracking-[0.2em] text-[#d4af37] uppercase">
                                    Escolha do horário
                                </p>
                                <h1 className="mt-2 font-['Bodoni_Moda'] text-[28px] leading-[1.15] text-[#f8f2e8]">
                                    Encontre o melhor{' '}
                                    <em className="text-[#d4af37]">momento.</em>
                                </h1>
                                <p className="mt-3 font-['DM_Sans'] text-sm leading-6 text-[#a9a39a]">
                                    Reserve um horário disponível para você.
                                </p>
                                <div className="mt-6 grid gap-4">
                                    <label className="flex h-12 items-center gap-2 rounded-xl border border-[#39362f] bg-[#0f0f0d] px-3 focus-within:border-[#d4af37]">
                                        <CalendarDays className="size-4 text-[#d4af37]" />
                                        <span className="sr-only">Data</span>
                                        <input
                                            type="date"
                                            value={date}
                                            min={today()}
                                            max={limit()}
                                            disabled={!professionalId}
                                            onChange={(event) =>
                                                onDateChange(event.target.value)
                                            }
                                            className="w-full bg-transparent font-['DM_Sans'] text-sm text-[#f4efe6] [color-scheme:dark] outline-none"
                                        />
                                    </label>
                                    <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                        {slots.map((item) => (
                                            <button
                                                type="button"
                                                key={item.starts_at}
                                                onClick={() =>
                                                    onSlotChange(item.starts_at)
                                                }
                                                className={`rounded-lg border px-2 py-2.5 font-['Space_Grotesk'] text-xs font-semibold transition ${slot === item.starts_at ? 'border-[#d4af37] bg-[#d4af37] text-[#17140d]' : 'border-[#39362f] bg-[#1d1b17] text-[#d6cfc2] hover:border-[#8b7635]'}`}
                                            >
                                                {time(
                                                    item.starts_at,
                                                    availabilityTimezone,
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                                {professionalId && date && !slots.length ? (
                                    <p className="mt-4 font-['DM_Sans'] text-xs text-[#918b80]">
                                        Nenhum horário disponível para esta
                                        data.
                                    </p>
                                ) : null}
                                {selectedSummary}
                            </section>
                        ) : null}

                        {activeStep === 4 ? (
                            <section className="bg-[#131313] p-0">
                                <p className="font-['Space_Grotesk'] text-[9px] font-semibold tracking-[0.2em] text-[#d4af37] uppercase">
                                    Revise os detalhes
                                </p>
                                <h1 className="mt-2 font-['Bodoni_Moda'] text-[28px] leading-[1.15] text-[#f8f2e8]">
                                    Confirme seu{' '}
                                    <em className="text-[#d4af37]">
                                        agendamento.
                                    </em>
                                </h1>
                                <div className="mt-6 rounded-xl border border-[#39362f] bg-[#0f0f0d] p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-['Space_Grotesk'] text-[9px] tracking-[0.16em] text-[#8f887b] uppercase">
                                                Serviço
                                            </p>
                                            <p className="mt-1 font-['DM_Sans'] text-sm font-semibold text-[#f4efe6]">
                                                {selectedService?.name}
                                            </p>
                                        </div>
                                        <p className="font-['Space_Grotesk'] text-sm font-semibold text-[#d4af37]">
                                            {selectedService
                                                ? money(
                                                      selectedService.price_cents,
                                                  )
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="mt-4 grid grid-cols-2 gap-3 border-t border-[#373229] pt-4">
                                        <div>
                                            <p className="font-['Space_Grotesk'] text-[9px] tracking-[0.16em] text-[#8f887b] uppercase">
                                                Profissional
                                            </p>
                                            <p className="mt-1 truncate font-['DM_Sans'] text-xs text-[#d6cfc2]">
                                                {selectedProfessional?.name}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="font-['Space_Grotesk'] text-[9px] tracking-[0.16em] text-[#8f887b] uppercase">
                                                Data e hora
                                            </p>
                                            <p className="mt-1 font-['DM_Sans'] text-xs text-[#d6cfc2]">
                                                {slot
                                                    ? formatDateTimeSlot(
                                                          slot,
                                                          unit.timezone,
                                                      )
                                                    : ''}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div className="mt-5 grid gap-3">
                                    <label
                                        className="sr-only"
                                        htmlFor="atelier-name"
                                    >
                                        Nome
                                    </label>
                                    <input
                                        id="atelier-name"
                                        value={customerName}
                                        onChange={(event) =>
                                            onCustomerChange(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        disabled={!slot}
                                        required
                                        placeholder="Seu nome"
                                        className="h-12 rounded-xl border border-[#39362f] bg-[#0f0f0d] px-4 font-['DM_Sans'] text-sm text-[#f4efe6] outline-none placeholder:text-[#6f6a61] focus:border-[#d4af37]"
                                    />
                                    <label
                                        className="sr-only"
                                        htmlFor="atelier-email"
                                    >
                                        E-mail (opcional)
                                    </label>
                                    <input
                                        id="atelier-email"
                                        type="email"
                                        value={customerEmail}
                                        onChange={(event) =>
                                            onCustomerChange(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                        disabled={!slot}
                                        placeholder="E-mail (opcional)"
                                        className="h-12 rounded-xl border border-[#39362f] bg-[#0f0f0d] px-4 font-['DM_Sans'] text-sm text-[#f4efe6] outline-none placeholder:text-[#6f6a61] focus:border-[#d4af37]"
                                    />
                                    <label
                                        className="sr-only"
                                        htmlFor="atelier-notes"
                                    >
                                        Observação (opcional)
                                    </label>
                                    <textarea
                                        id="atelier-notes"
                                        value={customerNotes}
                                        maxLength={500}
                                        onChange={(event) =>
                                            onCustomerChange(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                        disabled={!slot}
                                        placeholder="Observação (opcional)"
                                        rows={3}
                                        className="rounded-xl border border-[#39362f] bg-[#0f0f0d] px-4 py-3 font-['DM_Sans'] text-sm text-[#f4efe6] outline-none placeholder:text-[#6f6a61] focus:border-[#d4af37]"
                                    />
                                    <label
                                        className="sr-only"
                                        htmlFor="atelier-phone"
                                    >
                                        Telefone
                                    </label>
                                    <input
                                        id="atelier-phone"
                                        value={customerPhone}
                                        onChange={(event) =>
                                            onCustomerChange(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                        disabled={!slot}
                                        required
                                        placeholder="WhatsApp / telefone"
                                        className="h-12 rounded-xl border border-[#39362f] bg-[#0f0f0d] px-4 font-['DM_Sans'] text-sm text-[#f4efe6] outline-none placeholder:text-[#6f6a61] focus:border-[#d4af37]"
                                    />
                                </div>
                            </section>
                        ) : null}
                        {activeStep === 1 ? (
                            <div className="fixed right-0 bottom-[76px] left-0 z-40 mx-auto flex w-full max-w-[390px] items-center justify-between border-t border-[#4d4635]/30 bg-[#171612] px-4 py-3 md:max-w-5xl md:px-10">
                                <div className="min-w-0">
                                    <p className="truncate font-['Manrope'] text-[10px] font-bold tracking-[0.14em] text-[#d4af37] uppercase">
                                        {selectedServices.length
                                            ? `${selectedServices.length} serviço${selectedServices.length > 1 ? 's' : ''} selecionado${selectedServices.length > 1 ? 's' : ''}`
                                            : 'Nenhum serviço'}
                                    </p>
                                    <p className="font-['Plus_Jakarta_Sans'] text-lg font-extrabold text-[#ffe9b0]">
                                        {money(selectedTotalCents)}{' '}
                                        <span className="font-['Manrope'] text-xs font-medium text-[#a9a39a]">
                                            · {selectedTotalMinutes} min
                                        </span>
                                    </p>
                                </div>
                            </div>
                        ) : null}
                        <button
                            type="submit"
                            onClick={(event) => {
                                if (activeStep < 4) {
                                    event.preventDefault();
                                    advanceStep();
                                }
                            }}
                            disabled={
                                activeStep === 1
                                    ? !serviceId
                                    : activeStep === 2
                                      ? !professionalId
                                      : activeStep === 3
                                        ? !slot
                                        : !customerName ||
                                          !customerPhone ||
                                          processing
                            }
                            className="fixed right-0 bottom-0 left-0 z-40 mx-auto flex min-h-[76px] w-full max-w-[390px] items-center justify-center gap-2 border-t border-[#4d4635]/30 bg-[#ffe9b0] px-6 font-['Manrope'] text-sm font-bold tracking-wide text-[#261900] shadow-[0_-12px_36px_rgba(0,0,0,0.65)] transition hover:brightness-110 focus-visible:ring-2 focus-visible:ring-[#d4af37] disabled:cursor-not-allowed disabled:opacity-40 md:max-w-5xl md:rounded-t-2xl md:border-x md:px-10"
                            style={{
                                backgroundColor: 'var(--booking-primary)',
                            }}
                        >
                            {processing ? 'Confirmando…' : stepCta}
                            <ChevronRight className="size-4" />
                        </button>
                    </form>
                )}
                <footer className="mt-10 text-center font-['DM_Sans'] text-[11px] text-[#6f6a61]">
                    Seus dados são usados somente para organizar este
                    atendimento.
                </footer>
            </div>
        </main>
    );
}

function InfoCard({
    id,
    className,
    title,
    children,
}: {
    id?: string;
    className?: string;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section
            id={id}
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
    appearance,
    logoUrl,
    children,
}: {
    appearance: ResolvedBookingAppearance;
    logoUrl?: string | null;
    children: React.ReactNode;
}) {
    return (
        <main
            className="min-h-dvh bg-[var(--booking-background)] px-4 pt-5 pb-24 text-slate-950 sm:px-6 sm:py-8 dark:text-white"
            style={appearanceStyle(appearance)}
        >
            <div className="mx-auto mb-6 flex max-w-6xl items-center justify-between">
                {logoUrl ? (
                    <img
                        src={logoUrl}
                        alt={appearance.brand_name}
                        className="max-h-10 max-w-40 object-contain"
                    />
                ) : (
                    <span className="font-display text-lg font-semibold tracking-tight">
                        {appearance.brand_name}
                    </span>
                )}
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
