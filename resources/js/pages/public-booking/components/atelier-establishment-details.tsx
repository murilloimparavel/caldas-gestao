import { publicOpeningLabel } from '../public-opening-hours';
import {
    ArrowUpRight,
    CalendarDays,
    Clock3,
    Facebook,
    Globe2,
    Instagram,
    MapPin,
    MessageCircle,
    Phone,
} from 'lucide-react';
import type { ReactNode } from 'react';
import type { Unit } from '../show';

const days = [
    'Domingo',
    'Segunda-feira',
    'Terça-feira',
    'Quarta-feira',
    'Quinta-feira',
    'Sexta-feira',
    'Sábado',
];

const externalUrl = (value?: string | null): string | null => {
    if (!value) {
        return null;
    }

    try {
        const url = new URL(value);

        return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
    } catch {
        return null;
    }
};

function DetailSection({
    title,
    icon,
    children,
}: {
    title: string;
    icon: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="rounded-2xl border border-[#39362f] bg-[#191919] p-5 md:p-6">
            <h2 className="mb-5 flex items-center gap-3 font-['Bodoni_Moda'] text-2xl text-[#f8f2e8]">
                <span className="text-[#e9c176]">{icon}</span>
                {title}
            </h2>
            {children}
        </section>
    );
}

function ContactLink({
    href,
    label,
    icon,
}: {
    href: string;
    label: string;
    icon: ReactNode;
}) {
    const external = href.startsWith('http');

    return (
        <a
            href={href}
            target={external ? '_blank' : undefined}
            rel={external ? 'noopener noreferrer' : undefined}
            className="flex min-h-12 items-center justify-between gap-3 rounded-xl border border-[#4d4635]/50 px-4 py-3 text-sm text-[#ffe9b0] transition hover:border-[#e9c176] hover:bg-[#25231e] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#ffe9b0]"
        >
            <span className="flex min-w-0 items-center gap-3">
                {icon}
                <span className="break-words">{label}</span>
            </span>
            {external ? (
                <ArrowUpRight className="size-4 shrink-0" aria-hidden="true" />
            ) : null}
        </a>
    );
}

export function AtelierEstablishmentDetails({
    unit,
    status,
    onBook,
}: {
    unit: Unit;
    status: { label: string; open: boolean };
    onBook: () => void;
}) {
    const contacts = unit.contacts;
    const phone = contacts?.phone?.replace(/\D/g, '');
    const whatsapp = contacts?.whatsapp?.replace(/\D/g, '');
    const whatsappNumber =
        whatsapp && whatsapp.length <= 11 ? `55${whatsapp}` : whatsapp;
    const address = unit.address;
    const addressLines = address
        ? [
              [address.street, address.number].filter(Boolean).join(', '),
              address.neighborhood,
              [address.city, address.state].filter(Boolean).join(' — '),
              address.postal_code ? `CEP ${address.postal_code}` : null,
          ].filter(Boolean)
        : [];
    const mapsQuery = addressLines.join(', ');
    const socialLinks = [
        {
            label: 'Instagram',
            href: externalUrl(contacts?.instagram_url),
            icon: <Instagram className="size-4 shrink-0" aria-hidden="true" />,
        },
        {
            label: 'Facebook',
            href: externalUrl(contacts?.facebook_url),
            icon: <Facebook className="size-4 shrink-0" aria-hidden="true" />,
        },
        {
            label: 'Site',
            href: externalUrl(contacts?.website_url),
            icon: <Globe2 className="size-4 shrink-0" aria-hidden="true" />,
        },
    ].filter((link) => link.href);
    const hasHours = Object.keys(unit.public_hours ?? {}).length > 0;
    const cover = unit.cover_image_url ?? unit.cover_url;
    const showContact = unit.sections?.contact !== false;
    const showHours = unit.sections?.hours !== false;

    return (
        <section
            aria-labelledby="establishment-heading"
            className="px-4 pb-8 font-['Manrope'] text-[#d0c5af] md:px-10"
        >
            <div className="mb-7 overflow-hidden rounded-2xl border border-[#39362f] bg-[#191919]">
                {cover && unit.sections?.hero !== false ? (
                    <img
                        src={cover}
                        alt={`Espaço ${unit.name}`}
                        className="h-44 w-full object-cover md:h-64"
                    />
                ) : null}
                <div className="p-5 md:p-8">
                    <p className="text-[10px] font-bold tracking-[0.2em] text-[#e9c176] uppercase">
                        Conheça nosso espaço
                    </p>
                    <h1
                        id="establishment-heading"
                        className="mt-2 font-['Bodoni_Moda'] text-3xl text-[#fdfbf7] md:text-4xl"
                    >
                        {unit.name}
                    </h1>
                    {unit.description ? (
                        <p className="mt-4 max-w-3xl text-sm leading-7 whitespace-pre-line">
                            {unit.description}
                        </p>
                    ) : null}
                    {showHours && hasHours ? (
                        <p className="mt-4 flex items-center gap-2 text-xs text-[#ffe9b0]">
                            <span
                                className={`size-1.5 rounded-full ${status.open ? 'bg-[#a8c79a]' : 'bg-[#a9a39a]'}`}
                            />
                            {status.label}
                        </p>
                    ) : null}
                </div>
            </div>
            <div className="grid items-start gap-5 lg:grid-cols-2">
                {showContact ? (
                    <div className="grid gap-5">
                        <DetailSection
                            title="Contato e redes sociais"
                            icon={
                                <Phone className="size-5" aria-hidden="true" />
                            }
                        >
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                                {phone ? (
                                    <ContactLink
                                        href={`tel:+${phone.length <= 11 ? `55${phone}` : phone}`}
                                        label={contacts?.phone ?? 'Telefone'}
                                        icon={
                                            <Phone
                                                className="size-4 shrink-0"
                                                aria-hidden="true"
                                            />
                                        }
                                    />
                                ) : null}
                                {whatsappNumber ? (
                                    <ContactLink
                                        href={`https://wa.me/${whatsappNumber}?text=${encodeURIComponent(`Olá! Vim do agendamento online de ${unit.name}. Poderia me ajudar, por gentileza?`)}`}
                                        label="WhatsApp"
                                        icon={
                                            <MessageCircle
                                                className="size-4 shrink-0"
                                                aria-hidden="true"
                                            />
                                        }
                                    />
                                ) : null}
                                {socialLinks.map((link) => (
                                    <ContactLink
                                        key={link.label}
                                        href={link.href!}
                                        label={link.label}
                                        icon={link.icon}
                                    />
                                ))}
                            </div>
                            {!phone &&
                            !whatsappNumber &&
                            socialLinks.length === 0 ? (
                                <p className="text-sm leading-6 text-[#a9a39a]">
                                    Contato e redes sociais ainda não
                                    informados.
                                </p>
                            ) : null}
                        </DetailSection>
                        <DetailSection
                            title="Onde estamos"
                            icon={
                                <MapPin className="size-5" aria-hidden="true" />
                            }
                        >
                            {addressLines.length ? (
                                <>
                                    <address className="mb-5 text-sm leading-7 not-italic">
                                        {addressLines.map((line, index) => (
                                            <span key={index} className="block">
                                                {line}
                                            </span>
                                        ))}
                                    </address>
                                    <ContactLink
                                        href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(mapsQuery)}`}
                                        label="Como chegar"
                                        icon={
                                            <MapPin
                                                className="size-4 shrink-0"
                                                aria-hidden="true"
                                            />
                                        }
                                    />
                                </>
                            ) : (
                                <p className="text-sm leading-6 text-[#a9a39a]">
                                    Endereço ainda não informado.
                                </p>
                            )}
                        </DetailSection>
                    </div>
                ) : null}
                {showHours ? (
                    <DetailSection
                        title="Horários de funcionamento"
                        icon={<Clock3 className="size-5" aria-hidden="true" />}
                    >
                        {hasHours ? (
                            <dl className="divide-y divide-[#39362f] text-sm">
                                {days.map((day, index) => {
                                    const hours =
                                        unit.public_hours?.[String(index)];
                                    const label = publicOpeningLabel(hours);

                                    return (
                                        <div
                                            key={day}
                                            className="flex flex-wrap justify-between gap-x-4 gap-y-1 py-3 first:pt-0"
                                        >
                                            <dt>{day}</dt>
                                            <dd
                                                className={
                                                    label === 'Fechado' ||
                                                    label === 'Não informado'
                                                        ? 'text-[#a9a39a]'
                                                        : 'text-[#ffe9b0]'
                                                }
                                            >
                                                {label}
                                            </dd>
                                        </div>
                                    );
                                })}
                            </dl>
                        ) : (
                            <p className="text-sm leading-6 text-[#a9a39a]">
                                Horários de funcionamento ainda não informados.
                            </p>
                        )}
                        <p className="mt-4 text-xs leading-5 text-[#a9a39a]">
                            Horário local do estabelecimento (
                            {unit.timezone.replaceAll('_', ' ')}). Consulte os
                            horários disponíveis ao agendar.
                        </p>
                    </DetailSection>
                ) : null}
            </div>
            <div className="mt-7 flex flex-col items-start justify-between gap-4 rounded-2xl border border-[#4d4635] bg-[#171612] p-5 sm:flex-row sm:items-center md:p-6">
                <div>
                    <h2 className="font-['Bodoni_Moda'] text-2xl text-[#f8f2e8]">
                        Seu próximo cuidado começa aqui.
                    </h2>
                    <p className="mt-2 text-sm leading-6 text-[#a9a39a]">
                        Escolha seus serviços e encontre o melhor horário.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={onBook}
                    style={{
                        backgroundColor: 'var(--booking-primary, #ffe9b0)',
                        color: 'var(--booking-primary-foreground, #261900)',
                    }}
                    className="flex min-h-12 w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-[#ffe9b0] px-5 py-3 text-sm font-bold text-[#261900] transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#ffe9b0] sm:w-auto"
                >
                    <CalendarDays className="size-4" aria-hidden="true" />
                    Voltar ao agendamento
                </button>
            </div>
        </section>
    );
}
