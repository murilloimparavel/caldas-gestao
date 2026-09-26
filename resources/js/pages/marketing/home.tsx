import { Head, Link } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    BarChart3,
    CalendarCheck,
    CalendarDays,
    Check,
    ChevronDown,
    Command,
    Menu,
    Scissors,
    Sparkles,
    UserRound,
    WalletCards,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { CSSProperties, KeyboardEvent as ReactKeyboardEvent } from 'react';
import { MetaPixel } from '@/components/meta-pixel';
import { cn } from '@/lib/utils';
import { login, register } from '@/routes';
import type { Branding } from '@/types/ui';
import { BrandMark } from './components/brand-mark';
import { CalendarPreview } from './components/calendar-preview';
import { CheckoutPreview } from './components/checkout-preview';
import { ProductShell } from './components/product-shell';
import { RetentionPreview } from './components/retention-preview';

interface HomeProps {
    branding: Branding;
    metaPixelId?: string | null;
}

type AudienceKey = 'barbearia' | 'salao' | 'estetica';

type Audience = {
    label: string;
    number: string;
    title: string;
    description: string;
    details: string[];
    icon: LucideIcon;
};

const audiences: Record<AudienceKey, Audience> = {
    barbearia: {
        label: 'Barbearias',
        number: '01',
        title: 'O ritmo da cadeira pede uma operação à altura.',
        description:
            'Uma visão limpa dos horários, serviços, profissionais, comandas e comissões. Menos conversa atravessada. Mais domínio do dia.',
        details: [
            'Agenda por profissional',
            'Comanda por atendimento',
            'Comissão no fechamento',
        ],
        icon: Scissors,
    },
    salao: {
        label: 'Salões',
        number: '02',
        title: 'Quando a equipe cresce, a clareza vira vantagem.',
        description:
            'Centralize serviços, produtos, horários e permissões para a equipe trabalhar no mesmo compasso, mesmo com uma operação movimentada.',
        details: [
            'Site próprio de agendamento',
            'Equipe e permissões',
            'Produtos e fornecedores',
        ],
        icon: Sparkles,
    },
    estetica: {
        label: 'Estética',
        number: '03',
        title: 'A jornada do cliente continua depois da sessão.',
        description:
            'Histórico, pacotes, assinaturas e retornos organizados para você cuidar da experiência sem perder a visão do negócio.',
        details: [
            'Histórico de clientes',
            'Pacotes e assinaturas',
            'Unidades e profissionais',
        ],
        icon: UserRound,
    },
};

const faqs = [
    {
        question: 'Meu cliente precisa instalar um aplicativo?',
        answer: 'Não. O seu espaço pode ter um site público de agendamento que abre direto no navegador do celular. Você compartilha o link no Instagram, no WhatsApp ou onde preferir.',
    },
    {
        question: 'O sistema evita conflitos de agenda?',
        answer: 'Sim. A disponibilidade considera profissionais, serviços e horários para reduzir conflitos na reserva. Você também pode conectar o Google Calendar.',
    },
    {
        question: 'Consigo registrar o atendimento e fechar a comanda?',
        answer: 'Sim. Serviços e produtos entram na comanda, e o fechamento registra o movimento ligado ao atendimento. Caixa, estoque e comissão acompanham esse fluxo.',
    },
    {
        question: 'Posso trabalhar com equipe e mais de uma unidade?',
        answer: 'Sim. O Caldas Gestão permite organizar profissionais, permissões, unidades e regras de acesso conforme a sua operação.',
    },
    {
        question: 'Existem pacotes e assinaturas?',
        answer: 'Sim. Você pode organizar pacotes de sessões e assinaturas para acompanhar o que foi contratado e o que ainda precisa ser realizado.',
    },
    {
        question: 'Como funciona o teste?',
        answer: 'Você tem 14 dias para testar o sistema. Não precisa cadastrar cartão para começar.',
    },
];

const capabilityGroups = [
    {
        index: '01',
        title: 'Atrair e agendar',
        icon: CalendarCheck,
        items: [
            'Site público do espaço',
            'Disponibilidade online',
            'Google Calendar',
            'Links de campanha',
        ],
    },
    {
        index: '02',
        title: 'Atender e vender',
        icon: Command,
        items: [
            'Clientes e histórico',
            'Serviços e categorias',
            'Produtos e fornecedores',
            'Comandas e checkout',
        ],
    },
    {
        index: '03',
        title: 'Controlar e crescer',
        icon: BarChart3,
        items: [
            'Caixa e movimentações',
            'Estoque',
            'Comissões',
            'Pacotes e assinaturas',
        ],
    },
];

const preservedQueryKeys = [
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_content',
    'utm_term',
    'fbclid',
    'gclid',
] as const;

const focusRingDark =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8FF3D] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0A0C0B]';
const focusRingLight =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0A0C0B] focus-visible:ring-offset-2 focus-visible:ring-offset-[#F2EFE7]';

function buildRegisterHref(search: string): string {
    const sourceParams = new URLSearchParams(search);
    const query: Record<string, string> = {};

    preservedQueryKeys.forEach((key) => {
        const value = sourceParams.get(key);

        if (value) {
            query[key] = value;
        }
    });

    return register({ query }).url;
}

function Kicker({
    children,
    light = false,
}: {
    children: string;
    light?: boolean;
}) {
    return (
        <p
            className={`flex items-center gap-3 text-3xs font-bold tracking-[0.22em] uppercase ${light ? 'text-[#C8FF3D]' : 'text-[#52605A]'}`}
        >
            <span
                className={`h-px w-8 ${light ? 'bg-[#C8FF3D]' : 'bg-[#52605A]'}`}
                aria-hidden="true"
            />
            {children}
        </p>
    );
}

function LineMark({ className = '' }: { className?: string }) {
    return (
        <span
            className={`pointer-events-none absolute h-px bg-current opacity-20 ${className}`}
            aria-hidden="true"
        />
    );
}

function Home({ branding, metaPixelId }: HomeProps) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [activeAudience, setActiveAudience] =
        useState<AudienceKey>('barbearia');
    const [activePreview, setActivePreview] = useState<
        'agenda' | 'comanda' | 'retencao'
    >('agenda');
    const [openFaq, setOpenFaq] = useState<number | null>(0);
    const [registerHref, setRegisterHref] = useState(() => register().url);
    const menuButtonRef = useRef<HTMLButtonElement>(null);
    const mobileMenuRef = useRef<HTMLDivElement>(null);
    const wasMobileMenuOpenRef = useRef(false);
    const brandName = branding?.name || 'Caldas Gestão';
    const audience = audiences[activeAudience];
    const AudienceIcon = audience.icon;
    const brandStyle = {
        '--cg-accent': '#C8FF3D',
        '--cg-bone': '#F2EFE7',
    } as CSSProperties;

    useEffect(() => {
        const syncRegisterHref = () => {
            setRegisterHref(buildRegisterHref(window.location.search));
        };
        const frame = window.requestAnimationFrame(syncRegisterHref);

        window.addEventListener('popstate', syncRegisterHref);

        return () => {
            window.cancelAnimationFrame(frame);
            window.removeEventListener('popstate', syncRegisterHref);
        };
    }, []);

    useEffect(() => {
        if (!mobileMenuOpen) {
            if (wasMobileMenuOpenRef.current) {
                wasMobileMenuOpenRef.current = false;
                menuButtonRef.current?.focus();
            }

            return;
        }

        wasMobileMenuOpenRef.current = true;

        const frame = window.requestAnimationFrame(() => {
            mobileMenuRef.current
                ?.querySelector<HTMLElement>('[data-mobile-first]')
                ?.focus();
        });

        return () => window.cancelAnimationFrame(frame);
    }, [mobileMenuOpen]);

    useEffect(() => {
        if (!mobileMenuOpen) {
            return;
        }

        const handleEscape = (event: globalThis.KeyboardEvent) => {
            if (event.key === 'Escape') {
                setMobileMenuOpen(false);
            }
        };

        document.addEventListener('keydown', handleEscape);

        return () => document.removeEventListener('keydown', handleEscape);
    }, [mobileMenuOpen]);

    const closeMobileMenu = () => setMobileMenuOpen(false);

    const handleAudienceKeyDown = (event: ReactKeyboardEvent<HTMLElement>) => {
        const keys = Object.keys(audiences) as AudienceKey[];
        const currentIndex = keys.indexOf(activeAudience);
        let nextIndex = currentIndex;

        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
            nextIndex = (currentIndex + 1) % keys.length;
        }

        if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
            nextIndex = (currentIndex - 1 + keys.length) % keys.length;
        }

        if (event.key === 'Home') {
            nextIndex = 0;
        }

        if (event.key === 'End') {
            nextIndex = keys.length - 1;
        }

        if (nextIndex !== currentIndex) {
            event.preventDefault();
            setActiveAudience(keys[nextIndex]);
            document.getElementById(`audience-tab-${keys[nextIndex]}`)?.focus();
        }
    };

    return (
        <>
            <MetaPixel pixelId={metaPixelId} />
            <Head title={`${brandName} · O sistema operacional da beleza`}>
                <meta
                    name="description"
                    content="O sistema operacional para barbearias, salões, estúdios de beleza e estética. Agende, atenda, feche e acompanhe a rotina em um só lugar."
                />
                <meta name="robots" content="index, follow" />
                <link rel="canonical" href="https://gestao.caldasindica.com/" />
                <meta property="og:type" content="website" />
                <meta
                    property="og:title"
                    content={`${brandName} · O sistema operacional da beleza`}
                />
                <meta
                    property="og:description"
                    content="Seu espaço já tem estilo. A gestão deve estar no mesmo nível."
                />
                <meta property="og:site_name" content={brandName} />
                <meta property="og:locale" content="pt_BR" />
                <meta
                    property="og:url"
                    content="https://gestao.caldasindica.com/"
                />
                <meta
                    property="og:image"
                    content="https://gestao.caldasindica.com/marketing/caldas-hero-editorial.png"
                />
                <meta
                    property="og:image:alt"
                    content="Gestora em um espaço de beleza contemporâneo"
                />
                <meta property="og:image:width" content="1672" />
                <meta property="og:image:height" content="941" />
                <meta name="twitter:card" content="summary_large_image" />
                <meta
                    name="twitter:title"
                    content={`${brandName} · O sistema operacional da beleza`}
                />
                <meta
                    name="twitter:description"
                    content="Agendamentos, atendimento, caixa, equipe e clientes em uma operação com mais clareza."
                />
                <meta
                    name="twitter:image"
                    content="https://gestao.caldasindica.com/marketing/caldas-hero-editorial.png"
                />
            </Head>

            <div
                style={brandStyle}
                className="min-h-screen bg-[#0A0C0B] font-sans text-[#F2EFE7] selection:bg-[#C8FF3D] selection:text-[#0A0C0B]"
            >
                <header
                    className="absolute top-0 right-0 left-0 z-50 border-b border-white/15 bg-[#0A0C0B]/55 text-[#F2EFE7] backdrop-blur-sm"
                    aria-label="Navegação principal"
                >
                    <div className="mx-auto flex h-[78px] max-w-[1440px] items-center justify-between px-5 sm:px-8 lg:px-12">
                        <a
                            href="#top"
                            aria-label={`Ir para o início de ${brandName}`}
                            className={focusRingDark}
                            onClick={closeMobileMenu}
                        >
                            <BrandMark
                                branding={{ ...branding, name: brandName }}
                                light
                            />
                        </a>

                        <nav
                            className="hidden items-center gap-8 text-3xs font-bold tracking-[0.1em] uppercase lg:flex"
                            aria-label="Links da página"
                        >
                            <a
                                className={`transition-colors hover:text-[#C8FF3D] ${focusRingDark}`}
                                href="#sistema"
                            >
                                Sistema
                            </a>
                            <a
                                className={`transition-colors hover:text-[#C8FF3D] ${focusRingDark}`}
                                href="#para-quem"
                            >
                                Para quem
                            </a>
                            <a
                                className={`transition-colors hover:text-[#C8FF3D] ${focusRingDark}`}
                                href="#duvidas"
                            >
                                Dúvidas
                            </a>
                        </nav>

                        <div className="hidden items-center gap-5 lg:flex">
                            <Link
                                className={`text-3xs font-bold tracking-[0.1em] uppercase transition-colors hover:text-[#C8FF3D] ${focusRingDark}`}
                                href={login()}
                            >
                                Entrar
                            </Link>
                            <Link
                                className={`inline-flex items-center gap-2 bg-[#C8FF3D] px-4 py-3 text-3xs font-bold tracking-[0.08em] text-[#0A0C0B] uppercase transition hover:bg-[#F2EFE7] ${focusRingDark}`}
                                href={registerHref}
                            >
                                Teste grátis <ArrowUpRight className="size-4" />
                            </Link>
                        </div>

                        <button
                            ref={menuButtonRef}
                            type="button"
                            className={`inline-flex size-11 items-center justify-center border border-white/25 text-[#F2EFE7] transition hover:border-[#C8FF3D] hover:text-[#C8FF3D] lg:hidden ${focusRingDark}`}
                            aria-label={
                                mobileMenuOpen ? 'Fechar menu' : 'Abrir menu'
                            }
                            aria-expanded={mobileMenuOpen}
                            aria-controls="mobile-menu"
                            onClick={() => setMobileMenuOpen((open) => !open)}
                        >
                            {mobileMenuOpen ? (
                                <X className="size-5" />
                            ) : (
                                <Menu className="size-5" />
                            )}
                        </button>
                    </div>

                    {mobileMenuOpen && (
                        <div
                            ref={mobileMenuRef}
                            id="mobile-menu"
                            className="border-t border-white/15 bg-[#0A0C0B] px-5 py-5 lg:hidden"
                        >
                            <nav
                                className="flex flex-col"
                                aria-label="Links móveis"
                            >
                                <a
                                    data-mobile-first
                                    className={`border-b border-white/10 py-4 text-sm font-bold tracking-[0.08em] uppercase hover:text-[#C8FF3D] ${focusRingDark}`}
                                    href="#sistema"
                                    onClick={closeMobileMenu}
                                >
                                    Sistema
                                </a>
                                <a
                                    className={`border-b border-white/10 py-4 text-sm font-bold tracking-[0.08em] uppercase hover:text-[#C8FF3D] ${focusRingDark}`}
                                    href="#para-quem"
                                    onClick={closeMobileMenu}
                                >
                                    Para quem
                                </a>
                                <a
                                    className={`border-b border-white/10 py-4 text-sm font-bold tracking-[0.08em] uppercase hover:text-[#C8FF3D] ${focusRingDark}`}
                                    href="#duvidas"
                                    onClick={closeMobileMenu}
                                >
                                    Dúvidas
                                </a>
                                <div className="mt-5 grid grid-cols-2 gap-3">
                                    <Link
                                        className={`border border-white/25 px-3 py-3 text-center text-3xs font-bold tracking-[0.08em] uppercase transition hover:border-[#C8FF3D] hover:text-[#C8FF3D] ${focusRingDark}`}
                                        href={login()}
                                        onClick={closeMobileMenu}
                                    >
                                        Entrar
                                    </Link>
                                    <Link
                                        className={`bg-[#C8FF3D] px-3 py-3 text-center text-3xs font-bold tracking-[0.08em] text-[#0A0C0B] uppercase transition hover:bg-[#F2EFE7] ${focusRingDark}`}
                                        href={registerHref}
                                        onClick={closeMobileMenu}
                                    >
                                        Teste grátis
                                    </Link>
                                </div>
                            </nav>
                        </div>
                    )}
                </header>

                <main id="top">
                    <section className="relative isolate min-h-[720px] overflow-hidden border-b border-white/15 sm:min-h-[820px] lg:min-h-[min(900px,100vh)]">
                        <picture>
                            <source
                                srcSet="/marketing/caldas-hero-editorial.webp"
                                type="image/webp"
                            />
                            <img
                                width={1672}
                                height={941}
                                fetchPriority="high"
                                decoding="async"
                                className="absolute inset-0 -z-20 size-full object-cover object-[62%_center]"
                                src="/marketing/caldas-hero-editorial.png"
                                alt="Gestora em um espaço de beleza contemporâneo"
                            />
                        </picture>
                        <div
                            className="absolute inset-0 -z-10 bg-[#0A0C0B]/60"
                            aria-hidden="true"
                        />
                        <LineMark className="top-[32%] right-0 left-0" />
                        <LineMark className="top-[68%] right-0 left-0" />

                        <div className="mx-auto flex min-h-[720px] max-w-[1440px] flex-col justify-end px-5 pt-32 pb-10 sm:min-h-[820px] sm:px-8 sm:pb-14 lg:min-h-[min(900px,100vh)] lg:px-12 lg:pb-20">
                            <div className="grid items-end gap-12 lg:grid-cols-[minmax(0,1fr)_280px] lg:gap-16">
                                <div className="max-w-[930px]">
                                    <Kicker light>
                                        O sistema operacional da beleza
                                    </Kicker>
                                    <h1 className="mt-7 max-w-[950px] text-[clamp(3.4rem,8.7vw,9.5rem)] leading-[0.82] font-bold tracking-[-0.085em] text-[#F2EFE7]">
                                        Seu negócio merece funcionar tão bem
                                        quanto parece.
                                    </h1>
                                    <div className="mt-9 flex flex-col gap-7 sm:flex-row sm:items-end sm:justify-between">
                                        <p className="max-w-md text-base leading-7 text-[#D4D0C5] sm:text-lg">
                                            Agenda, atendimento, caixa, equipe e
                                            clientes em um só sistema, feito
                                            para quem leva o próprio espaço a
                                            sério.
                                        </p>
                                        <div className="flex shrink-0 flex-col gap-3 sm:items-end">
                                            <Link
                                                className={`inline-flex items-center justify-center gap-3 bg-[#C8FF3D] px-6 py-4 text-sm font-bold text-[#0A0C0B] transition hover:bg-[#F2EFE7] ${focusRingDark}`}
                                                href={registerHref}
                                            >
                                                Testar grátis por 14 dias{' '}
                                                <ArrowRight className="size-4" />
                                            </Link>
                                            <span className="text-3xs font-bold tracking-[0.12em] text-[#B6B2A8] uppercase">
                                                14 dias para testar. Sem cartão.
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className="hidden border-l border-white/30 pl-6 lg:block">
                                    <span className="text-3xs font-bold tracking-[0.2em] text-[#C8FF3D] uppercase">
                                        Um sistema para o ritmo real
                                    </span>
                                    <p className="mt-5 text-3xl leading-none font-bold tracking-[-0.06em] text-[#F2EFE7]">
                                        Da primeira reserva ao próximo retorno.
                                    </p>
                                    <a
                                        className={`mt-8 inline-flex items-center gap-2 text-3xs font-bold tracking-[0.12em] text-[#F2EFE7] uppercase transition hover:text-[#C8FF3D] ${focusRingDark}`}
                                        href="#sistema"
                                    >
                                        Explorar o sistema{' '}
                                        <ArrowDownRight className="size-4" />
                                    </a>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div className="overflow-hidden border-b border-[#0A0C0B]/20 bg-[#C8FF3D] text-[#0A0C0B]">
                        <div className="flex min-w-max items-center gap-8 py-4 text-3xs font-bold tracking-[0.2em] uppercase sm:gap-14 sm:py-5">
                            <span className="pl-5 sm:pl-8">Barbearias</span>
                            <span aria-hidden="true">·</span>
                            <span>Salões</span>
                            <span aria-hidden="true">·</span>
                            <span>Estética</span>
                            <span aria-hidden="true">·</span>
                            <span>Agenda</span>
                            <span aria-hidden="true">·</span>
                            <span>Caixa</span>
                            <span aria-hidden="true">·</span>
                            <span>Equipe</span>
                            <span aria-hidden="true">·</span>
                            <span className="pr-5 sm:pr-8">Clientes</span>
                        </div>
                    </div>

                    <section className="relative overflow-hidden bg-[#F2EFE7] text-[#0A0C0B]">
                        <LineMark className="top-24 right-0 left-0 opacity-10" />
                        <div className="mx-auto max-w-[1440px] px-5 py-24 sm:px-8 sm:py-32 lg:px-12 lg:py-44">
                            <div className="grid gap-12 lg:grid-cols-[0.85fr_1.15fr] lg:gap-24">
                                <div>
                                    <Kicker>
                                        O problema não é falta de esforço
                                    </Kicker>
                                    <p className="mt-8 max-w-sm text-xl leading-8 tracking-[-0.03em] sm:text-2xl">
                                        É ter uma operação inteira espalhada
                                        entre caderno, planilha, mensagens e
                                        memória.
                                    </p>
                                </div>
                                <div>
                                    <h2 className="max-w-5xl text-[clamp(3.4rem,8.5vw,8.5rem)] leading-[0.82] font-bold tracking-[-0.09em]">
                                        Menos improviso.
                                        <br />
                                        <span className="text-[#52605A]">
                                            Mais espaço para crescer.
                                        </span>
                                    </h2>
                                    <div className="mt-12 grid gap-6 border-t border-[#0A0C0B]/20 pt-6 sm:grid-cols-3 sm:gap-8">
                                        <div>
                                            <span className="text-4xl leading-none font-bold tracking-[-0.08em]">
                                                01
                                            </span>
                                            <p className="mt-4 max-w-[180px] text-sm leading-6 text-[#52605A]">
                                                O cliente encontra um horário
                                                sem depender de resposta.
                                            </p>
                                        </div>
                                        <div>
                                            <span className="text-4xl leading-none font-bold tracking-[-0.08em]">
                                                02
                                            </span>
                                            <p className="mt-4 max-w-[180px] text-sm leading-6 text-[#52605A]">
                                                A equipe sabe o que acontece
                                                antes do dia começar.
                                            </p>
                                        </div>
                                        <div>
                                            <span className="text-4xl leading-none font-bold tracking-[-0.08em]">
                                                03
                                            </span>
                                            <p className="mt-4 max-w-[180px] text-sm leading-6 text-[#52605A]">
                                                O fechamento deixa um rastro que
                                                você consegue acompanhar.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="sistema"
                        className="scroll-mt-8 border-y border-white/15 bg-[#101310]"
                    >
                        <div className="mx-auto max-w-[1440px] px-5 py-24 sm:px-8 sm:py-32 lg:px-12 lg:py-40">
                            <div className="flex flex-col justify-between gap-8 lg:flex-row lg:items-end">
                                <div>
                                    <Kicker light>
                                        Um fluxo, não um amontoado de funções
                                    </Kicker>
                                    <h2 className="mt-7 max-w-3xl text-[clamp(3rem,6.5vw,7rem)] leading-[0.86] font-bold tracking-[-0.08em] text-[#F2EFE7]">
                                        Tudo gira no mesmo fluxo.
                                    </h2>
                                </div>
                                <p className="max-w-sm text-sm leading-6 text-[#A9A79D] lg:pb-2">
                                    O sistema acompanha o negócio inteiro. Você
                                    começa onde a rotina mais aperta e conecta o
                                    resto no tempo certo.
                                </p>
                            </div>

                            <div className="relative mt-16 grid gap-10 lg:mt-24 lg:grid-cols-[0.75fr_1.5fr_0.75fr] lg:items-center lg:gap-8">
                                <div className="order-2 space-y-7 lg:order-1">
                                    <div className="border-t border-white/20 pt-5">
                                        <div className="flex items-center justify-between gap-4">
                                            <span className="text-3xs font-bold tracking-[0.18em] text-[#C8FF3D] uppercase">
                                                01
                                            </span>
                                            <CalendarDays className="size-5 text-[#C8FF3D]" />
                                        </div>
                                        <h3 className="mt-5 text-xl font-bold tracking-[-0.04em] text-[#F2EFE7]">
                                            Agenda que se explica.
                                        </h3>
                                        <p className="mt-3 text-sm leading-6 text-[#A9A79D]">
                                            Seu site público mostra serviços,
                                            profissionais e horários
                                            disponíveis.
                                        </p>
                                    </div>
                                    <div className="border-t border-white/20 pt-5">
                                        <div className="flex items-center justify-between gap-4">
                                            <span className="text-3xs font-bold tracking-[0.18em] text-[#C8FF3D] uppercase">
                                                02
                                            </span>
                                            <UserRound className="size-5 text-[#C8FF3D]" />
                                        </div>
                                        <h3 className="mt-5 text-xl font-bold tracking-[-0.04em] text-[#F2EFE7]">
                                            Histórico que acompanha.
                                        </h3>
                                        <p className="mt-3 text-sm leading-6 text-[#A9A79D]">
                                            Cada atendimento deixa contexto para
                                            a próxima conversa.
                                        </p>
                                    </div>
                                </div>

                                <div className="relative order-1 flex min-h-[390px] items-center justify-center overflow-hidden border border-white/15 bg-[#0A0C0B] lg:order-2 lg:min-h-[560px]">
                                    <span className="absolute top-7 left-7 text-3xs font-bold tracking-[0.2em] text-[#777A70] uppercase">
                                        Caldas / core
                                    </span>
                                    <span className="absolute right-7 bottom-7 text-right text-3xs font-bold tracking-[0.2em] text-[#777A70] uppercase">
                                        A rotina
                                        <br />
                                        em movimento
                                    </span>
                                    <div
                                        className="absolute top-1/2 left-1/2 size-[78%] -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/10"
                                        aria-hidden="true"
                                    />
                                    <div
                                        className="absolute top-1/2 left-1/2 size-[52%] -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#C8FF3D]/40"
                                        aria-hidden="true"
                                    />
                                    <picture>
                                        <source
                                            srcSet="/marketing/caldas-flow-engine.webp"
                                            type="image/webp"
                                        />
                                        <img
                                            width={1122}
                                            height={1402}
                                            loading="lazy"
                                            decoding="async"
                                            className="relative z-10 max-h-[420px] w-[78%] object-contain drop-shadow-[0_24px_40px_rgba(0,0,0,0.65)] transition-transform duration-700 motion-safe:hover:scale-105"
                                            src="/marketing/caldas-flow-engine.png"
                                            alt="Objeto abstrato em preto e chartreuse representando o fluxo conectado do sistema"
                                        />
                                    </picture>
                                </div>

                                <div className="order-3 space-y-7">
                                    <div className="border-t border-white/20 pt-5">
                                        <div className="flex items-center justify-between gap-4">
                                            <span className="text-3xs font-bold tracking-[0.18em] text-[#C8FF3D] uppercase">
                                                03
                                            </span>
                                            <WalletCards className="size-5 text-[#C8FF3D]" />
                                        </div>
                                        <h3 className="mt-5 text-xl font-bold tracking-[-0.04em] text-[#F2EFE7]">
                                            Fechamento sem ponto cego.
                                        </h3>
                                        <p className="mt-3 text-sm leading-6 text-[#A9A79D]">
                                            Comanda, caixa, estoque e comissão
                                            conectados ao que aconteceu.
                                        </p>
                                    </div>
                                    <div className="border-t border-white/20 pt-5">
                                        <div className="flex items-center justify-between gap-4">
                                            <span className="text-3xs font-bold tracking-[0.18em] text-[#C8FF3D] uppercase">
                                                04
                                            </span>
                                            <BarChart3 className="size-5 text-[#C8FF3D]" />
                                        </div>
                                        <h3 className="mt-5 text-xl font-bold tracking-[-0.04em] text-[#F2EFE7]">
                                            Próximo retorno visível.
                                        </h3>
                                        <p className="mt-3 text-sm leading-6 text-[#A9A79D]">
                                            Pacotes, assinaturas e clientes
                                            inativos com mais contexto.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Live Interface Explorer */}
                            <div className="mt-20 border-t border-white/10 pt-16">
                                <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                                    <div>
                                        <span className="text-3xs font-bold tracking-[0.2em] text-[#C8FF3D] uppercase">
                                            A experiência em tela
                                        </span>
                                        <h3 className="mt-3 text-2xl font-bold tracking-[-0.04em] text-[#F2EFE7] sm:text-3xl">
                                            O produto desenhado para a
                                            velocidade do balcão.
                                        </h3>
                                    </div>

                                    {/* Tab switcher */}
                                    <div
                                        className="flex flex-wrap gap-2 rounded-xl border border-white/15 bg-[#0A0C0B] p-1.5"
                                        role="tablist"
                                        aria-label="Demonstração do produto"
                                    >
                                        <button
                                            type="button"
                                            role="tab"
                                            aria-selected={
                                                activePreview === 'agenda'
                                            }
                                            onClick={() =>
                                                setActivePreview('agenda')
                                            }
                                            className={cn(
                                                'rounded-lg px-4 py-2 text-xs font-bold transition focus-visible:ring-2 focus-visible:ring-[#C8FF3D] focus-visible:outline-hidden',
                                                activePreview === 'agenda'
                                                    ? 'bg-[#C8FF3D] text-[#0A0C0B]'
                                                    : 'text-[#A9A79D] hover:text-white',
                                            )}
                                        >
                                            01. Agenda Multiprofissional
                                        </button>
                                        <button
                                            type="button"
                                            role="tab"
                                            aria-selected={
                                                activePreview === 'comanda'
                                            }
                                            onClick={() =>
                                                setActivePreview('comanda')
                                            }
                                            className={cn(
                                                'rounded-lg px-4 py-2 text-xs font-bold transition focus-visible:ring-2 focus-visible:ring-[#C8FF3D] focus-visible:outline-hidden',
                                                activePreview === 'comanda'
                                                    ? 'bg-[#C8FF3D] text-[#0A0C0B]'
                                                    : 'text-[#A9A79D] hover:text-white',
                                            )}
                                        >
                                            02. Comanda & Fechamento
                                        </button>
                                        <button
                                            type="button"
                                            role="tab"
                                            aria-selected={
                                                activePreview === 'retencao'
                                            }
                                            onClick={() =>
                                                setActivePreview('retencao')
                                            }
                                            className={cn(
                                                'rounded-lg px-4 py-2 text-xs font-bold transition focus-visible:ring-2 focus-visible:ring-[#C8FF3D] focus-visible:outline-hidden',
                                                activePreview === 'retencao'
                                                    ? 'bg-[#C8FF3D] text-[#0A0C0B]'
                                                    : 'text-[#A9A79D] hover:text-white',
                                            )}
                                        >
                                            03. Retenção & Retornos
                                        </button>
                                    </div>
                                </div>

                                <div className="mt-8">
                                    {activePreview === 'agenda' && (
                                        <ProductShell
                                            title="Caldas / Agenda em Tempo Real"
                                            url="caldas.app/agenda"
                                            badge="Sem Conflitos"
                                        >
                                            <CalendarPreview />
                                        </ProductShell>
                                    )}
                                    {activePreview === 'comanda' && (
                                        <ProductShell
                                            title="Caldas / Vendas & Comandas"
                                            url="caldas.app/vendas/comandas"
                                            badge="Caixa Integrado"
                                        >
                                            <CheckoutPreview />
                                        </ProductShell>
                                    )}
                                    {activePreview === 'retencao' && (
                                        <ProductShell
                                            title="Caldas / Inteligência de Clientes"
                                            url="caldas.app/clientes/retencao"
                                            badge="Previsão de Retorno"
                                        >
                                            <RetentionPreview />
                                        </ProductShell>
                                    )}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="bg-[#C8FF3D] text-[#0A0C0B]">
                        <div className="mx-auto grid max-w-[1440px] gap-12 px-5 py-24 sm:px-8 sm:py-32 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20 lg:px-12 lg:py-40">
                            <div>
                                <Kicker>O que muda na prática</Kicker>
                                <p className="mt-8 max-w-xs text-xl leading-8 tracking-[-0.03em]">
                                    O valor não está em ter mais um lugar para
                                    preencher. Está em fazer as partes
                                    conversarem.
                                </p>
                                <Link
                                    className={`mt-10 inline-flex items-center gap-2 border-b border-[#0A0C0B] pb-2 text-3xs font-bold tracking-[0.12em] uppercase transition hover:gap-4 ${focusRingLight}`}
                                    href={registerHref}
                                >
                                    Começar pela minha rotina{' '}
                                    <ArrowRight className="size-4" />
                                </Link>
                            </div>
                            <div className="space-y-0 border-t border-[#0A0C0B]/30">
                                <div className="grid gap-5 border-b border-[#0A0C0B]/30 py-7 sm:grid-cols-[72px_1fr_1fr] sm:gap-8 sm:py-9">
                                    <span className="text-4xl font-bold tracking-[-0.08em]">
                                        01
                                    </span>
                                    <h3 className="text-2xl font-bold tracking-[-0.05em] sm:text-3xl">
                                        O cliente agenda quando faz sentido para
                                        ele.
                                    </h3>
                                    <p className="text-sm leading-6 text-[#34402B]">
                                        Um site público do seu espaço, com
                                        disponibilidade real, sem exigir
                                        aplicativo.
                                    </p>
                                </div>
                                <div className="grid gap-5 border-b border-[#0A0C0B]/30 py-7 sm:grid-cols-[72px_1fr_1fr] sm:gap-8 sm:py-9">
                                    <span className="text-4xl font-bold tracking-[-0.08em]">
                                        02
                                    </span>
                                    <h3 className="text-2xl font-bold tracking-[-0.05em] sm:text-3xl">
                                        O atendimento termina com registro.
                                    </h3>
                                    <p className="text-sm leading-6 text-[#34402B]">
                                        Serviços, produtos, caixa, estoque e
                                        comissões seguem o mesmo movimento.
                                    </p>
                                </div>
                                <div className="grid gap-5 py-7 sm:grid-cols-[72px_1fr_1fr] sm:gap-8 sm:py-9">
                                    <span className="text-4xl font-bold tracking-[-0.08em]">
                                        03
                                    </span>
                                    <h3 className="text-2xl font-bold tracking-[-0.05em] sm:text-3xl">
                                        O próximo passo deixa de depender da
                                        memória.
                                    </h3>
                                    <p className="text-sm leading-6 text-[#34402B]">
                                        Histórico, pacotes, assinaturas e
                                        clientes inativos ajudam a manter a
                                        relação viva.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="para-quem"
                        className="scroll-mt-8 border-b border-white/15 bg-[#F2EFE7] text-[#0A0C0B]"
                    >
                        <div className="mx-auto max-w-[1440px] px-5 py-24 sm:px-8 sm:py-32 lg:px-12 lg:py-40">
                            <div className="flex flex-col justify-between gap-7 lg:flex-row lg:items-end">
                                <div>
                                    <Kicker>
                                        O seu espaço tem um ritmo próprio
                                    </Kicker>
                                    <h2 className="mt-7 max-w-4xl text-[clamp(3rem,6.5vw,7rem)] leading-[0.86] font-bold tracking-[-0.09em]">
                                        A gestão acompanha.
                                    </h2>
                                </div>
                                <p className="max-w-sm text-sm leading-6 text-[#52605A] lg:pb-2">
                                    Uma base sólida para negócios diferentes,
                                    com a flexibilidade de uma operação que está
                                    evoluindo.
                                </p>
                            </div>

                            <div className="mt-14 border-t border-[#0A0C0B]/25 sm:mt-20">
                                <div
                                    className="flex flex-col border-b border-[#0A0C0B]/25 sm:flex-row"
                                    role="tablist"
                                    tabIndex={0}
                                    aria-label="Tipos de negócio"
                                    onKeyDown={handleAudienceKeyDown}
                                >
                                    {(
                                        Object.keys(audiences) as AudienceKey[]
                                    ).map((key) => {
                                        const item = audiences[key];
                                        const isActive = activeAudience === key;

                                        return (
                                            <button
                                                key={key}
                                                id={`audience-tab-${key}`}
                                                type="button"
                                                role="tab"
                                                aria-selected={isActive}
                                                aria-controls={`audience-panel-${key}`}
                                                tabIndex={isActive ? 0 : -1}
                                                className={`flex flex-1 items-center justify-between border-b border-[#0A0C0B]/15 px-1 py-5 text-left text-sm font-bold tracking-[-0.02em] transition last:border-0 sm:border-r sm:border-b-0 sm:px-5 sm:py-7 ${isActive ? 'text-[#0A0C0B]' : 'text-[#87918F] hover:text-[#0A0C0B]'} ${focusRingLight}`}
                                                onClick={() =>
                                                    setActiveAudience(key)
                                                }
                                            >
                                                <span className="flex items-center gap-4">
                                                    <span
                                                        className={`text-3xs tracking-[0.18em] ${isActive ? 'text-[#52605A]' : 'text-[#A4AAA1]'}`}
                                                    >
                                                        {item.number}
                                                    </span>
                                                    {item.label}
                                                </span>
                                                <ArrowUpRight
                                                    className={`size-4 ${isActive ? 'text-[#0A0C0B]' : 'text-transparent'}`}
                                                />
                                            </button>
                                        );
                                    })}
                                </div>

                                <div
                                    id={`audience-panel-${activeAudience}`}
                                    role="tabpanel"
                                    aria-labelledby={`audience-tab-${activeAudience}`}
                                    className="grid gap-12 py-12 sm:py-16 lg:grid-cols-[0.9fr_1.1fr] lg:gap-24 lg:py-24"
                                >
                                    <div>
                                        <div className="flex items-center gap-4">
                                            <AudienceIcon className="size-6" />
                                            <span className="text-3xs font-bold tracking-[0.18em] text-[#52605A] uppercase">
                                                {audience.label}
                                            </span>
                                        </div>
                                        <h3 className="mt-7 max-w-xl text-[clamp(2.3rem,4.5vw,5rem)] leading-[0.9] font-bold tracking-[-0.08em]">
                                            {audience.title}
                                        </h3>
                                        <p className="mt-7 max-w-lg text-base leading-7 text-[#52605A]">
                                            {audience.description}
                                        </p>
                                    </div>
                                    <div className="grid content-start gap-0 border-t border-[#0A0C0B]/25">
                                        {audience.details.map(
                                            (detail, index) => (
                                                <div
                                                    key={detail}
                                                    className="flex items-center justify-between gap-4 border-b border-[#0A0C0B]/20 py-5"
                                                >
                                                    <span className="text-3xs font-bold tracking-[0.18em] text-[#87918F]">
                                                        0{index + 1}
                                                    </span>
                                                    <span className="flex-1 text-lg font-bold tracking-[-0.03em]">
                                                        {detail}
                                                    </span>
                                                    <Check className="size-5 text-[#52605A]" />
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="bg-[#0A0C0B] text-[#F2EFE7]">
                        <div className="mx-auto max-w-[1440px] px-5 py-24 sm:px-8 sm:py-32 lg:px-12 lg:py-40">
                            <div className="grid gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:gap-24">
                                <div>
                                    <Kicker light>Inventário do sistema</Kicker>
                                    <h2 className="mt-7 max-w-xl text-[clamp(3rem,5vw,5.8rem)] leading-[0.86] font-bold tracking-[-0.08em]">
                                        A rotina inteira, sem enfeite.
                                    </h2>
                                    <p className="mt-8 max-w-sm text-base leading-7 text-[#A9A79D]">
                                        Ferramentas para operar o que você já
                                        faz, com a organização que permite fazer
                                        melhor.
                                    </p>
                                </div>
                                <div className="grid gap-0 border-t border-white/20 sm:grid-cols-3 sm:border-t-0">
                                    {capabilityGroups.map((group) => {
                                        const GroupIcon = group.icon;

                                        return (
                                            <div
                                                key={group.title}
                                                className="border-b border-white/20 py-7 sm:border-t sm:py-6 sm:pr-6 sm:pl-6 sm:not-last:border-r sm:first:border-l-0 sm:first:pl-0 sm:last:pr-0"
                                            >
                                                <div className="flex items-center justify-between gap-4">
                                                    <span className="text-3xs font-bold tracking-[0.18em] text-[#C8FF3D]">
                                                        {group.index}
                                                    </span>
                                                    <GroupIcon className="size-5 text-[#C8FF3D]" />
                                                </div>
                                                <h3 className="mt-12 text-xl font-bold tracking-[-0.04em]">
                                                    {group.title}
                                                </h3>
                                                <ul className="mt-6 space-y-3">
                                                    {group.items.map((item) => (
                                                        <li
                                                            key={item}
                                                            className="flex items-start gap-3 text-sm leading-5 text-[#A9A79D]"
                                                        >
                                                            <span
                                                                className="mt-2 size-1 shrink-0 rounded-full bg-[#C8FF3D]"
                                                                aria-hidden="true"
                                                            />
                                                            {item}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="duvidas"
                        className="scroll-mt-8 bg-[#F2EFE7] text-[#0A0C0B]"
                    >
                        <div className="mx-auto grid max-w-[1440px] gap-12 px-5 py-24 sm:px-8 sm:py-32 lg:grid-cols-[0.75fr_1.25fr] lg:gap-24 lg:px-12 lg:py-40">
                            <div>
                                <Kicker>Antes de começar</Kicker>
                                <h2 className="mt-7 max-w-md text-[clamp(3rem,5vw,5.8rem)] leading-[0.86] font-bold tracking-[-0.08em]">
                                    Dúvidas honestas. Respostas diretas.
                                </h2>
                            </div>
                            <div className="border-t border-[#0A0C0B]/25">
                                {faqs.map((faq, index) => {
                                    const isOpen = openFaq === index;
                                    const answerId = `faq-answer-${index}`;

                                    return (
                                        <div
                                            key={faq.question}
                                            className="border-b border-[#0A0C0B]/25"
                                        >
                                            <button
                                                type="button"
                                                className={`flex w-full items-center justify-between gap-5 py-6 text-left text-base font-bold tracking-[-0.025em] transition hover:text-[#52605A] sm:py-7 sm:text-lg ${focusRingLight}`}
                                                aria-expanded={isOpen}
                                                aria-controls={answerId}
                                                onClick={() =>
                                                    setOpenFaq(
                                                        isOpen ? null : index,
                                                    )
                                                }
                                            >
                                                <span>{faq.question}</span>
                                                <ChevronDown
                                                    className={`size-5 shrink-0 transition-transform duration-300 ${isOpen ? 'rotate-180' : ''}`}
                                                />
                                            </button>
                                            <div
                                                id={answerId}
                                                hidden={!isOpen}
                                                className="max-w-2xl pr-8 pb-7 text-sm leading-6 text-[#52605A]"
                                            >
                                                {faq.answer}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    <section className="relative overflow-hidden bg-[#C8FF3D] text-[#0A0C0B]">
                        <LineMark className="top-1/2 right-0 left-0 opacity-20" />
                        <div className="relative mx-auto grid max-w-[1440px] gap-12 px-5 py-24 sm:px-8 sm:py-32 lg:grid-cols-[1.2fr_0.8fr] lg:items-end lg:gap-20 lg:px-12 lg:py-40">
                            <div>
                                <Kicker>
                                    O próximo nível começa na rotina
                                </Kicker>
                                <h2 className="mt-7 max-w-5xl text-[clamp(3.5rem,8vw,9rem)] leading-[0.8] font-bold tracking-[-0.09em]">
                                    Faça a gestão acompanhar o espaço que você
                                    construiu.
                                </h2>
                            </div>
                            <div className="lg:pb-2">
                                <p className="max-w-sm text-base leading-7 text-[#34402B]">
                                    Comece pelo essencial. Organize o que
                                    acontece todos os dias. Cresça com mais
                                    clareza.
                                </p>
                                <Link
                                    className={`mt-8 inline-flex items-center gap-3 bg-[#0A0C0B] px-6 py-4 text-sm font-bold text-[#F2EFE7] transition hover:bg-[#F2EFE7] hover:text-[#0A0C0B] ${focusRingLight}`}
                                    href={registerHref}
                                >
                                    Testar grátis por 14 dias{' '}
                                    <ArrowUpRight className="size-4" />
                                </Link>
                                <p className="mt-4 text-3xs font-bold tracking-[0.12em] text-[#52605A] uppercase">
                                    14 dias para testar. Sem cartão.
                                </p>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-white/15 bg-[#0A0C0B] py-8 text-[#A9A79D]">
                    <div className="mx-auto flex max-w-[1440px] flex-col gap-7 px-5 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-12">
                        <BrandMark
                            branding={{ ...branding, name: brandName }}
                            light
                            compact
                        />
                        <div className="flex flex-wrap items-center gap-x-6 gap-y-3 text-3xs font-bold tracking-[0.1em] uppercase">
                            <a
                                className={`transition-colors hover:text-[#C8FF3D] ${focusRingDark}`}
                                href="#sistema"
                            >
                                Sistema
                            </a>
                            <a
                                className={`transition-colors hover:text-[#C8FF3D] ${focusRingDark}`}
                                href="#para-quem"
                            >
                                Para quem
                            </a>
                            <a
                                className={`transition-colors hover:text-[#C8FF3D] ${focusRingDark}`}
                                href="#duvidas"
                            >
                                Dúvidas
                            </a>
                            <Link
                                className={`transition-colors hover:text-[#C8FF3D] ${focusRingDark}`}
                                href={login()}
                            >
                                Entrar
                            </Link>
                            <span>
                                <span suppressHydrationWarning>
                                    © {new Date().getFullYear()} {brandName}
                                </span>
                            </span>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}

export default Home;
