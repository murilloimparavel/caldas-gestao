import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    ChartNoAxesCombined,
    Sparkles,
} from 'lucide-react';
import { login, register } from '@/routes';
import type { Branding } from '@/types/ui';

export default function Home({ branding }: { branding: Branding }) {
    return (
        <>
            <Head title="Gestão simples para negócios de beleza" />
            <main className="min-h-dvh overflow-hidden bg-[#f7f4ee] text-[#17252a]">
                <section className="relative mx-auto max-w-7xl px-6 pt-6 pb-20 lg:px-10 lg:pb-28">
                    <div className="absolute -top-40 -right-32 h-96 w-96 rounded-full bg-[#f2c1a7]/50 blur-3xl" />
                    <nav className="relative z-10 flex items-center justify-between">
                        <div className="flex items-center gap-3 font-semibold">
                            {branding.logoUrl ? (
                                <img
                                    src={branding.logoUrl}
                                    alt={branding.name}
                                    className="size-10 rounded-xl object-contain"
                                />
                            ) : (
                                <div className="flex size-10 items-center justify-center rounded-xl bg-[#3167d8] text-lg font-bold text-white">
                                    C
                                </div>
                            )}
                            <span>{branding.name}</span>
                        </div>
                        <div className="flex items-center gap-3 text-sm font-medium">
                            <Link
                                href={login()}
                                className="hidden rounded-full px-4 py-2 hover:bg-black/5 sm:block"
                            >
                                Entrar
                            </Link>
                            <Link
                                href={register()}
                                className="rounded-full bg-[#17252a] px-5 py-2.5 text-white transition-transform hover:-translate-y-0.5"
                            >
                                Começar grátis
                            </Link>
                        </div>
                    </nav>
                    <div className="relative z-10 grid items-center gap-14 pt-20 lg:grid-cols-[1.05fr_.95fr] lg:pt-28">
                        <div className="max-w-2xl">
                            <p className="mb-6 inline-flex items-center gap-2 rounded-full border border-[#d9d0c5] bg-white/70 px-4 py-2 text-sm font-medium text-[#3167d8]">
                                <Sparkles className="size-4" /> Mais tempo para
                                cuidar dos seus clientes
                            </p>
                            <h1 className="font-display text-5xl leading-[.98] font-semibold tracking-[-.05em] sm:text-7xl">
                                A operação do seu negócio, finalmente no ritmo
                                certo.
                            </h1>
                            <p className="mt-7 max-w-xl text-lg leading-8 text-[#5e6865]">
                                Agenda, clientes, serviços, comandas e
                                financeiro em um só lugar — com clareza para
                                decidir e leveza para executar.
                            </p>
                            <div className="mt-9 flex flex-wrap items-center gap-4">
                                <Link
                                    href={register()}
                                    className="inline-flex items-center gap-2 rounded-full bg-[#3167d8] px-6 py-3.5 font-semibold text-white shadow-lg shadow-[#3167d8]/20 transition-transform hover:-translate-y-0.5"
                                >
                                    Criar minha conta{' '}
                                    <ArrowRight className="size-4" />
                                </Link>
                                <span className="text-sm text-[#6d746f]">
                                    Sem cartão de crédito
                                </span>
                            </div>
                        </div>
                        <div className="relative rounded-[2rem] border border-[#ded5ca] bg-[#17252a] p-5 shadow-2xl shadow-[#17252a]/20 sm:p-7">
                            <div className="absolute -top-5 -right-5 rounded-2xl bg-[#efa83f] px-4 py-3 text-sm font-semibold text-[#17252a] shadow-lg">
                                Seu dia, sob controle
                            </div>
                            <div className="rounded-2xl bg-[#fdfbf7] p-5 sm:p-7">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm text-[#74807b]">
                                            Visão de hoje
                                        </p>
                                        <p className="mt-1 text-2xl font-semibold">
                                            Bom dia, vamos começar?
                                        </p>
                                    </div>
                                    <CalendarDays className="size-8 text-[#3167d8]" />
                                </div>
                                <div className="mt-8 grid gap-3 sm:grid-cols-2">
                                    <div className="rounded-xl bg-[#edf2ff] p-4">
                                        <p className="text-xs text-[#52658f]">
                                            Agendamentos
                                        </p>
                                        <p className="mt-2 text-3xl font-semibold text-[#214fae]">
                                            18
                                        </p>
                                    </div>
                                    <div className="rounded-xl bg-[#fff1df] p-4">
                                        <p className="text-xs text-[#946a36]">
                                            Faturamento
                                        </p>
                                        <p className="mt-2 text-3xl font-semibold text-[#9c6829]">
                                            R$ 2.840
                                        </p>
                                    </div>
                                </div>
                                <div className="mt-4 flex items-center gap-3 rounded-xl border border-[#e6e0d7] p-4">
                                    <ChartNoAxesCombined className="size-5 text-[#3167d8]" />
                                    <p className="text-sm text-[#59635f]">
                                        Decisões melhores começam com uma visão
                                        clara.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <section className="border-y border-[#ded5ca] bg-white/50 px-6 py-16 lg:px-10">
                    <div className="mx-auto grid max-w-7xl gap-8 md:grid-cols-3">
                        <div>
                            <p className="text-sm font-semibold tracking-[.18em] text-[#3167d8] uppercase">
                                Feito para o dia a dia
                            </p>
                            <h2 className="mt-3 text-3xl font-semibold tracking-tight">
                                Menos improviso. Mais consistência.
                            </h2>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3 md:col-span-2">
                            <div className="rounded-2xl border border-[#ded5ca] bg-[#fffdf9] p-5">
                                <CalendarDays className="size-5 text-[#3167d8]" />
                                <h3 className="mt-5 font-semibold">
                                    Agenda viva
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-[#6d746f]">
                                    Visualize horários, profissionais e encaixes
                                    sem perder o contexto.
                                </p>
                            </div>
                            <div className="rounded-2xl border border-[#ded5ca] bg-[#fffdf9] p-5">
                                <Sparkles className="size-5 text-[#c56f4d]" />
                                <h3 className="mt-5 font-semibold">
                                    Clientes próximos
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-[#6d746f]">
                                    Histórico e relacionamento para cada
                                    atendimento ter continuidade.
                                </p>
                            </div>
                            <div className="rounded-2xl border border-[#ded5ca] bg-[#fffdf9] p-5">
                                <ChartNoAxesCombined className="size-5 text-[#9c6829]" />
                                <h3 className="mt-5 font-semibold">
                                    Números úteis
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-[#6d746f]">
                                    Acompanhe o que importa para ajustar sua
                                    operação.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
                <section className="mx-auto max-w-7xl px-6 py-20 text-center lg:px-10">
                    <h2 className="mx-auto max-w-2xl text-4xl font-semibold tracking-tight">
                        Pronto para deixar a gestão mais leve?
                    </h2>
                    <p className="mx-auto mt-4 max-w-xl text-[#6d746f]">
                        Crie seu espaço em poucos minutos e comece pelo que já
                        faz parte da sua rotina.
                    </p>
                    <Link
                        href={register()}
                        className="mt-8 inline-flex items-center gap-2 rounded-full bg-[#17252a] px-6 py-3.5 font-semibold text-white"
                    >
                        Começar agora <ArrowRight className="size-4" />
                    </Link>
                </section>
            </main>
        </>
    );
}
