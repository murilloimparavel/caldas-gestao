import {
    CalendarDays,
    CheckCircle2,
    Clock3,
    LoaderCircle,
    Star,
    UserRound,
    Zap,
} from 'lucide-react';
import type { ReactNode } from 'react';

type StepService = {
    id: string;
    name: string;
    duration_minutes: number;
    price_cents: number;
};

type StepProfessional = {
    id: string;
    name: string;
};

type Slot = { starts_at: string; ends_at: string };

const money = (cents: number): string =>
    new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(cents / 100);

export function AtelierServiceSummaryCard({
    services,
    professional,
}: {
    services: StepService[];
    professional?: StepProfessional;
}): ReactNode {
    const totalCents = services.reduce(
        (total, service) => total + service.price_cents,
        0,
    );
    const totalMinutes = services.reduce(
        (total, service) => total + service.duration_minutes,
        0,
    );

    return (
        <div className="flex items-center justify-between rounded-xl border border-[#39362f] bg-[#151513] p-4">
            <div className="flex min-w-0 items-center gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#29251c] text-[#d4af37]">
                    <Star className="size-4" />
                </span>
                <div className="min-w-0">
                    <p className="truncate font-['DM_Sans'] text-sm font-semibold text-[#f4efe6]">
                        {services.map((service) => service.name).join(' + ') ||
                            'Serviço selecionado'}{' '}
                        {professional ? `· ${professional.name}` : ''}
                    </p>
                    <p className="font-['Space_Grotesk'] text-[10px] tracking-[0.12em] text-[#918b80] uppercase">
                        {services.length || 1} serviço
                        {services.length > 1 ? 's' : ''} · {totalMinutes} min
                    </p>
                </div>
            </div>
            <span className="shrink-0 font-['Space_Grotesk'] text-sm font-semibold text-[#d4af37]">
                {money(totalCents)}
            </span>
        </div>
    );
}

export function AtelierFirstAvailableCard({
    disabled,
    loading,
    selected,
    onSelect,
}: {
    disabled: boolean;
    loading: boolean;
    selected: boolean;
    onSelect: () => void;
}): ReactNode {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onSelect}
            className={`mt-3 flex w-full items-center gap-3 rounded-xl border p-4 text-left transition ${selected ? 'border-[#d4af37] bg-[#282317]' : 'border-[#4d4330] bg-[#171612] hover:border-[#d4af37]/70'}`}
        >
            <span className="flex size-11 shrink-0 items-center justify-center rounded-lg border border-[#d4af37]/70 text-[#d4af37]">
                <Zap className="size-5" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block font-['DM_Sans'] text-base font-semibold text-[#f4efe6]">
                    {loading
                        ? 'Buscando o primeiro horário'
                        : 'Primeiro disponível'}
                </span>
                <span className="mt-1 block font-['DM_Sans'] text-xs leading-5 text-[#a9a39a]">
                    {loading
                        ? 'Comparando os horários dos profissionais.'
                        : 'Encontre o melhor horário disponível entre os profissionais.'}
                </span>
            </span>
            {loading ? (
                <LoaderCircle className="size-6 shrink-0 animate-spin text-[#d4af37]" />
            ) : (
                <span
                    className={`size-6 shrink-0 rounded-full border ${selected ? 'border-[#d4af37] bg-[#d4af37]' : 'border-[#706752]'}`}
                />
            )}
        </button>
    );
}

export function AtelierProfessionalCard({
    professional,
    selected,
    onSelect,
}: {
    professional: StepProfessional & { avatar_url?: string | null };
    selected: boolean;
    onSelect: () => void;
}): ReactNode {
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            className={`flex min-h-[106px] items-center gap-3 rounded-xl border p-4 text-left transition ${selected ? 'border-[#d4af37] bg-[#201d16]' : 'border-[#39362f] bg-[#151513] hover:border-[#8b7635]'}`}
        >
            <span className="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-[#5b4d2b] bg-[#302c22] font-['DM_Sans'] text-xs font-semibold text-[#d4af37]">
                {professional.avatar_url ? (
                    <img
                        src={professional.avatar_url}
                        alt=""
                        className="size-full object-cover"
                    />
                ) : (
                    <UserRound className="size-5" />
                )}
            </span>
            <span className="min-w-0 flex-1 font-['DM_Sans'] text-base font-medium text-[#eee7dc]">
                {professional.name}
                <span className="mt-1 block font-['DM_Sans'] text-xs text-[#a9a39a]">
                    Atendimento presencial · Próximo horário disponível
                </span>
            </span>
            {selected ? (
                <CheckCircle2 className="size-6 shrink-0 text-[#d4af37]" />
            ) : (
                <span className="size-6 shrink-0 rounded-full border border-[#706752]" />
            )}
        </button>
    );
}

export function AtelierScheduleSlots({
    date,
    dateChoices,
    professionalId,
    slots,
    selectedSlot,
    timezone,
    onDateChange,
    onSlotChange,
    today,
    limit,
}: {
    date: string;
    dateChoices: string[];
    professionalId: string;
    slots: Slot[];
    selectedSlot: string;
    timezone: string;
    onDateChange: (value: string) => void;
    onSlotChange: (value: string) => void;
    today: string;
    limit: string;
}): ReactNode {
    const time = (iso: string): string =>
        new Intl.DateTimeFormat('pt-BR', {
            hour: '2-digit',
            minute: '2-digit',
            timeZone: timezone,
        }).format(new Date(iso));
    const groups = [
        {
            label: 'MANHÃ',
            slots: slots.filter(
                (item) => Number(time(item.starts_at).slice(0, 2)) < 12,
            ),
        },
        {
            label: 'TARDE',
            slots: slots.filter((item) => {
                const hour = Number(time(item.starts_at).slice(0, 2));

                return hour >= 12 && hour < 18;
            }),
        },
        {
            label: 'NOITE',
            slots: slots.filter(
                (item) => Number(time(item.starts_at).slice(0, 2)) >= 18,
            ),
        },
    ].filter((group) => group.slots.length > 0);

    return (
        <>
            <div className="mt-7 flex items-end justify-between gap-4">
                <h2 className="font-['Bodoni_Moda'] text-xl text-[#f8f2e8] italic">
                    Outros horários
                </h2>
                <span className="font-['DM_Sans'] text-xs text-[#918b80]">
                    Horário local da unidade
                </span>
            </div>
            <div className="mt-3 grid grid-cols-5 gap-2 overflow-x-auto pb-1">
                {dateChoices.map((choice) => {
                    const [year, month, day] = choice.split('-').map(Number);
                    const option = new Date(Date.UTC(year, month - 1, day, 12));
                    const active = choice === date;

                    return (
                        <button
                            type="button"
                            key={choice}
                            onClick={() => onDateChange(choice)}
                            disabled={!professionalId}
                            className={`min-w-[62px] rounded-lg border px-2 py-2 text-center transition ${active ? 'border-[#d4af37] bg-[#272117]' : 'border-[#39362f] bg-[#171616] hover:border-[#8b7635]'}`}
                        >
                            <span className="block font-['Space_Grotesk'] text-[9px] font-semibold tracking-[0.12em] text-[#d4af37] uppercase">
                                {option
                                    .toLocaleDateString('pt-BR', {
                                        weekday: 'short',
                                        timeZone: 'UTC',
                                    })
                                    .replace('.', '')}
                            </span>
                            <span className="mt-1 block font-['Bodoni_Moda'] text-lg text-[#f4efe6]">
                                {option.getDate()}
                            </span>
                            <span className="block font-['DM_Sans'] text-[9px] text-[#918b80] uppercase">
                                {option
                                    .toLocaleDateString('pt-BR', {
                                        month: 'short',
                                        timeZone: 'UTC',
                                    })
                                    .replace('.', '')}
                            </span>
                        </button>
                    );
                })}
            </div>
            <label className="mt-4 flex h-11 items-center gap-2 rounded-lg border border-[#39362f] bg-[#0f0f0d] px-3 focus-within:border-[#d4af37]">
                <CalendarDays className="size-4 text-[#d4af37]" />
                <span className="sr-only">Escolher outra data</span>
                <input
                    type="date"
                    value={date}
                    min={today}
                    max={limit}
                    disabled={!professionalId}
                    onChange={(event) => onDateChange(event.target.value)}
                    className="w-full bg-transparent font-['DM_Sans'] text-sm text-[#f4efe6] [color-scheme:dark] outline-none"
                />
            </label>
            {groups.map((group) => (
                <div key={group.label} className="mt-6">
                    <div className="flex items-center gap-3 border-b border-[#2b2925] pb-2">
                        <span className="font-['Space_Grotesk'] text-xs font-semibold tracking-[0.16em] text-[#d4af37] uppercase">
                            {group.label}
                        </span>
                        <span className="h-px flex-1 bg-[#2b2925]" />
                    </div>
                    <div className="mt-3 grid grid-cols-3 gap-2">
                        {group.slots.map((item) => (
                            <button
                                type="button"
                                key={item.starts_at}
                                onClick={() => onSlotChange(item.starts_at)}
                                className={`min-h-12 rounded-lg border px-2 font-['Space_Grotesk'] text-xs font-semibold transition ${selectedSlot === item.starts_at ? 'border-[#d4af37] bg-[#2a261e] text-[#d4af37]' : 'border-[#2b2925] bg-[#171616] text-[#f4efe6] hover:border-[#8b7635]'}`}
                            >
                                {time(item.starts_at)}
                            </button>
                        ))}
                    </div>
                </div>
            ))}
        </>
    );
}

export function AtelierBookingPolicy(): ReactNode {
    return (
        <div className="flex items-start gap-3 rounded-xl border border-[#39362f] bg-[#171616] p-4">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-full border border-[#8b7635] text-[#d4af37]">
                <Clock3 className="size-4" />
            </span>
            <div>
                <p className="font-['DM_Sans'] text-sm font-semibold text-[#f4efe6]">
                    Flexibilidade & transparência
                </p>
                <p className="mt-1 font-['DM_Sans'] text-xs leading-5 text-[#a9a39a]">
                    Cancelamento ou reagendamento sem custo até 2 horas antes do
                    horário. O valor é pago diretamente no local.
                </p>
            </div>
        </div>
    );
}
