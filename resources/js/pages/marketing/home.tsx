import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Boxes,
    Building2,
    CalendarCheck,
    CalendarDays,
    Check,
    CheckCircle2,
    ChevronDown,
    Clock,
    DollarSign,
    HeartHandshake,
    HelpCircle,
    Lock,
    Menu,
    MessageCircleHeart,
    PackageCheck,
    Receipt,
    Repeat,
    ShieldCheck,
    Smartphone,
    Sparkles,
    Store,
    User,
    UserMinus,
    Users,
    UserX,
    Wallet,
    X,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { login, register } from '@/routes';
import type { Branding } from '@/types/ui';

interface HomeProps {
    branding: Branding;
}

export default function Home({ branding }: HomeProps) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [activeFeature, setActiveFeature] = useState(0);
    const [openFaq, setOpenFaq] = useState<number | null>(0);

    const brandName = branding?.name || 'Caldas Gestão';

    const features = [
        {
            id: 0,
            icon: CalendarDays,
            badge: 'Agilidade & Zero Conflitos',
            title: 'Agenda Inteligente no Celular',
            description:
                'Veja o dia inteiro de um jeito fácil. Visualize por profissional, controle pausas de almoço, folgas e encaixes sem nenhum risco de marcar duas pessoas na mesma cadeira ao mesmo tempo.',
            preview: {
                title: 'Agenda do Dia • 8 Horários Marcados',
                subtitle: 'Sincronizado em tempo real com o Google Agenda',
                items: [
                    {
                        time: '09:00',
                        client: 'Juliana Mendes',
                        service: 'Corte e Escova',
                        staff: 'Camila',
                        status: 'Confirmado',
                    },
                    {
                        time: '10:30',
                        client: 'Larissa Costa',
                        service: 'Mechas & Nutrição',
                        staff: 'Camila',
                        status: 'Em atendimento',
                    },
                    {
                        time: '13:00',
                        client: 'Beatriz Lima',
                        service: 'Manicure Spa',
                        staff: 'Fernanda',
                        status: 'Lembrete enviado',
                    },
                ],
            },
        },
        {
            id: 1,
            icon: Receipt,
            badge: 'Rapidez no Balcão',
            title: 'Comandas Simples e Rápidas',
            description:
                'Junte serviços e produtos na mesma comanda. Finalize no dinheiro, cartão ou PIX em poucos cliques e tenha o histórico completo de cada cliente guardado com segurança.',
            preview: {
                title: 'Comanda #1042 • Roberta Vasconcelos',
                subtitle: 'Serviços + Produtos somados automaticamente',
                items: [
                    {
                        time: 'Serviço',
                        client: 'Design de Sobrancelha',
                        service: 'R$ 75,00',
                        staff: 'Prof: Amanda',
                        status: 'Concluído',
                    },
                    {
                        time: 'Produto',
                        client: 'Óleo Reparador 60ml',
                        service: 'R$ 85,00',
                        staff: 'Uso & Venda',
                        status: 'Estoque baixado',
                    },
                    {
                        time: 'Total',
                        client: 'R$ 160,00 no PIX',
                        service: 'Comissão calculada',
                        staff: 'Amanda: R$ 37,50',
                        status: 'Pago',
                    },
                ],
            },
        },
        {
            id: 2,
            icon: PackageCheck,
            badge: 'Previsibilidade de Receita',
            title: 'Planos Mensais e Pacotes de Atendimento',
            description:
                'Crie pacotes promocionais e assinaturas mensais para fidelizar quem frequenta seu espaço. Tenha previsibilidade financeira real para pagar as contas com tranquilidade.',
            preview: {
                title: 'Clube de Assinaturas & Pacotes Ativos',
                subtitle: 'Receita garantida no dia 1º de cada mês',
                items: [
                    {
                        time: 'Assinatura',
                        client: 'Plano VIP Cabelo Toda Semana',
                        service: 'R$ 290/mês',
                        staff: '18 assinantes ativos',
                        status: 'Renovado',
                    },
                    {
                        time: 'Pacote',
                        client: 'Combo 4 Sessões Estética Corporal',
                        service: 'R$ 480 pago adiantado',
                        staff: '2/4 sessões feitas',
                        status: 'Ativo',
                    },
                    {
                        time: 'Fidelização',
                        client: 'Retenção média de 8 meses',
                        service: 'Garantia de faturamento',
                        staff: 'Zero furos',
                        status: 'Recorrente',
                    },
                ],
            },
        },
        {
            id: 3,
            icon: Wallet,
            badge: 'Dinheiro na Mão Sem Faltar Nada',
            title: 'Controle Completo do Caixa',
            description:
                'Abra o caixa pela manhã, registre entradas, saídas de troco ou pagamentos rápidos e feche o dia em segundos. Saiba exatamente quanto dinheiro está na sua mão sem faltar um centavo.',
            preview: {
                title: 'Fechamento Diário • Sexta-Feira',
                subtitle: 'Tudo discriminado por forma de pagamento',
                items: [
                    {
                        time: 'Entradas',
                        client: 'Cartões (Débito e Crédito)',
                        service: 'R$ 2.450,00',
                        staff: 'Conciliação direta',
                        status: 'Conferido',
                    },
                    {
                        time: 'Instantâneo',
                        client: 'PIX QR Code Direto',
                        service: 'R$ 1.830,00',
                        staff: 'Comprovantes salvos',
                        status: 'Na conta',
                    },
                    {
                        time: 'Gaveta',
                        client: 'Dinheiro em Espécie',
                        service: 'R$ 640,00',
                        staff: 'Troco inicial: R$ 100,00',
                        status: '100% batido',
                    },
                ],
            },
        },
        {
            id: 4,
            icon: Boxes,
            badge: 'Fim do Prejuízo Invisível',
            title: 'Estoque sob Controle',
            description:
                'Saiba o que está acabando na prateleira antes de faltar durante o atendimento. Separe o que é para uso dos profissionais e o que é para venda no balcão. Fim dos produtos perdidos.',
            preview: {
                title: 'Gestão de Materiais & Revenda',
                subtitle: 'Alertas automáticos de reposição de itens',
                items: [
                    {
                        time: 'Alerta',
                        client: 'Tonalizante Castanho 6.0',
                        service: '2 un restantes',
                        staff: 'Consumo interno',
                        status: 'Pedir hoje',
                    },
                    {
                        time: 'Revenda',
                        client: 'Shampoo Pós-Química 300ml',
                        service: '11 un em estoque',
                        staff: 'Prateleira de vendas',
                        status: 'Estoque OK',
                    },
                    {
                        time: 'Economia',
                        client: 'Zero desperdício de insumos',
                        service: 'Baixa automática',
                        staff: 'Custo por atendimento',
                        status: 'Controlado',
                    },
                ],
            },
        },
        {
            id: 5,
            icon: MessageCircleHeart,
            badge: 'Recupere Clientes no Piloto Automático',
            title: 'Aviso Automático para Quem Sumiu Voltar',
            description:
                'O sistema mostra quem é cliente do seu espaço mas não agenda nada há mais de 30 ou 60 dias. Com um clique, você envia uma mensagem carinhosa convidando a pessoa para voltar antes que ela vá para outro lugar.',
            preview: {
                title: 'Radar de Retenção de Clientes',
                subtitle: 'Disparo facilitado no WhatsApp com 1 clique',
                items: [
                    {
                        time: 'Há 45 dias',
                        client: 'Mariana Duarte',
                        service: 'Último serviço: Mechas',
                        staff: 'Mensagem carinhosa pronta',
                        status: 'Convidar',
                    },
                    {
                        time: 'Há 35 dias',
                        client: 'Carla Silveira',
                        service: 'Último serviço: Hidratação',
                        staff: 'Cupom de boas-vindas',
                        status: 'Reagendou!',
                    },
                    {
                        time: 'Resultado',
                        client: '14 clientes recuperados no mês',
                        service: '+ R$ 2.180 faturados',
                        staff: 'Sem gastar com anúncio',
                        status: 'Sucesso',
                    },
                ],
            },
        },
    ];

    const faqs = [
        {
            q: 'Eu não entendo muito de tecnologia ou computador. Vou conseguir mexer?',
            a: 'Sim, com certeza! O Caldas Gestão foi desenhado justamente para quem não é da área de tecnologia. As telas são limpas, com botões fáceis e palavras que você já usa no dia a dia. Se você sabe usar o WhatsApp e o Instagram, você vai aprender a mexer em menos de 10 minutos.',
        },
        {
            q: 'O meu cliente precisa baixar algum aplicativo pesado na loja do celular?',
            a: 'Não! Esse é um dos maiores diferenciais. O cliente não precisa ir na loja de aplicativos, criar senhas difíceis ou ocupar espaço na memória do celular dele. Ele simplesmente clica no link do seu espaço pelo WhatsApp ou Instagram e o agendamento abre na hora direto no navegador.',
        },
        {
            q: 'Como funciona esse negócio de "meu próprio site na internet"?',
            a: 'Você ganha um endereço exclusivo na internet com a sua cara. Nele aparecem a logo do seu espaço, as fotos do seu ambiente, a lista dos seus serviços com preços, os nomes dos profissionais e o botão de agendar. O cliente se sente atendido por um espaço profissional de alto padrão.',
        },
        {
            q: 'Meus profissionais vão conseguir ver os horários deles no próprio celular?',
            a: 'Sim! O sistema se conecta diretamente ao Google Agenda, que já vem instalado no celular da sua equipe. Quando alguém agenda um horário no seu site, o compromisso aparece no celular do profissional responsável na mesma hora.',
        },
        {
            q: 'Como funcionam os planos mensais e os pacotes de serviços?',
            a: 'Você pode criar opções para o cliente pagar um valor fixo por mês (por exemplo: atendimento toda semana ou corte ilimitado) ou comprar um pacote de 4 ou 8 sessões com desconto. Isso faz com que você receba o dinheiro adiantado e garante que aquele cliente não vá em outro lugar.',
        },
        {
            q: 'Eu preciso colocar meu cartão de crédito para fazer o teste?',
            a: 'De jeito nenhum. Você pode começar o seu cadastro agora mesmo, sem digitar nenhum número de cartão e sem nenhum compromisso. Você experimenta no seu dia a dia e só decide continuar se realmente gostar do resultado.',
        },
        {
            q: 'E se eu tiver qualquer dúvida ou dificuldade na hora de usar?',
            a: 'Você não fica sozinho(a). Nossa equipe de suporte está à disposição para te ajudar a cadastrar seus primeiros serviços, sua equipe e tirar qualquer dúvida com carinho e rapidez.',
        },
        {
            q: 'Posso começar sozinho e depois cadastrar meus funcionários?',
            a: 'Claro! Muitos começam com um único profissional (o próprio dono) e, conforme o espaço vai crescendo e contratando colaboradores, basta adicionar os novos membros da equipe no painel. O sistema cresce junto com você.',
        },
    ];

    const toggleFaq = (index: number) => {
        setOpenFaq(openFaq === index ? null : index);
    };

    return (
        <>
            <Head title="Caldas Gestão — Sistema de Gestão e Agendamento para Espaços de Beleza e Estética">
                <meta
                    name="description"
                    content="Tenha seu próprio site de agendamento na internet, acabe com os furos de horário e coloque ordem no seu dinheiro. Simples, rápido e feito para salões, barbearias e clínicas."
                />
            </Head>

            <div
                id="top"
                className="min-h-screen bg-[#f8f6f0] text-[#17252a] selection:bg-[#efa83f]/30 selection:text-[#17252a]"
            >
                {/* =========================================================
                    HEADER & NAVEGAÇÃO
                ========================================================= */}
                <header className="sticky top-0 z-50 border-b border-[#e8e2d8] bg-[#f8f6f0]/90 backdrop-blur-md transition-all">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
                        {/* Logo */}
                        <a
                            href="#top"
                            className="flex items-center gap-3 transition-opacity hover:opacity-90"
                        >
                            {branding?.logoUrl ? (
                                <img
                                    src={branding.logoUrl}
                                    alt={brandName}
                                    className="size-10 rounded-xl object-contain shadow-xs"
                                />
                            ) : (
                                <div className="flex size-10 items-center justify-center rounded-xl bg-[#3167d8] text-white shadow-xs">
                                    <AppLogoIcon className="size-6 text-white" />
                                </div>
                            )}
                            <div className="flex flex-col">
                                <span className="font-display text-lg font-bold tracking-tight text-[#17252a]">
                                    {brandName}
                                </span>
                                <span className="text-[10px] font-semibold tracking-wider text-[#3167d8] uppercase">
                                    Beleza & Estética
                                </span>
                            </div>
                        </a>

                        {/* Menu Desktop */}
                        <nav className="hidden items-center gap-8 md:flex">
                            <a
                                href="#o-erro-na-bio"
                                className="text-sm font-medium text-[#4f5d59] transition-colors hover:text-[#17252a]"
                            >
                                O Erro na Bio
                            </a>
                            <a
                                href="#como-funciona"
                                className="text-sm font-medium text-[#4f5d59] transition-colors hover:text-[#17252a]"
                            >
                                Como Funciona
                            </a>
                            <a
                                href="#recursos"
                                className="text-sm font-medium text-[#4f5d59] transition-colors hover:text-[#17252a]"
                            >
                                Recursos
                            </a>
                            <a
                                href="#para-quem-e"
                                className="text-sm font-medium text-[#4f5d59] transition-colors hover:text-[#17252a]"
                            >
                                Para Quem É
                            </a>
                            <a
                                href="#faq"
                                className="text-sm font-medium text-[#4f5d59] transition-colors hover:text-[#17252a]"
                            >
                                Dúvidas
                            </a>
                        </nav>

                        {/* Ações / CTAs Header */}
                        <div className="hidden items-center gap-3 md:flex">
                            <Link
                                href={login()}
                                className="rounded-xl px-4 py-2 text-sm font-semibold text-[#17252a] transition hover:bg-black/5"
                            >
                                Entrar
                            </Link>
                            <Link
                                href={register()}
                                className="inline-flex items-center gap-2 rounded-xl bg-[#3167d8] px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#3167d8]/20 transition-all hover:bg-[#2551b3] hover:shadow-lg active:scale-98"
                            >
                                Começar Grátis
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>

                        {/* Botão Mobile */}
                        <button
                            type="button"
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="inline-flex items-center justify-center rounded-xl border border-[#e8e2d8] bg-white p-2 text-[#17252a] md:hidden"
                            aria-label="Abrir menu"
                        >
                            {mobileMenuOpen ? (
                                <X className="size-6" />
                            ) : (
                                <Menu className="size-6" />
                            )}
                        </button>
                    </div>

                    {/* Drawer Mobile */}
                    {mobileMenuOpen && (
                        <div className="border-b border-[#e8e2d8] bg-[#f8f6f0] px-4 pt-3 pb-6 md:hidden">
                            <nav className="flex flex-col gap-3">
                                <a
                                    href="#o-erro-na-bio"
                                    onClick={() => setMobileMenuOpen(false)}
                                    className="rounded-lg px-3 py-2 text-base font-medium text-[#17252a] hover:bg-black/5"
                                >
                                    O Erro na Bio
                                </a>
                                <a
                                    href="#como-funciona"
                                    onClick={() => setMobileMenuOpen(false)}
                                    className="rounded-lg px-3 py-2 text-base font-medium text-[#17252a] hover:bg-black/5"
                                >
                                    Como Funciona
                                </a>
                                <a
                                    href="#recursos"
                                    onClick={() => setMobileMenuOpen(false)}
                                    className="rounded-lg px-3 py-2 text-base font-medium text-[#17252a] hover:bg-black/5"
                                >
                                    Recursos
                                </a>
                                <a
                                    href="#para-quem-e"
                                    onClick={() => setMobileMenuOpen(false)}
                                    className="rounded-lg px-3 py-2 text-base font-medium text-[#17252a] hover:bg-black/5"
                                >
                                    Para Quem É
                                </a>
                                <a
                                    href="#faq"
                                    onClick={() => setMobileMenuOpen(false)}
                                    className="rounded-lg px-3 py-2 text-base font-medium text-[#17252a] hover:bg-black/5"
                                >
                                    Dúvidas Frequentes
                                </a>
                                <div className="mt-2 flex flex-col gap-2 border-t border-[#e8e2d8] pt-4">
                                    <Link
                                        href={login()}
                                        className="flex w-full items-center justify-center rounded-xl border border-[#dcd7cf] bg-white px-4 py-3 font-semibold text-[#17252a]"
                                    >
                                        Entrar na Minha Conta
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="flex w-full items-center justify-center gap-2 rounded-xl bg-[#3167d8] px-4 py-3 font-semibold text-white shadow-md"
                                    >
                                        Começar Grátis Agora
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </div>
                            </nav>
                        </div>
                    )}
                </header>

                <main>
                    {/* =========================================================
                        DOBRA 1: HERO SECTION (A Primeira Impressão)
                    ========================================================= */}
                    <section className="relative overflow-hidden pt-12 pb-20 sm:pt-16 sm:pb-28 lg:pt-20 lg:pb-32">
                        {/* Fundos decorativos sutis */}
                        <div className="pointer-events-none absolute -top-40 right-1/2 -z-10 h-[520px] w-[520px] translate-x-1/2 rounded-full bg-linear-to-br from-[#efa83f]/20 via-[#3167d8]/15 to-transparent blur-3xl" />
                        <div className="pointer-events-none absolute top-1/3 -right-24 -z-10 h-[380px] w-[380px] rounded-full bg-[#f2c1a7]/30 blur-3xl" />

                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="grid items-center gap-12 lg:grid-cols-12 lg:gap-14">
                                {/* Coluna Texto & Ação */}
                                <div className="text-center lg:col-span-7 lg:text-left">
                                    {/* Etiqueta Superior (Badge) */}
                                    <div className="inline-flex items-center gap-2 rounded-full border border-[#ded5ca] bg-white/80 px-4 py-2 text-xs font-semibold text-[#214fae] shadow-xs backdrop-blur-xs sm:text-sm">
                                        <Sparkles className="size-4 text-[#efa83f]" />
                                        <span>
                                            ✨ A ferramenta completa para o seu
                                            espaço de beleza e estética
                                        </span>
                                    </div>

                                    {/* Título Principal (H1) */}
                                    <h1 className="mt-6 font-display text-4xl font-extrabold tracking-tight text-[#17252a] sm:text-5xl lg:text-6xl lg:leading-[1.12]">
                                        Tenha seu próprio site de agendamento na
                                        internet, acabe com os furos de horário
                                        e coloque ordem no seu dinheiro.
                                    </h1>

                                    {/* Subtítulo */}
                                    <p className="mt-6 text-lg leading-relaxed text-[#525f5a] sm:text-xl sm:leading-8">
                                        Tudo o que você precisa para gerenciar
                                        seus clientes, sua equipe e seu caixa em
                                        um só lugar. Seus clientes agendam
                                        sozinhos 24 horas por dia, sua equipe vê
                                        os horários direto no celular e você
                                        nunca mais passa sufoco fechando
                                        comissões na sexta-feira.
                                    </p>

                                    {/* 3 Pontos Rápidos de Benefício */}
                                    <ul className="mt-8 space-y-3 text-left sm:space-y-3.5">
                                        <li className="flex items-start gap-3 text-base text-[#17252a]">
                                            <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                                <Check className="size-3.5 stroke-[3]" />
                                            </span>
                                            <span>
                                                <strong className="font-semibold text-[#17252a]">
                                                    Seu próprio site na
                                                    internet:
                                                </strong>{' '}
                                                com o seu nome e a sua logo, sem
                                                divulgar concorrentes.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-base text-[#17252a]">
                                            <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                                <Check className="size-3.5 stroke-[3]" />
                                            </span>
                                            <span>
                                                <strong className="font-semibold text-[#17252a]">
                                                    Agenda no celular e fim dos
                                                    furos de horário:
                                                </strong>{' '}
                                                sincronizada com o Google Agenda
                                                e com lembretes automáticos para
                                                os clientes.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-base text-[#17252a]">
                                            <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                                <Check className="size-3.5 stroke-[3]" />
                                            </span>
                                            <span>
                                                <strong className="font-semibold text-[#17252a]">
                                                    Dinheiro garantido todo mês
                                                    com mensalidades e pacotes:
                                                </strong>{' '}
                                                tenha faturamento certo logo no
                                                dia 1º.
                                            </span>
                                        </li>
                                    </ul>

                                    {/* Botão Principal de Ação (CTA) */}
                                    <div className="mt-10 flex flex-col items-center gap-4 sm:flex-row lg:items-start">
                                        <Link
                                            href={register()}
                                            className="group flex w-full items-center justify-center gap-3 rounded-2xl bg-[#3167d8] px-8 py-4 text-center text-lg font-bold text-white shadow-xl shadow-[#3167d8]/25 transition-all hover:bg-[#2551b3] hover:shadow-2xl hover:shadow-[#3167d8]/35 active:scale-98 sm:w-auto"
                                        >
                                            <span>
                                                👉 Quero experimentar no meu
                                                espaço agora
                                            </span>
                                            <ArrowRight className="size-5 transition-transform group-hover:translate-x-1" />
                                        </Link>
                                    </div>

                                    {/* Micro-chamada de Confiança */}
                                    <p className="mt-4 flex items-center justify-center gap-2 text-sm font-medium text-[#65726e] lg:justify-start">
                                        <Lock className="size-4 text-emerald-600" />
                                        <span>
                                            🔒 Não precisa de cartão de crédito.
                                            Leva menos de 2 minutos para
                                            começar.
                                        </span>
                                    </p>
                                </div>

                                {/* Coluna Demonstração Visual / Mockup */}
                                <div className="lg:col-span-5">
                                    <div className="relative mx-auto max-w-md">
                                        {/* Mockup Card Celular / Site Próprio */}
                                        <div className="relative rounded-3xl border border-[#ded5ca] bg-white p-5 shadow-2xl shadow-[#17252a]/10 sm:p-6">
                                            {/* Header do Mockup */}
                                            <div className="flex items-center justify-between border-b border-[#f0eae0] pb-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex size-11 items-center justify-center rounded-2xl bg-[#17252a] text-white">
                                                        <Sparkles className="size-6 text-[#efa83f]" />
                                                    </div>
                                                    <div>
                                                        <p className="text-xs font-semibold tracking-wider text-[#3167d8] uppercase">
                                                            Site Próprio
                                                            Exclusivo
                                                        </p>
                                                        <p className="font-display text-base font-bold text-[#17252a]">
                                                            Studio Bella &
                                                            Estética
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
                                                    Online 24h
                                                </span>
                                            </div>

                                            {/* Preview: Agendamento Simples */}
                                            <div className="mt-4 space-y-3">
                                                <div className="rounded-2xl border border-[#e8e2d8] bg-[#f8f6f0] p-3.5">
                                                    <p className="text-xs font-bold tracking-wider text-[#65726e] uppercase">
                                                        1. Escolha o serviço
                                                    </p>
                                                    <div className="mt-2 flex items-center justify-between rounded-xl border border-[#ded5ca] bg-white p-2.5 shadow-xs">
                                                        <div>
                                                            <p className="text-sm font-bold text-[#17252a]">
                                                                Corte & Escova
                                                                Modelada
                                                            </p>
                                                            <p className="text-xs text-[#717d79]">
                                                                50 min • Com
                                                                lavagem especial
                                                            </p>
                                                        </div>
                                                        <span className="text-sm font-bold text-[#3167d8]">
                                                            R$ 130
                                                        </span>
                                                    </div>
                                                </div>

                                                <div className="rounded-2xl border border-[#e8e2d8] bg-[#f8f6f0] p-3.5">
                                                    <p className="text-xs font-bold tracking-wider text-[#65726e] uppercase">
                                                        2. Profissional &
                                                        Horário
                                                    </p>
                                                    <div className="mt-2 grid grid-cols-3 gap-2">
                                                        <div className="rounded-xl border border-[#3167d8] bg-[#edf2ff] p-2 text-center text-xs font-bold text-[#214fae]">
                                                            14:30
                                                        </div>
                                                        <div className="rounded-xl border border-[#ded5ca] bg-white p-2 text-center text-xs font-medium text-[#65726e]">
                                                            16:00
                                                        </div>
                                                        <div className="rounded-xl border border-[#ded5ca] bg-white p-2 text-center text-xs font-medium text-[#65726e]">
                                                            17:15
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Confirmação Instantânea */}
                                                <div className="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-3.5">
                                                    <div className="flex items-center gap-2.5">
                                                        <div className="flex size-7 items-center justify-center rounded-full bg-emerald-600 text-white">
                                                            <Check className="size-4" />
                                                        </div>
                                                        <div>
                                                            <p className="text-xs font-bold text-emerald-900">
                                                                Agendado com
                                                                Sucesso!
                                                            </p>
                                                            <p className="text-[11px] text-emerald-800">
                                                                Lembrete
                                                                automático
                                                                enviado no
                                                                WhatsApp
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Selo Flutuante: Google Agenda */}
                                            <div className="mt-4 flex items-center justify-between rounded-xl bg-[#17252a] p-3 text-white">
                                                <div className="flex items-center gap-2.5">
                                                    <CalendarCheck className="size-5 text-[#efa83f]" />
                                                    <div>
                                                        <p className="text-xs font-semibold">
                                                            Google Agenda
                                                            Integrada
                                                        </p>
                                                        <p className="text-[10px] text-gray-300">
                                                            Horário salvo no
                                                            celular da equipe
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className="rounded-md bg-white/20 px-2 py-0.5 text-[10px] font-bold text-white">
                                                    Automático
                                                </span>
                                            </div>
                                        </div>

                                        {/* Card Flutuante de Receita Previsível */}
                                        <div className="mt-4 rounded-2xl border border-[#e8e2d8] bg-white p-4 shadow-xl">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-800">
                                                        <DollarSign className="size-5" />
                                                    </div>
                                                    <div>
                                                        <p className="text-xs font-medium text-[#65726e]">
                                                            Faturamento no Dia
                                                            1º
                                                        </p>
                                                        <p className="text-lg font-bold text-[#17252a]">
                                                            R$ 4.280,00
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">
                                                    26 Pacotes Ativos
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* =========================================================
                        DOBRA 2: A QUEBRA DE PARADIGMA (O erro na bio do Instagram)
                    ========================================================= */}
                    <section
                        id="o-erro-na-bio"
                        className="border-y border-[#ded5ca] bg-white/60 py-20 lg:py-28"
                    >
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3.5 py-1.5 text-xs font-bold text-amber-900">
                                    <AlertTriangle className="size-4 text-amber-700" />
                                    ⚠️ Você já parou para pensar nisso?
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-3xl font-extrabold tracking-tight text-[#17252a] sm:text-4xl lg:text-5xl">
                                    O erro que a maioria comete ao colocar links
                                    de outros aplicativos na bio do seu
                                    Instagram.
                                </h2>

                                {/* Texto de Apoio */}
                                <div className="mt-6 space-y-4 text-base leading-relaxed text-[#525f5a] sm:text-lg">
                                    <p>
                                        Você passa a semana inteira postando
                                        fotos, gravando vídeos, pagando anúncios
                                        e se esforçando para atrair clientes.
                                        Aí, quando a pessoa finalmente se
                                        interessa pelo seu trabalho e clica no
                                        link do seu perfil...
                                    </p>
                                    <p>
                                        Ela cai em um aplicativo cheio de outros
                                        salões, barbearias e clínicas da sua
                                        mesma cidade, inclusive com promoções
                                        dos seus concorrentes na mesma tela.
                                    </p>
                                    <p className="rounded-2xl border border-red-200 bg-red-50/80 p-4 font-semibold text-red-900">
                                        Sem perceber,{' '}
                                        <span className="underline decoration-red-400 underline-offset-4">
                                            você pagou o marketing para mandar
                                            seu cliente para a concorrência.
                                        </span>
                                    </p>
                                </div>
                            </div>

                            {/* Comparativo Visual Lado a Lado */}
                            <div className="mt-14 grid gap-8 md:grid-cols-2">
                                {/* O Jeito Antigo */}
                                <div className="relative rounded-3xl border border-red-200 bg-white p-6 shadow-sm sm:p-8">
                                    <div className="flex items-center justify-between">
                                        <span className="inline-flex items-center gap-2 rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-800">
                                            <XCircle className="size-4" />O
                                            Jeito Antigo (Apps agregadores)
                                        </span>
                                        <span className="text-xs font-semibold text-red-600">
                                            Perda de Clientes
                                        </span>
                                    </div>
                                    <h3 className="mt-4 text-xl font-bold text-[#17252a]">
                                        O cliente se perde e vê seus
                                        concorrentes
                                    </h3>
                                    <ul className="mt-5 space-y-3 text-sm text-[#65726e]">
                                        <li className="flex items-start gap-2.5">
                                            <X className="mt-0.5 size-4 shrink-0 text-red-600" />
                                            <span>
                                                Seu cliente vê promoções de
                                                concorrentes ao lado do seu
                                                preço.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-2.5">
                                            <X className="mt-0.5 size-4 shrink-0 text-red-600" />
                                            <span>
                                                Obrigam o cliente a baixar
                                                aplicativo e criar cadastro
                                                chato.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-2.5">
                                            <X className="mt-0.5 size-4 shrink-0 text-red-600" />
                                            <span>
                                                Cobram taxas pesadas sobre o
                                                dinheiro suado do seu trabalho.
                                            </span>
                                        </li>
                                    </ul>
                                </div>

                                {/* O Novo Padrão: Caldas Gestão */}
                                <div className="relative rounded-3xl border-2 border-[#3167d8] bg-linear-to-b from-[#edf2ff]/60 to-white p-6 shadow-xl shadow-[#3167d8]/10 sm:p-8">
                                    <div className="flex items-center justify-between">
                                        <span className="inline-flex items-center gap-2 rounded-full bg-[#3167d8] px-3 py-1 text-xs font-bold text-white">
                                            <CheckCircle2 className="size-4" />O
                                            Novo Padrão Caldas Gestão
                                        </span>
                                        <span className="text-xs font-bold text-[#214fae]">
                                            100% Exclusivo
                                        </span>
                                    </div>
                                    <h3 className="mt-4 text-xl font-bold text-[#17252a]">
                                        No Caldas Gestão, a estrela é o seu
                                        espaço
                                    </h3>
                                    <div className="mt-4 space-y-3 text-sm leading-relaxed text-[#525f5a]">
                                        <p>
                                            Quando o cliente clica no seu link,
                                            ele entra no{' '}
                                            <strong className="text-[#17252a]">
                                                seu próprio site na internet
                                            </strong>
                                            .
                                        </p>
                                        <p>
                                            Lá só existem os seus serviços, seus
                                            preços, suas fotos e a sua equipe.
                                            Sua marca ganha autoridade e você
                                            não divide a atenção de quem quer
                                            ser atendido por você.
                                        </p>
                                    </div>
                                    <div className="mt-5 rounded-2xl border border-[#c4d6ff] bg-white p-3.5">
                                        <p className="text-xs font-semibold text-[#214fae]">
                                            ✨ O cliente clica, escolhe o
                                            horário e agenda direto pelo
                                            navegador, sem instalar nada.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* =========================================================
                        DOBRA 3: VOCÊ SE IDENTIFICA COM ISSO? (Os 4 problemas)
                    ========================================================= */}
                    <section className="py-20 lg:py-28">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-[#e8e2d8] px-3.5 py-1.5 text-xs font-bold text-[#4b5552]">
                                    <Store className="size-4 text-[#3167d8]" />A
                                    vida real de quem tem um espaço
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-3xl font-extrabold tracking-tight text-[#17252a] sm:text-4xl lg:text-5xl">
                                    Quantas dessas 4 dores de cabeça você
                                    enfrentou só nesta última semana?
                                </h2>
                            </div>

                            {/* 4 Cards */}
                            <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                {/* Card 1 */}
                                <div className="flex flex-col justify-between rounded-3xl border border-[#ded5ca] bg-white p-6 shadow-sm transition hover:shadow-md">
                                    <div>
                                        <div className="flex size-12 items-center justify-center rounded-2xl bg-red-100 text-red-700">
                                            <UserX className="size-6" />
                                        </div>
                                        <h3 className="mt-5 text-lg font-bold text-[#17252a]">
                                            Card 1 — O cliente que fura e deixa
                                            a cadeira vazia
                                        </h3>
                                        <p className="mt-3 text-sm leading-relaxed text-[#525f5a]">
                                            <strong className="text-[#17252a]">
                                                O que acontece:
                                            </strong>{' '}
                                            Você reserva o horário, recusa
                                            outros clientes que queriam aquela
                                            vaga e a pessoa simplesmente não
                                            aparece. Pior: nem manda mensagem
                                            avisando. No final do dia, é
                                            dinheiro que sumiu da sua gaveta.
                                        </p>
                                    </div>
                                    <div className="mt-6 rounded-xl border border-red-100 bg-red-50 p-2.5 text-xs font-semibold text-red-800">
                                        Horário ocioso = Prejuízo direto
                                    </div>
                                </div>

                                {/* Card 2 */}
                                <div className="flex flex-col justify-between rounded-3xl border border-[#ded5ca] bg-white p-6 shadow-sm transition hover:shadow-md">
                                    <div>
                                        <div className="flex size-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-800">
                                            <Receipt className="size-6" />
                                        </div>
                                        <h3 className="mt-5 text-lg font-bold text-[#17252a]">
                                            Card 2 — O sufoco da sexta-feira na
                                            hora de fechar o caixa
                                        </h3>
                                        <p className="mt-3 text-sm leading-relaxed text-[#525f5a]">
                                            <strong className="text-[#17252a]">
                                                O que acontece:
                                            </strong>{' '}
                                            Papéis espalhados, comandas
                                            rasuradas com letra difícil de ler,
                                            mensagens perdidas no WhatsApp e
                                            calculadora na mão. No fim do
                                            expediente, ainda rola clima chato
                                            sobre quem atendeu o quê e quanto é
                                            a comissão de cada um.
                                        </p>
                                    </div>
                                    <div className="mt-6 rounded-xl border border-amber-100 bg-amber-50 p-2.5 text-xs font-semibold text-amber-800">
                                        Contas demoradas e atrito na equipe
                                    </div>
                                </div>

                                {/* Card 3 */}
                                <div className="flex flex-col justify-between rounded-3xl border border-[#ded5ca] bg-white p-6 shadow-sm transition hover:shadow-md">
                                    <div>
                                        <div className="flex size-12 items-center justify-center rounded-2xl bg-blue-100 text-[#214fae]">
                                            <DollarSign className="size-6" />
                                        </div>
                                        <h3 className="mt-5 text-lg font-bold text-[#17252a]">
                                            Card 3 — A renda montanha-russa todo
                                            início de mês
                                        </h3>
                                        <p className="mt-3 text-sm leading-relaxed text-[#525f5a]">
                                            <strong className="text-[#17252a]">
                                                O que acontece:
                                            </strong>{' '}
                                            Um mês é muito bom, o outro dá um
                                            aperto no peito porque as contas do
                                            espaço chegam no dia 10 e você não
                                            tem ideia de quanto vai faturar até
                                            lá. Viver na dependência de o
                                            cliente resolver aparecer é viver
                                            com ansiedade.
                                        </p>
                                    </div>
                                    <div className="mt-6 rounded-xl border border-blue-100 bg-blue-50 p-2.5 text-xs font-semibold text-[#214fae]">
                                        Falta de previsibilidade financeira
                                    </div>
                                </div>

                                {/* Card 4 */}
                                <div className="flex flex-col justify-between rounded-3xl border border-[#ded5ca] bg-white p-6 shadow-sm transition hover:shadow-md">
                                    <div>
                                        <div className="flex size-12 items-center justify-center rounded-2xl bg-rose-100 text-rose-700">
                                            <UserMinus className="size-6" />
                                        </div>
                                        <h3 className="mt-5 text-lg font-bold text-[#17252a]">
                                            Card 4 — O cliente que sumiu e
                                            ninguém percebeu
                                        </h3>
                                        <p className="mt-3 text-sm leading-relaxed text-[#525f5a]">
                                            <strong className="text-[#17252a]">
                                                O que acontece:
                                            </strong>{' '}
                                            Aquela pessoa vinha fielmente todo
                                            mês. Mas na correria dos
                                            atendimentos, ninguém reparou que
                                            ela já sumiu há mais de 40 dias.
                                            Quando você se dá conta, ela já se
                                            acostumou a ser atendida em outro
                                            lugar.
                                        </p>
                                    </div>
                                    <div className="mt-6 rounded-xl border border-rose-100 bg-rose-50 p-2.5 text-xs font-semibold text-rose-800">
                                        Clientes escapando em silêncio
                                    </div>
                                </div>
                            </div>

                            {/* Fechamento da Dobra (Callout Empático) */}
                            <div className="mx-auto mt-12 max-w-4xl rounded-3xl border border-[#ded5ca] bg-[#fffdf9] p-8 text-center shadow-md">
                                <p className="font-display text-lg leading-relaxed font-medium text-[#17252a] italic sm:text-xl">
                                    “A culpa não é sua. Você aprendeu a ser um
                                    excelente profissional na sua arte, mas
                                    ninguém te deu uma ferramenta simples para o
                                    seu negócio funcionar sem sugar sua paz.”
                                </p>
                            </div>
                        </div>
                    </section>

                    {/* =========================================================
                        DOBRA 4: COMO FUNCIONA A TRANSFORMAÇÃO (Os 4 Pilares)
                    ========================================================= */}
                    <section
                        id="como-funciona"
                        className="border-t border-[#ded5ca] bg-white/70 py-20 lg:py-28"
                    >
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-[#edf2ff] px-3.5 py-1.5 text-xs font-bold text-[#214fae]">
                                    <Sparkles className="size-4 text-[#3167d8]" />
                                    Como o Caldas Gestão muda a sua rotina
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-3xl font-extrabold tracking-tight text-[#17252a] sm:text-4xl lg:text-5xl">
                                    Quatro passos práticos para colocar ordem na
                                    casa e ter paz na gestão.
                                </h2>
                            </div>

                            {/* Grid dos 4 Pilares */}
                            <div className="mt-14 grid gap-8 md:grid-cols-2">
                                {/* Pilar 1 */}
                                <div className="relative rounded-3xl border border-[#ded5ca] bg-white p-7 shadow-sm transition hover:shadow-md sm:p-9">
                                    <div className="flex items-center justify-between">
                                        <span className="flex size-11 items-center justify-center rounded-2xl bg-[#17252a] text-lg font-bold text-white">
                                            01
                                        </span>
                                        <Smartphone className="size-6 text-[#3167d8]" />
                                    </div>
                                    <h3 className="mt-6 text-2xl font-bold text-[#17252a]">
                                        Pilar 1: Seu próprio site na internet
                                        com a sua marca
                                    </h3>
                                    <p className="mt-4 text-base leading-relaxed text-[#525f5a]">
                                        O seu cliente clica no link da sua bio
                                        do Instagram ou do WhatsApp e abre uma
                                        página linda com a sua logo, suas fotos
                                        e seus serviços. Ele escolhe o que quer
                                        fazer, com qual profissional quer ser
                                        atendido e qual horário prefere. Tudo
                                        rápido, sem precisar baixar aplicativo
                                        nenhum no celular e disponível 24 horas
                                        por dia.
                                    </p>
                                </div>

                                {/* Pilar 2 */}
                                <div className="relative rounded-3xl border border-[#ded5ca] bg-white p-7 shadow-sm transition hover:shadow-md sm:p-9">
                                    <div className="flex items-center justify-between">
                                        <span className="flex size-11 items-center justify-center rounded-2xl bg-[#3167d8] text-lg font-bold text-white">
                                            02
                                        </span>
                                        <CalendarCheck className="size-6 text-emerald-600" />
                                    </div>
                                    <h3 className="mt-6 text-2xl font-bold text-[#17252a]">
                                        Pilar 2: Agenda no celular de toda a
                                        equipe (sem furos)
                                    </h3>
                                    <p className="mt-4 text-base leading-relaxed text-[#525f5a]">
                                        Cada profissional da sua equipe recebe
                                        os agendamentos direto no calendário que
                                        já usa no próprio celular (Google
                                        Agenda). E o melhor: o sistema envia
                                        lembretes automáticos para o cliente
                                        antes do horário. O cliente lembra,
                                        confirma e o índice de pessoas que furam
                                        cai drasticamente.
                                    </p>
                                </div>

                                {/* Pilar 3 */}
                                <div className="relative rounded-3xl border border-[#ded5ca] bg-white p-7 shadow-sm transition hover:shadow-md sm:p-9">
                                    <div className="flex items-center justify-between">
                                        <span className="flex size-11 items-center justify-center rounded-2xl bg-[#efa83f] text-lg font-bold text-[#17252a]">
                                            03
                                        </span>
                                        <Repeat className="size-6 text-amber-700" />
                                    </div>
                                    <h3 className="mt-6 text-2xl font-bold text-[#17252a]">
                                        Pilar 3: Dinheiro garantido no dia 1º
                                        com planos mensais e pacotes
                                    </h3>
                                    <p className="mt-4 text-base leading-relaxed text-[#525f5a]">
                                        Chega de depender apenas de atendimentos
                                        avulsos. No Caldas Gestão você cria
                                        pacotes de sessões e planos de
                                        mensalidade (por exemplo: corte todo
                                        mês, manutenção quinzenal, tratamento
                                        estético contínuo). O cliente paga
                                        adiantado, você garante receita logo na
                                        virada do mês e o cliente não vai embora
                                        nunca mais.
                                    </p>
                                </div>

                                {/* Pilar 4 */}
                                <div className="relative rounded-3xl border border-[#ded5ca] bg-white p-7 shadow-sm transition hover:shadow-md sm:p-9">
                                    <div className="flex items-center justify-between">
                                        <span className="flex size-11 items-center justify-center rounded-2xl bg-emerald-600 text-lg font-bold text-white">
                                            04
                                        </span>
                                        <Receipt className="size-6 text-emerald-700" />
                                    </div>
                                    <h3 className="mt-6 text-2xl font-bold text-[#17252a]">
                                        Pilar 4: Comanda única e comissão
                                        calculada na hora sem briga
                                    </h3>
                                    <p className="mt-4 text-base leading-relaxed text-[#525f5a]">
                                        Em cada atendimento, você abre uma
                                        comanda simples. Adiciona os serviços
                                        feitos e os produtos que o cliente
                                        comprou. No final, o sistema calcula na
                                        hora e com precisão quanto fica para o
                                        seu espaço e quanto é a comissão de cada
                                        profissional. Tudo transparente, sem
                                        discussão e sem erros de conta.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* =========================================================
                        DOBRA 5: O QUE VOCÊ TEM EM MÃOS (Os 6 Recursos Essenciais)
                    ========================================================= */}
                    <section
                        id="recursos"
                        className="border-t border-[#ded5ca] py-20 lg:py-28"
                    >
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3.5 py-1.5 text-xs font-bold text-emerald-800">
                                    <CheckCircle2 className="size-4" />
                                    Tudo pronto para usar
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-3xl font-extrabold tracking-tight text-[#17252a] sm:text-4xl lg:text-5xl">
                                    Tudo o que seu espaço precisa, reunido em
                                    uma única tela.
                                </h2>
                                <p className="mt-4 text-lg text-[#525f5a]">
                                    Sem funções complicadas que ninguém usa.
                                    Cada recurso foi desenhado para resolver uma
                                    tarefa real do seu dia.
                                </p>
                            </div>

                            {/* Seletor de Abas Interativo para Desktop & Mobile */}
                            <div className="mt-12 flex flex-wrap justify-center gap-2 sm:gap-3">
                                {features.map((feature, idx) => {
                                    const Icon = feature.icon;
                                    const isActive = activeFeature === idx;

                                    return (
                                        <button
                                            key={feature.id}
                                            type="button"
                                            onClick={() =>
                                                setActiveFeature(idx)
                                            }
                                            className={`flex items-center gap-2 rounded-2xl px-4 py-2.5 text-xs font-semibold transition-all sm:text-sm ${
                                                isActive
                                                    ? 'bg-[#17252a] text-white shadow-lg'
                                                    : 'border border-[#ded5ca] bg-white text-[#4f5d59] hover:bg-[#f3eee5] hover:text-[#17252a]'
                                            }`}
                                        >
                                            <Icon
                                                className={`size-4 ${isActive ? 'text-[#efa83f]' : 'text-[#3167d8]'}`}
                                            />
                                            <span>
                                                {feature.title.split(' ')[0]}{' '}
                                                {feature.title.split(' ')[1] ||
                                                    ''}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>

                            {/* Card em Destaque do Recurso Ativo */}
                            <div className="mt-8 rounded-3xl border border-[#ded5ca] bg-white p-6 shadow-xl sm:p-10">
                                <div className="grid items-center gap-8 lg:grid-cols-12 lg:gap-12">
                                    <div className="lg:col-span-6">
                                        <span className="inline-block rounded-full bg-[#edf2ff] px-3.5 py-1 text-xs font-bold text-[#214fae]">
                                            {features[activeFeature].badge}
                                        </span>
                                        <h3 className="mt-4 font-display text-2xl font-bold text-[#17252a] sm:text-3xl">
                                            {features[activeFeature].title}
                                        </h3>
                                        <p className="mt-4 text-base leading-relaxed text-[#525f5a] sm:text-lg">
                                            {
                                                features[activeFeature]
                                                    .description
                                            }
                                        </p>
                                        <div className="mt-8">
                                            <Link
                                                href={register()}
                                                className="inline-flex items-center gap-2 text-base font-bold text-[#3167d8] transition hover:text-[#2551b3]"
                                            >
                                                <span>
                                                    Experimentar este recurso no
                                                    seu espaço
                                                </span>
                                                <ArrowRight className="size-4" />
                                            </Link>
                                        </div>
                                    </div>

                                    {/* Preview do Recurso Ativo */}
                                    <div className="lg:col-span-6">
                                        <div className="rounded-2xl border border-[#ded5ca] bg-[#f8f6f0] p-5">
                                            <div className="flex items-center justify-between border-b border-[#e8e2d8] pb-3">
                                                <div>
                                                    <p className="font-bold text-[#17252a]">
                                                        {
                                                            features[
                                                                activeFeature
                                                            ].preview.title
                                                        }
                                                    </p>
                                                    <p className="text-xs text-[#65726e]">
                                                        {
                                                            features[
                                                                activeFeature
                                                            ].preview.subtitle
                                                        }
                                                    </p>
                                                </div>
                                                <span className="flex size-2.5 animate-pulse rounded-full bg-emerald-500" />
                                            </div>

                                            <div className="mt-4 space-y-2.5">
                                                {features[
                                                    activeFeature
                                                ].preview.items.map(
                                                    (item, i) => (
                                                        <div
                                                            key={i}
                                                            className="flex items-center justify-between rounded-xl border border-[#ded5ca] bg-white p-3 shadow-xs"
                                                        >
                                                            <div>
                                                                <p className="text-xs font-bold text-[#3167d8]">
                                                                    {item.time}
                                                                </p>
                                                                <p className="text-sm font-semibold text-[#17252a]">
                                                                    {
                                                                        item.client
                                                                    }
                                                                </p>
                                                                <p className="text-xs text-[#717d79]">
                                                                    {item.staff}
                                                                </p>
                                                            </div>
                                                            <div className="text-right">
                                                                <span className="inline-block rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800">
                                                                    {
                                                                        item.status
                                                                    }
                                                                </span>
                                                                <p className="mt-1 text-xs font-bold text-[#17252a]">
                                                                    {
                                                                        item.service
                                                                    }
                                                                </p>
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Grade Completa dos 6 Recursos */}
                            <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {features.map((feature) => {
                                    const Icon = feature.icon;

                                    return (
                                        <div
                                            key={feature.id}
                                            className="rounded-3xl border border-[#ded5ca] bg-white p-6 shadow-sm transition-all hover:shadow-md"
                                        >
                                            <div className="flex size-12 items-center justify-center rounded-2xl bg-[#edf2ff] text-[#3167d8]">
                                                <Icon className="size-6" />
                                            </div>
                                            <h4 className="mt-5 text-lg font-bold text-[#17252a]">
                                                {feature.title}
                                            </h4>
                                            <p className="mt-2 text-sm leading-relaxed text-[#525f5a]">
                                                {feature.description}
                                            </p>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    {/* =========================================================
                        DOBRA 6: FEITO PARA QUEM ATENDE SOZINHO OU TEM EQUIPE
                    ========================================================= */}
                    <section className="border-t border-[#ded5ca] bg-white/60 py-20 lg:py-28">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-[#f3eee5] px-3.5 py-1.5 text-xs font-bold text-[#17252a]">
                                    <Users className="size-4 text-[#3167d8]" />
                                    No seu ritmo
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-3xl font-extrabold tracking-tight text-[#17252a] sm:text-4xl lg:text-5xl">
                                    Não importa se você trabalha sozinho ou com
                                    10 profissionais: o Caldas Gestão funciona
                                    para você.
                                </h2>
                            </div>

                            {/* Duas Colunas */}
                            <div className="mt-14 grid gap-8 lg:grid-cols-2">
                                {/* Coluna 1 — Se você atende sozinho(a) */}
                                <div className="rounded-3xl border border-[#ded5ca] bg-white p-8 shadow-md sm:p-10">
                                    <div className="flex size-14 items-center justify-center rounded-2xl bg-[#edf2ff] text-[#3167d8]">
                                        <User className="size-7" />
                                    </div>
                                    <h3 className="mt-6 text-2xl font-bold text-[#17252a]">
                                        Se você atende sozinho(a):
                                    </h3>
                                    <ul className="mt-6 space-y-4 text-base leading-relaxed text-[#525f5a]">
                                        <li className="flex items-start gap-3">
                                            <Check className="mt-1 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="text-[#17252a]">
                                                    O seu assistente digital 24
                                                    horas:
                                                </strong>{' '}
                                                Quando você está com as mãos
                                                ocupadas atendendo, você não
                                                pode ficar respondendo WhatsApp
                                                ou atendendo ligação.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <Check className="mt-1 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                O seu site próprio recebe os
                                                agendamentos sozinho, organiza
                                                seu dia e avisa seu cliente.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <Check className="mt-1 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                Você economiza até 2 horas por
                                                dia de conversas demoradas no
                                                celular e foca no que faz de
                                                melhor: encantar quem senta na
                                                sua cadeira ou deita na sua
                                                maca.
                                            </span>
                                        </li>
                                    </ul>
                                </div>

                                {/* Coluna 2 — Se você tem equipe ou várias cadeiras */}
                                <div className="rounded-3xl border border-[#ded5ca] bg-white p-8 shadow-md sm:p-10">
                                    <div className="flex size-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-800">
                                        <Building2 className="size-7" />
                                    </div>
                                    <h3 className="mt-6 text-2xl font-bold text-[#17252a]">
                                        Se você tem equipe ou várias cadeiras:
                                    </h3>
                                    <ul className="mt-6 space-y-4 text-base leading-relaxed text-[#525f5a]">
                                        <li className="flex items-start gap-3">
                                            <Check className="mt-1 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="text-[#17252a]">
                                                    Cada profissional com seu
                                                    próprio acesso:
                                                </strong>{' '}
                                                Cada colaborador visualiza
                                                apenas os horários dele na
                                                agenda do celular, sem bagunça.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <Check className="mt-1 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="text-[#17252a]">
                                                    Fim das conversas sobre
                                                    comissões:
                                                </strong>{' '}
                                                No final da semana ou do mês, o
                                                relatório de cada um já está
                                                pronto, detalhado e sem pontas
                                                soltas.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <Check className="mt-1 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="text-[#17252a]">
                                                    Visão de dono:
                                                </strong>{' '}
                                                Você acompanha em tempo real
                                                quantas pessoas estão sendo
                                                atendidas, quanto dinheiro está
                                                entrando e qual colaborador está
                                                se destacando.
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            {/* Nota de Rodapé da Dobra */}
                            <div className="mt-8 text-center">
                                <p className="text-sm font-medium text-[#65726e] italic">
                                    (E quando você decidir abrir a sua segunda
                                    unidade, tudo continua integrado e
                                    organizado na mesma conta).
                                </p>
                            </div>
                        </div>
                    </section>

                    {/* =========================================================
                        DOBRA 7: PARA QUEM É E PARA QUEM NÃO É (Qualificação Honesta)
                    ========================================================= */}
                    <section
                        id="para-quem-e"
                        className="border-t border-[#ded5ca] py-20 lg:py-28"
                    >
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3.5 py-1.5 text-xs font-bold text-emerald-800">
                                    <ShieldCheck className="size-4" />
                                    Transparência em primeiro lugar
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-3xl font-extrabold tracking-tight text-[#17252a] sm:text-4xl lg:text-5xl">
                                    O Caldas Gestão é para o seu negócio? Veja
                                    com sinceridade:
                                </h2>
                            </div>

                            {/* Comparativo Lado a Lado */}
                            <div className="mt-14 grid gap-8 lg:grid-cols-2">
                                {/* É PARA VOCÊ que: */}
                                <div className="rounded-3xl border-2 border-emerald-500/30 bg-emerald-50/40 p-8 shadow-sm sm:p-10">
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-9 items-center justify-center rounded-full bg-emerald-600 text-white">
                                            <Check className="size-5 stroke-[3]" />
                                        </span>
                                        <h3 className="font-display text-xl font-bold text-emerald-950 sm:text-2xl">
                                            O Caldas Gestão É PARA VOCÊ que:
                                        </h3>
                                    </div>
                                    <ul className="mt-6 space-y-4">
                                        <li className="flex items-start gap-3 text-base text-[#17252a]">
                                            <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                Tem um salão, barbearia, clínica
                                                de estética, estúdio de
                                                sobrancelhas, cílios, unhas, spa
                                                ou espaço de bem-estar.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-base text-[#17252a]">
                                            <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                Está cansado(a) de perder horas
                                                no WhatsApp tentando achar um
                                                horário vago na agenda de papel.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-base text-[#17252a]">
                                            <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                Quer ter o seu próprio site na
                                                internet e valorizar o nome do
                                                seu espaço como uma marca
                                                profissional.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-base text-[#17252a]">
                                            <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                Quer acabar com as discussões de
                                                comissão e ter certeza de quanto
                                                está sobrando de lucro real no
                                                caixa.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-base text-[#17252a]">
                                            <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                Sonha em ter dinheiro garantido
                                                no dia 1º do mês através de
                                                pacotes e planos mensais.
                                            </span>
                                        </li>
                                    </ul>
                                </div>

                                {/* NÃO É PARA VOCÊ que: */}
                                <div className="rounded-3xl border border-red-200 bg-red-50/40 p-8 shadow-sm sm:p-10">
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-9 items-center justify-center rounded-full bg-red-600 text-white">
                                            <X className="size-5 stroke-[3]" />
                                        </span>
                                        <h3 className="font-display text-xl font-bold text-red-950 sm:text-2xl">
                                            O Caldas Gestão NÃO É PARA VOCÊ que:
                                        </h3>
                                    </div>
                                    <ul className="mt-6 space-y-4">
                                        <li className="flex items-start gap-3 text-base text-[#525f5a]">
                                            <XCircle className="mt-0.5 size-5 shrink-0 text-red-500" />
                                            <span>
                                                Quer continuar anotando
                                                agendamentos em caderno de papel
                                                e arriscando perder os dados se
                                                o caderno molhar.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-base text-[#525f5a]">
                                            <XCircle className="mt-0.5 size-5 shrink-0 text-red-500" />
                                            <span>
                                                Acha normal o cliente furar sem
                                                avisar e prefere ficar no
                                                prejuízo com horário ocioso.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-base text-[#525f5a]">
                                            <XCircle className="mt-0.5 size-5 shrink-0 text-red-500" />
                                            <span>
                                                Prefere continuar fazendo
                                                propaganda gratuita de
                                                aplicativos concorrentes na bio
                                                do seu perfil.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-base text-[#525f5a]">
                                            <XCircle className="mt-0.5 size-5 shrink-0 text-red-500" />
                                            <span>
                                                Não quer organizar seu dinheiro
                                                e não se importa em saber se o
                                                espaço está dando lucro ou
                                                prejuízo.
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* =========================================================
                        DOBRA 8: DÚVIDAS COMUNS / FAQ (Perguntas Frequentes)
                    ========================================================= */}
                    <section
                        id="faq"
                        className="border-t border-[#ded5ca] bg-white/70 py-20 lg:py-28"
                    >
                        <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                            <div className="text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-[#edf2ff] px-3.5 py-1.5 text-xs font-bold text-[#214fae]">
                                    <HelpCircle className="size-4 text-[#3167d8]" />
                                    Tire suas dúvidas sem rodeios
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-3xl font-extrabold tracking-tight text-[#17252a] sm:text-4xl lg:text-5xl">
                                    Dúvidas Comuns / FAQ (Perguntas Frequentes)
                                </h2>
                                <p className="mt-3 text-base text-[#525f5a]">
                                    Tudo o que você precisa saber antes de dar
                                    esse passo importante para o seu espaço.
                                </p>
                            </div>

                            {/* Acordeão Interativo */}
                            <div className="mt-12 space-y-4">
                                {faqs.map((faq, index) => {
                                    const isOpen = openFaq === index;

                                    return (
                                        <div
                                            key={index}
                                            className="overflow-hidden rounded-2xl border border-[#ded5ca] bg-white shadow-xs transition-all"
                                        >
                                            <button
                                                type="button"
                                                onClick={() => toggleFaq(index)}
                                                className="flex w-full items-center justify-between p-5 text-left font-display text-base font-bold text-[#17252a] transition hover:bg-[#fcfaf6] sm:p-6 sm:text-lg"
                                                aria-expanded={isOpen}
                                            >
                                                <span>{faq.q}</span>
                                                <ChevronDown
                                                    className={`ml-4 size-5 shrink-0 text-[#3167d8] transition-transform duration-200 ${
                                                        isOpen
                                                            ? 'rotate-180'
                                                            : ''
                                                    }`}
                                                />
                                            </button>
                                            {isOpen && (
                                                <div className="border-t border-[#f0eae0] px-5 pt-3 pb-6 text-base leading-relaxed text-[#525f5a] sm:px-6 sm:pb-7">
                                                    <p>{faq.a}</p>
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    {/* =========================================================
                        DOBRA 9: AS DUAS ESCOLHAS (O Momento da Decisão)
                    ========================================================= */}
                    <section className="border-t border-[#ded5ca] py-20 lg:py-28">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-[#f3eee5] px-3.5 py-1.5 text-xs font-bold text-[#17252a]">
                                    <Clock className="size-4 text-[#efa83f]" />A
                                    decisão em suas mãos
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-3xl font-extrabold tracking-tight text-[#17252a] sm:text-4xl lg:text-5xl">
                                    Hoje você tem dois caminhos pela frente para
                                    o futuro do seu espaço:
                                </h2>
                            </div>

                            {/* Dois Caminhos */}
                            <div className="mt-14 grid gap-8 lg:grid-cols-2">
                                {/* Caminho 1 */}
                                <div className="rounded-3xl border border-gray-300 bg-gray-100/70 p-8 shadow-inner sm:p-10">
                                    <span className="inline-block rounded-full bg-gray-200 px-3 py-1 text-xs font-bold text-gray-700">
                                        Caminho 1
                                    </span>
                                    <h3 className="mt-4 text-2xl font-bold text-gray-800">
                                        Continuar no cansaço do dia a dia
                                    </h3>
                                    <p className="mt-4 text-base leading-relaxed text-gray-600">
                                        Continuar respondendo mensagens picadas
                                        no WhatsApp até meia-noite, sofrer
                                        quando o cliente fura e não avisa,
                                        perder horas preciosas do seu fim de
                                        semana fazendo contas de comissão e
                                        viver na incerteza de quanto vai sobrar
                                        no final do mês.
                                    </p>
                                    <div className="mt-6 rounded-xl bg-gray-200/80 p-3 text-xs font-medium text-gray-600">
                                        Mais um ano com a sensação de trabalhar
                                        sem parar e não ver o dinheiro sobrar.
                                    </div>
                                </div>

                                {/* Caminho 2 */}
                                <div className="rounded-3xl border-2 border-[#3167d8] bg-linear-to-b from-white to-[#edf2ff]/70 p-8 shadow-xl sm:p-10">
                                    <span className="inline-block rounded-full bg-[#3167d8] px-3 py-1 text-xs font-bold text-white">
                                        Caminho 2
                                    </span>
                                    <h3 className="mt-4 text-2xl font-bold text-[#17252a]">
                                        Ter paz, ordem e um espaço que funciona
                                        de verdade
                                    </h3>
                                    <p className="mt-4 text-base leading-relaxed text-[#525f5a]">
                                        Ter o seu próprio site na internet
                                        trabalhando por você dia e noite, a
                                        agenda da sua equipe organizada no
                                        celular, os clientes recebendo lembretes
                                        automáticos, o caixa fechando redondinho
                                        em segundos e dinheiro garantido no dia
                                        1º com planos mensais.
                                    </p>
                                    <div className="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-semibold text-emerald-800">
                                        Mais tempo com sua família,
                                        profissionalismo na sua marca e
                                        tranquilidade financeira.
                                    </div>
                                </div>
                            </div>

                            {/* Frase de Impacto e CTA */}
                            <div className="mt-14 text-center">
                                <p className="font-display text-xl font-extrabold text-[#17252a] sm:text-2xl">
                                    Qual desses dois caminhos você escolhe para
                                    o seu negócio a partir de hoje?
                                </p>
                                <div className="mt-6">
                                    <Link
                                        href={register()}
                                        className="inline-flex items-center gap-2 rounded-2xl bg-[#3167d8] px-8 py-4 text-lg font-bold text-white shadow-xl shadow-[#3167d8]/20 transition-all hover:bg-[#2551b3] hover:shadow-2xl active:scale-98"
                                    >
                                        <span>
                                            Quero o Caminho 2 para o meu espaço
                                        </span>
                                        <ArrowRight className="size-5" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* =========================================================
                        DOBRA 10: CHAMADA FINAL (Fechamento sem Risco)
                    ========================================================= */}
                    <section className="relative overflow-hidden bg-[#17252a] py-20 text-white lg:py-28">
                        {/* Brilhos decorativos */}
                        <div className="pointer-events-none absolute -top-24 left-1/2 -z-0 h-96 w-96 -translate-x-1/2 rounded-full bg-[#3167d8]/30 blur-3xl" />
                        <div className="pointer-events-none absolute right-0 bottom-0 -z-0 h-80 w-80 rounded-full bg-[#efa83f]/20 blur-3xl" />

                        <div className="relative z-10 mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                            {/* Título de Fechamento */}
                            <h2 className="font-display text-3xl font-extrabold tracking-tight sm:text-4xl lg:text-5xl">
                                Dê o primeiro passo para ter mais tempo livre e
                                um espaço muito mais lucrativo.
                            </h2>

                            {/* Subtítulo */}
                            <p className="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-gray-300 sm:text-xl">
                                Comece seu teste agora mesmo em menos de 2
                                minutos. Veja como é fácil organizar sua agenda,
                                sua equipe e suas finanças.
                            </p>

                            {/* Botão Principal de Ação (CTA Final) */}
                            <div className="mt-10">
                                <Link
                                    href={register()}
                                    className="group inline-flex items-center gap-3 rounded-2xl bg-[#efa83f] px-9 py-5 text-xl font-bold text-[#17252a] shadow-2xl shadow-[#efa83f]/30 transition-all hover:scale-102 hover:bg-[#f3b55c] active:scale-98"
                                >
                                    <span>
                                        👉 Criar Minha Conta Grátis Agora
                                    </span>
                                    <ArrowRight className="size-6 transition-transform group-hover:translate-x-1" />
                                </Link>
                            </div>

                            {/* Garantia e Micro-chamada de Alívio */}
                            <div className="mt-6 flex items-center justify-center gap-2 text-sm font-semibold text-gray-300">
                                <ShieldCheck className="size-5 text-emerald-400" />
                                <span>
                                    🛡️ Sem cartão de crédito necessário • Sem
                                    contratos de fidelidade • Cancele quando
                                    quiser
                                </span>
                            </div>
                        </div>
                    </section>
                </main>

                {/* =========================================================
                    RODAPÉ DA PÁGINA (Footer)
                ========================================================= */}
                <footer className="border-t border-[#ded5ca] bg-[#f3eee5] py-14 text-[#525f5a]">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="grid gap-10 md:grid-cols-12">
                            {/* Brand Info */}
                            <div className="md:col-span-6">
                                <a
                                    href="#top"
                                    className="flex items-center gap-3"
                                >
                                    {branding?.logoUrl ? (
                                        <img
                                            src={branding.logoUrl}
                                            alt={brandName}
                                            className="size-9 rounded-xl object-contain shadow-xs"
                                        />
                                    ) : (
                                        <div className="flex size-9 items-center justify-center rounded-xl bg-[#3167d8] text-white">
                                            <AppLogoIcon className="size-5 text-white" />
                                        </div>
                                    )}
                                    <span className="font-display text-xl font-bold text-[#17252a]">
                                        {brandName}
                                    </span>
                                </a>
                                <p className="mt-4 max-w-md text-sm leading-relaxed text-[#525f5a]">
                                    A ferramenta de gestão e agendamento feita
                                    sob medida para espaços de beleza, estética
                                    e bem-estar.
                                </p>
                                <p className="mt-4 flex items-center gap-2 text-xs font-semibold text-emerald-700">
                                    <ShieldCheck className="size-4" />
                                    <span>
                                        Seus dados e os dados dos seus clientes
                                        estão protegidos com total segurança e
                                        sigilo.
                                    </span>
                                </p>
                            </div>

                            {/* Links Rápidos */}
                            <div className="md:col-span-3">
                                <p className="text-xs font-bold tracking-wider text-[#17252a] uppercase">
                                    Navegação
                                </p>
                                <ul className="mt-4 space-y-2.5 text-sm">
                                    <li>
                                        <a
                                            href="#top"
                                            className="transition hover:text-[#17252a]"
                                        >
                                            Início
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="#recursos"
                                            className="transition hover:text-[#17252a]"
                                        >
                                            Recursos
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="#como-funciona"
                                            className="transition hover:text-[#17252a]"
                                        >
                                            Como Funciona
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="#para-quem-e"
                                            className="transition hover:text-[#17252a]"
                                        >
                                            Para Quem É
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="#faq"
                                            className="transition hover:text-[#17252a]"
                                        >
                                            Dúvidas Frequentes
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            {/* Acesso & Suporte */}
                            <div className="md:col-span-3">
                                <p className="text-xs font-bold tracking-wider text-[#17252a] uppercase">
                                    Atendimento
                                </p>
                                <ul className="mt-4 space-y-2.5 text-sm">
                                    <li>
                                        <Link
                                            href={login()}
                                            className="transition hover:text-[#17252a]"
                                        >
                                            Entrar no Sistema
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href={register()}
                                            className="font-semibold text-[#3167d8] transition hover:text-[#214fae]"
                                        >
                                            Criar Conta Grátis
                                        </Link>
                                    </li>
                                    <li>
                                        <a
                                            href="https://wa.me/5562999999999"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-1.5 transition hover:text-[#17252a]"
                                        >
                                            <HeartHandshake className="size-4 text-[#3167d8]" />
                                            <span>Falar com o Suporte</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {/* Linha Divisória e Copyright */}
                        <div className="mt-12 flex flex-col items-center justify-between border-t border-[#ded5ca] pt-8 text-xs text-[#717d79] sm:flex-row">
                            <p>
                                © 2026 Caldas Gestão. Todos os direitos
                                reservados.
                            </p>
                            <p className="mt-2 sm:mt-0">
                                Desenvolvido com carinho para quem cuida e
                                transforma pessoas.
                            </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}

Home.layout = null;
