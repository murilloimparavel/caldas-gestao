import { Head } from '@inertiajs/react';
import type { Branding } from '@/types/ui';

interface BarberHomeProps {
    branding: Branding;
}

const SALES_WHATSAPP_PHONE = '5564992697946';
const CANONICAL_URL = 'https://gestao.caldasindica.com/?lp=barber';
const HERO_PROMISE =
    'O melhor aplicativo para o barbeiro que quer saber quanto realmente sobra.';
const PRESERVED_QUERY_KEYS = [
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_content',
    'utm_term',
    'fbclid',
    'gclid',
] as const;

const comparisonRows = [
    {
        label: 'Organiza horários',
        common: 'Sim',
        caldas: 'Sim',
    },
    {
        label: 'Liga atendimento ao caixa',
        common: 'Depende do sistema',
        caldas: 'Sim',
    },
    {
        label: 'Reúne despesas e comissões',
        common: 'Depende do sistema',
        caldas: 'Sim',
    },
    {
        label: 'Saldo após valores registrados',
        common: 'Depende do sistema',
        caldas: 'Sim',
    },
];

function buildWhatsAppHref(search: string): string {
    const params = new URLSearchParams(search);
    const tracking = PRESERVED_QUERY_KEYS.flatMap((key) => {
        const value = params.get(key);

        return value ? [`${key}: ${value}`] : [];
    });
    const message = [
        'Olá! Vi o Caldas Gestão para barbearias e quero organizar minha gestão.',
        tracking.length > 0 ? `Origem: ${tracking.join(' | ')}` : '',
    ]
        .filter(Boolean)
        .join('\n');

    return `https://wa.me/${SALES_WHATSAPP_PHONE}?text=${encodeURIComponent(message)}`;
}

function Logo({ name }: { name: string }) {
    return (
        <div className="flex items-center gap-3" aria-label={name}>
            <span className="grid size-9 place-items-center rounded-full bg-[#f5f5f3] text-[#171717]">
                <span className="h-4 w-1 rounded-full bg-current" />
            </span>
            <span className="text-sm font-semibold tracking-[-0.03em] text-[#f5f5f3]">
                {name}
            </span>
        </div>
    );
}

function BarberHome({ branding }: BarberHomeProps) {
    const whatsappHref = buildWhatsAppHref(
        typeof window === 'undefined' ? '' : window.location.search,
    );
    const brandName = branding?.name || 'Caldas Gestão';

    return (
        <>
            <Head>
                <title>Gestão para barbearias | Caldas Gestão</title>
                <meta
                    name="description"
                    content="Organize agenda, clientes, caixa e equipe da sua barbearia em um só lugar."
                />
                <link rel="canonical" href={CANONICAL_URL} />
                <meta property="og:url" content={CANONICAL_URL} />
                <meta property="og:type" content="website" />
                <meta
                    property="og:title"
                    content="Sua barbearia cresceu. Sua gestão acompanhou?"
                />
                <meta
                    property="og:description"
                    content="Pare de caçar horário no WhatsApp e fechar o caixa no chute. Conheça o Caldas Gestão."
                />
            </Head>

            <div className="min-h-screen overflow-hidden bg-[#111111] pb-20 font-sans text-[#f5f5f3] selection:bg-white selection:text-[#111111] sm:pb-0">
                <header className="mx-auto flex max-w-6xl items-center justify-between px-5 py-5 sm:px-8 lg:px-10">
                    <Logo name={brandName} />
                    <a
                        href={whatsappHref}
                        aria-label="Falar com um vendedor pelo WhatsApp"
                        className="hidden rounded-full border border-white/15 px-4 py-2 text-xs font-medium text-[#deded5] transition hover:border-white hover:text-white focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none sm:inline-flex"
                    >
                        Falar com alguém
                    </a>
                </header>

                <main>
                    <section className="mx-auto max-w-6xl px-5 pt-10 pb-14 sm:px-8 sm:pt-16 sm:pb-20 lg:px-10 lg:pt-24 lg:pb-28">
                        <div className="max-w-4xl">
                            <p className="mb-5 text-xs font-semibold tracking-[0.18em] text-[#f5f5f3] uppercase">
                                Para donos de barbearia
                            </p>
                            <h1 className="max-w-2xl text-[clamp(2.8rem,8vw,6.8rem)] leading-[0.92] font-semibold tracking-[-0.085em]">
                                Sua barbearia fatura. Você sabe quanto sobra?
                            </h1>
                            <p className="mt-5 max-w-xl text-base leading-6 text-[#b8b8b5] sm:mt-7 sm:text-xl sm:leading-8">
                                O Caldas conecta agenda, caixa, despesas e
                                comissões para mostrar o saldo com base no que
                                você registrou.
                            </p>
                            <p className="mt-5 max-w-xl text-base leading-6 font-semibold text-white sm:text-xl sm:leading-8">
                                {HERO_PROMISE}
                            </p>
                            <a
                                href={whatsappHref}
                                aria-label="Quero enxergar o que sobra pelo WhatsApp"
                                className="mt-7 inline-flex min-h-12 w-full items-center justify-center gap-3 rounded-full bg-white px-5 py-3.5 text-sm font-semibold text-[#171717] transition hover:bg-[#ededeb] focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#111111] focus-visible:outline-none sm:w-auto"
                            >
                                Quero enxergar o que sobra{' '}
                                <span aria-hidden="true">→</span>
                            </a>
                            <p className="mt-3 text-xs text-[#858583]">
                                Mensalidade acessível. Direto no WhatsApp.
                            </p>
                        </div>
                    </section>

                    <section className="border-y border-white/10 bg-[#202020]">
                        <div className="mx-auto max-w-6xl px-5 py-14 sm:px-8 sm:py-20 lg:px-10 lg:py-24">
                            <h2 className="mt-5 text-4xl leading-[0.96] font-semibold tracking-[-0.08em] sm:text-6xl">
                                Do corte ao saldo.
                            </h2>
                            <ol className="mt-8 grid gap-0 border-y border-white/15 sm:grid-cols-3">
                                <li className="border-b border-white/15 py-5 text-base leading-6 text-[#d0d0ce] sm:border-r sm:border-b-0 sm:px-5 sm:first:pl-0">
                                    <span className="mr-2 text-[#858583]">
                                        01
                                    </span>
                                    Atendimento registrado
                                </li>
                                <li className="border-b border-white/15 py-5 text-base leading-6 text-[#d0d0ce] sm:border-r sm:border-b-0 sm:px-5">
                                    <span className="mr-2 text-[#858583]">
                                        02
                                    </span>
                                    Entradas, despesas e comissões reunidas
                                </li>
                                <li className="py-5 text-base leading-6 text-[#d0d0ce] sm:px-5 sm:last:pr-0">
                                    <span className="mr-2 text-[#858583]">
                                        03
                                    </span>
                                    Saldo visível
                                </li>
                            </ol>
                        </div>
                    </section>

                    <section className="border-t border-white/10 bg-[#f5f5f3] text-[#171717]">
                        <div className="mx-auto max-w-6xl px-5 py-14 sm:px-8 sm:py-20 lg:px-10 lg:py-24">
                            <div className="max-w-2xl">
                                <p className="text-xs font-semibold tracking-[0.18em] text-[#666661] uppercase">
                                    Muito além da agenda
                                </p>
                                <h2 className="mt-5 max-w-xl text-4xl leading-[0.96] font-semibold tracking-[-0.08em] sm:text-6xl">
                                    Se ele só marca horário, ele não administra
                                    sua barbearia.
                                </h2>
                                <p className="mt-6 max-w-2xl text-base leading-7 text-[#51514d] sm:text-lg sm:leading-8">
                                    Agenda qualquer aplicativo organiza. O
                                    Caldas conecta o atendimento ao caixa e
                                    mostra o que sobra com base no que você
                                    registrou.
                                </p>
                            </div>

                            <div className="mt-10 hidden overflow-hidden border border-[#bdbdb8] md:block">
                                <table className="w-full border-collapse text-left text-sm">
                                    <caption className="sr-only">
                                        Comparação entre uma agenda comum e o
                                        Caldas Gestão
                                    </caption>
                                    <thead className="bg-[#e4e4e1] text-[#171717]">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-5 py-4 font-semibold"
                                            >
                                                O que você acompanha
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-5 py-4 font-semibold"
                                            >
                                                Agenda comum
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-5 py-4 font-semibold"
                                            >
                                                Caldas Gestão
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[#d0d0cc]">
                                        {comparisonRows.map((row) => (
                                            <tr key={row.label}>
                                                <th
                                                    scope="row"
                                                    className="px-5 py-4 font-medium"
                                                >
                                                    {row.label}
                                                </th>
                                                <td className="px-5 py-4 text-[#666661]">
                                                    {row.common}
                                                </td>
                                                <td className="px-5 py-4 font-semibold">
                                                    {row.caldas}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div
                                className="mt-8 border border-[#bdbdb8] bg-white text-xs leading-5 md:hidden"
                                role="table"
                                aria-label="Comparação entre categorias de aplicativos"
                            >
                                <div
                                    className="grid grid-cols-[1.4fr_0.8fr_0.8fr] border-b border-[#bdbdb8] bg-[#e4e4e1] font-semibold"
                                    role="row"
                                >
                                    <span
                                        className="px-3 py-3"
                                        role="columnheader"
                                    >
                                        Recurso
                                    </span>
                                    <span
                                        className="px-3 py-3"
                                        role="columnheader"
                                    >
                                        Agenda
                                    </span>
                                    <span
                                        className="px-3 py-3"
                                        role="columnheader"
                                    >
                                        Caldas
                                    </span>
                                </div>
                                {comparisonRows.map((row) => (
                                    <div
                                        key={row.label}
                                        className="grid grid-cols-[1.4fr_0.8fr_0.8fr] border-b border-[#deded9] last:border-b-0"
                                        role="row"
                                    >
                                        <span
                                            className="px-3 py-3 font-medium"
                                            role="rowheader"
                                        >
                                            {row.label}
                                        </span>
                                        <span
                                            className="px-3 py-3 text-[#777772]"
                                            role="cell"
                                        >
                                            {row.common}
                                        </span>
                                        <span
                                            className="px-3 py-3 font-semibold"
                                            role="cell"
                                        >
                                            {row.caldas}
                                        </span>
                                    </div>
                                ))}
                            </div>

                            <p className="mt-4 text-xs leading-5 text-[#777772]">
                                Comparação geral entre categorias; os recursos
                                variam conforme o fornecedor.
                            </p>
                        </div>
                    </section>

                    <section className="bg-[#e7e7e5] text-[#171717]">
                        <div className="mx-auto max-w-6xl px-5 py-14 sm:px-8 sm:py-20 lg:px-10 lg:py-24">
                            <div className="max-w-3xl">
                                <p className="text-xs font-semibold tracking-[0.18em] text-[#555552] uppercase">
                                    No fim do mês
                                </p>
                                <h2 className="mt-5 max-w-3xl text-4xl leading-[0.94] font-semibold tracking-[-0.08em] sm:text-6xl">
                                    Você quer continuar juntando informação ou
                                    abrir o sistema e entender o que aconteceu?
                                </h2>
                            </div>

                            <div className="mt-10 grid gap-px overflow-hidden border border-[#b9b9b4] bg-[#b9b9b4] lg:grid-cols-2">
                                <div className="bg-[#d8d8d4] p-5 sm:p-7">
                                    <p className="text-xs font-semibold tracking-[0.18em] text-[#666661] uppercase">
                                        Hoje
                                    </p>
                                    <h3 className="mt-4 text-2xl font-semibold tracking-[-0.06em] sm:text-3xl">
                                        No escuro
                                    </h3>
                                    <ul className="mt-6 divide-y divide-[#b9b9b4] text-sm leading-6 text-[#555550]">
                                        <li className="py-3 first:pt-0">
                                            Fechar caixa no chute
                                        </li>
                                        <li className="py-3">
                                            Calcular comissão por fora
                                        </li>
                                        <li className="py-3">
                                            Procurar informação em conversas
                                        </li>
                                    </ul>
                                </div>
                                <div className="bg-white p-5 sm:p-7">
                                    <p className="text-xs font-semibold tracking-[0.18em] text-[#666661] uppercase">
                                        Clareza
                                    </p>
                                    <h3 className="mt-4 text-2xl font-semibold tracking-[-0.06em] sm:text-3xl">
                                        No controle
                                    </h3>
                                    <ul className="mt-6 divide-y divide-[#deded9] text-sm leading-6 text-[#555550]">
                                        <li className="py-3 first:pt-0">
                                            Entradas e saídas reunidas
                                        </li>
                                        <li className="py-3">
                                            Comissões registradas
                                        </li>
                                        <li className="py-3">
                                            Agenda e caixa conectados
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div className="mt-10 flex flex-col gap-6 border-t border-[#b9b9b4] pt-6 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <p className="max-w-xl text-lg leading-7 text-[#454541] sm:text-xl sm:leading-8">
                                        Você não precisa de mais um aplicativo.
                                        Precisa parar de administrar no escuro.
                                    </p>
                                    <p className="mt-3 text-sm leading-6 text-[#666661]">
                                        Mensalidade acessível. Atendimento
                                        direto pelo WhatsApp.
                                    </p>
                                </div>
                                <a
                                    href={whatsappHref}
                                    aria-label="Quero enxergar o que sobra pelo WhatsApp"
                                    className="flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-[#171717] px-5 py-3.5 text-sm font-semibold text-[#f5f5f3] transition hover:bg-[#303030] focus-visible:ring-2 focus-visible:ring-[#171717] focus-visible:ring-offset-2 focus-visible:outline-none sm:w-auto sm:shrink-0"
                                >
                                    Quero enxergar o que sobra{' '}
                                    <span aria-hidden="true">→</span>
                                </a>
                            </div>
                        </div>
                    </section>
                </main>
                <div className="fixed inset-x-0 bottom-0 z-20 border-t border-white/10 bg-[#111111]/95 p-3 backdrop-blur sm:hidden">
                    <a
                        href={whatsappHref}
                        aria-label="Falar no WhatsApp"
                        className="flex min-h-12 items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-[#171717] focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                    >
                        Falar no WhatsApp
                    </a>
                </div>
                <footer className="border-t border-white/10 px-5 py-6 text-center text-xs text-[#727270] sm:px-8">
                    {brandName} · Gestão para barbearias
                </footer>
            </div>
        </>
    );
}

export default BarberHome;
