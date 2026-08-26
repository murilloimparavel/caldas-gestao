import { Head, useHttp } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    slug: string;
    name: string;
    timezone: string;
    address: Address | null;
};

type Professional = { id: string; name: string };

type Service = {
    id: string;
    name: string;
    description: string | null;
    duration_minutes: number;
    image_url?: string | null;
    photo_url?: string | null;
    price_cents: number;
    professionals: Professional[];
};

type Props = { unit: Unit; services: Service[]; professionals: Professional[] };

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
};

const formatPrice = (cents: number): string =>
    new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(cents / 100);

const formatTime = (iso: string, timezone: string): string =>
    new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: timezone,
    }).format(new Date(iso));

const today = (): string => new Date().toISOString().slice(0, 10);

const dateLimit = (): string => {
    const date = new Date();
    date.setDate(date.getDate() + 31);

    return date.toISOString().slice(0, 10);
};

const publicBookingRouteArgs = (unitSlug: string): [string, string] => {
    if (typeof window === 'undefined') {
return [unitSlug, unitSlug];
}

    const parts = window.location.pathname.split('/').filter(Boolean);
    const bookingIndex = parts.indexOf('book');

    return bookingIndex >= 0 && parts[bookingIndex + 2]
        ? [parts[bookingIndex + 1], parts[bookingIndex + 2]]
        : [unitSlug, unitSlug];
};

const addressLabel = (address: Address | null): string | null => {
    if (!address) {
return null;
}

    return [
        [address.street, address.number].filter(Boolean).join(', '),
        [address.neighborhood, address.city].filter(Boolean).join(' · '),
        [address.state, address.postal_code].filter(Boolean).join(' · '),
    ]
        .filter(Boolean)
        .join(' — ');
};

export default function PublicBooking({ unit, services }: Props) {
    const [serviceId, setServiceId] = useState('');
    const [professionalId, setProfessionalId] = useState('');
    const [date, setDate] = useState('');
    const [selectedSlot, setSelectedSlot] = useState('');
    const [submitted, setSubmitted] = useState(false);

    const selectedService = useMemo(
        () => services.find((service) => service.id === serviceId) ?? null,
        [serviceId, services],
    );
    const selectedProfessional = selectedService?.professionals.find(
        (professional) => professional.id === professionalId,
    );
    const address = addressLabel(unit.address);
    const routeArgs = publicBookingRouteArgs(unit.slug);

    const availabilityRequest = useHttp<AvailabilityQuery, AvailabilityResponse>({
        service_id: '',
        professional_id: '',
        date: '',
    });
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
            availability.url(routeArgs, {
                query: { service_id: serviceId, professional_id: professionalId, date },
            }),
            { onError: () => setSelectedSlot('') },
        );
        // The request is intentionally keyed by the public slugs; the tenant slug is
        // not included in the show payload, so Inertia keeps it in the current URL.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [date, professionalId, serviceId]);

    const slots = availabilityRequest.response?.slots ?? [];

    const handleServiceChange = (value: string): void => {
        setServiceId(value);
        setProfessionalId('');
        setDate('');
        setSelectedSlot('');
        setSubmitted(false);
    };

    const handleSubmit = async (event: React.FormEvent<HTMLFormElement>): Promise<void> => {
        event.preventDefault();

        if (!selectedSlot) {
return;
}

        appointmentRequest.setData((current) => ({
            ...current,
            service_id: serviceId,
            professional_id: professionalId,
            starts_at: selectedSlot,
        }));

        await appointmentRequest.post(
            store.url(routeArgs),
            {
                headers: {
                    'X-Idempotency-Key':
                        typeof crypto.randomUUID === 'function'
                            ? crypto.randomUUID()
                            : `${Date.now()}-${Math.random()}`,
                },
                onSuccess: () => setSubmitted(true),
            },
        );
    };

    if (submitted) {
        return (
            <PublicShell unit={unit}>
                <Head title={`Agendamento confirmado · ${unit.name}`} />
                <section className="mx-auto max-w-xl rounded-3xl border border-emerald-200 bg-white p-8 text-center shadow-sm dark:border-emerald-900 dark:bg-slate-900">
                    <p className="text-sm font-semibold tracking-[0.16em] text-emerald-700 uppercase dark:text-emerald-300">
                        Tudo certo
                    </p>
                    <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">
                        Seu horário está reservado.
                    </h1>
                    <p className="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                        {selectedService?.name} com {selectedProfessional?.name} em{' '}
                        {new Intl.DateTimeFormat('pt-BR', { dateStyle: 'full', timeZone: unit.timezone }).format(new Date(selectedSlot))} às{' '}
                        {formatTime(selectedSlot, unit.timezone)}.
                    </p>
                </section>
            </PublicShell>
        );
    }

    return (
        <PublicShell unit={unit}>
            <Head title={`Agendar · ${unit.name}`} />
            <div className="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[minmax(0,0.82fr)_minmax(0,1.18fr)]">
                <header className="flex flex-col justify-between gap-8 rounded-3xl bg-slate-950 p-7 text-white shadow-xl shadow-slate-950/10 sm:p-10 dark:bg-slate-800">
                    <div className="space-y-5">
                        <p className="text-sm font-semibold tracking-[0.2em] text-amber-300 uppercase">
                            Atendimento com hora marcada
                        </p>
                        <h1 className="font-display text-4xl leading-tight font-semibold tracking-[-0.04em] sm:text-5xl">
                            Escolha um momento que funcione para você.
                        </h1>
                        <p className="max-w-md text-sm leading-7 text-slate-300">
                            Selecione o serviço, encontre a disponibilidade e confirme seus dados em poucos passos.
                        </p>
                    </div>
                    <div className="space-y-2 border-t border-white/15 pt-5 text-sm text-slate-300">
                        <p className="font-medium text-white">{unit.name}</p>
                        {address && <p>{address}</p>}
                        <p>Horários exibidos no fuso {unit.timezone}.</p>
                    </div>
                </header>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <StepCard number="01" title="Escolha o serviço">
                        {services.length === 0 ? (
                            <p className="rounded-xl bg-slate-50 p-4 text-sm text-slate-600 dark:bg-slate-900 dark:text-slate-300">
                                Nenhum serviço está disponível para agendamento online no momento.
                            </p>
                        ) : (
                            <div className="grid gap-3">
                                {services.map((service) => (
                                    <button
                                        type="button"
                                        key={service.id}
                                        onClick={() => handleServiceChange(service.id)}
                                        aria-pressed={serviceId === service.id}
                                        className={`rounded-2xl border p-4 text-left transition focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2 dark:focus-visible:ring-white ${serviceId === service.id ? 'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950' : 'border-slate-200 bg-white hover:border-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-500'}`}
                                    >
                                        <span className="flex items-start justify-between gap-4">
                                            <span className="flex items-start gap-3">
                                                {service.photo_url || service.image_url ? (
                                                    <img
                                                        src={service.photo_url || service.image_url || undefined}
                                                        alt={service.name}
                                                        className="size-12 shrink-0 rounded-xl object-cover border border-slate-200 dark:border-slate-700"
                                                    />
                                                ) : null}
                                                <span>
                                                    <span className="block font-semibold">{service.name}</span>
                                                    {service.description && <span className={`mt-1 block text-sm leading-5 ${serviceId === service.id ? 'text-slate-300 dark:text-slate-600' : 'text-slate-600 dark:text-slate-300'}`}>{service.description}</span>}
                                                </span>
                                            </span>
                                            <span className="shrink-0 text-sm font-semibold">{formatPrice(service.price_cents)}</span>
                                        </span>
                                        <span className={`mt-3 block text-xs ${serviceId === service.id ? 'text-slate-300 dark:text-slate-600' : 'text-slate-500 dark:text-slate-400'}`}>
                                            {service.duration_minutes} min · {service.professionals.length} profissional(is)
                                        </span>
                                    </button>
                                ))}
                            </div>
                        )}
                    </StepCard>

                    <StepCard number="02" title="Escolha o profissional">
                        <Label htmlFor="professional">Profissional</Label>
                        <select id="professional" value={professionalId} onChange={(event) => {
 setProfessionalId(event.target.value); setDate(''); setSelectedSlot(''); 
}} disabled={!selectedService} className="mt-2 flex h-11 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50">
                            <option value="">{selectedService ? 'Selecione uma pessoa' : 'Escolha um serviço primeiro'}</option>
                            {selectedService?.professionals.map((professional) => <option key={professional.id} value={professional.id}>{professional.name}</option>)}
                        </select>
                    </StepCard>

                    <StepCard number="03" title="Encontre um horário">
                        <Label htmlFor="date">Data</Label>
                        <Input
                            id="date"
                            type="date"
                            min={today()}
                            max={dateLimit()}
                            value={date}
                            onChange={(event) => {
                                setDate(event.target.value);
                                setSelectedSlot('');
                            }}
                            disabled={!professionalId}
                            className="mt-2"
                        />
                        {availabilityRequest.processing && <p className="mt-3 text-sm text-slate-500" role="status">Buscando horários...</p>}
                        {!availabilityRequest.processing && date && professionalId && slots.length === 0 && <p className="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">Não encontramos horários para esta combinação. Tente outra data.</p>}
                        {slots.length > 0 && <fieldset className="mt-4"><legend className="text-sm font-medium">Horários disponíveis</legend><div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">{slots.map((slot) => <button type="button" key={slot.starts_at} aria-pressed={selectedSlot === slot.starts_at} onClick={() => setSelectedSlot(slot.starts_at)} className={`min-h-11 rounded-xl border px-3 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2 dark:focus-visible:ring-white ${selectedSlot === slot.starts_at ? 'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950' : 'border-slate-200 hover:border-slate-500 dark:border-slate-700'}`}>{formatTime(slot.starts_at, availabilityRequest.response?.timezone ?? unit.timezone)}</button>)}</div></fieldset>}
                    </StepCard>

                    <StepCard number="04" title="Confirme seus dados">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2"><Label htmlFor="name">Nome</Label><Input id="name" name="name" value={appointmentRequest.data.name} onChange={(event) => appointmentRequest.setData('name', event.target.value)} required autoComplete="name" disabled={!selectedSlot} /></div>
                            <div className="grid gap-2"><Label htmlFor="phone">Telefone</Label><Input id="phone" name="phone" value={appointmentRequest.data.phone} onChange={(event) => appointmentRequest.setData('phone', event.target.value)} required autoComplete="tel" disabled={!selectedSlot} /></div>
                        </div>
                        {appointmentRequest.errors.name && <p className="text-sm text-destructive">{appointmentRequest.errors.name}</p>}
                        {appointmentRequest.errors.phone && <p className="text-sm text-destructive">{appointmentRequest.errors.phone}</p>}
                        {appointmentRequest.hasErrors && !appointmentRequest.errors.name && !appointmentRequest.errors.phone && <p className="text-sm text-destructive">Não foi possível concluir. Revise os dados e tente novamente.</p>}
                        <Button type="submit" className="mt-5 w-full" disabled={!selectedSlot || !appointmentRequest.data.name || !appointmentRequest.data.phone || appointmentRequest.processing}>{appointmentRequest.processing ? 'Confirmando...' : 'Confirmar agendamento'}</Button>
                    </StepCard>
                </form>
            </div>
        </PublicShell>
    );
}

function StepCard({ number, title, children }: { number: string; title: string; children: React.ReactNode }) {
    return <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900"><div className="flex items-center gap-3"><span className="grid size-8 place-items-center rounded-full bg-amber-100 text-xs font-bold text-amber-900 dark:bg-amber-400/20 dark:text-amber-200">{number}</span><h2 className="font-display text-xl font-semibold text-slate-950 dark:text-white">{title}</h2></div><div className="mt-5">{children}</div></section>;
}

function PublicShell({ unit, children }: { unit: Unit; children: React.ReactNode }) {
    return <main className="min-h-dvh bg-[#f7f5f0] px-4 py-5 text-slate-950 sm:px-6 sm:py-8 dark:bg-slate-950 dark:text-white"><div className="mx-auto mb-6 flex max-w-6xl items-center justify-between"><span className="font-display text-lg font-semibold tracking-tight">{unit.name}</span><span className="rounded-full border border-slate-200 bg-white/70 px-3 py-1 text-xs font-medium text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">Agendamento online</span></div>{children}<footer className="mx-auto mt-10 max-w-6xl text-center text-xs text-slate-500 dark:text-slate-400">Ao confirmar, seus dados serão usados somente para organizar este atendimento.</footer></main>;
}

PublicBooking.layout = null;
