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
    MessageSquare,
    PackageCheck,
    Receipt,
    Repeat,
    ShieldCheck,
    Smartphone,
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
                <meta name="robots" content="index, follow" />
                <link rel="canonical" href="https://caldasgestao.com.br" />
                <meta property="og:type" content="website" />
                <meta
                    property="og:title"
                    content="Caldas Gestão — Sistema de Gestão e Agendamento para Espaços de Beleza e Estética"
                />
                <meta
                    property="og:description"
                    content="Tenha seu próprio site de agendamento na internet, acabe com os furos de horário e coloque ordem no seu dinheiro. Simples, rápido e feito para salões, barbearias e clínicas."
                />
                <meta property="og:site_name" content="Caldas Gestão" />
                <meta property="og:locale" content="pt_BR" />
                <meta name="twitter:card" content="summary_large_image" />
                <meta
                    name="twitter:title"
                    content="Caldas Gestão — Sistema de Gestão e Agendamento para Espaços de Beleza e Estética"
                />
                <meta
                    name="twitter:description"
                    content="Tenha seu próprio site de agendamento na internet, acabe com os furos de horário e coloque ordem no seu dinheiro."
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
                    <section className="relative overflow-hidden pt-8 pb-14 sm:pt-14 sm:pb-20 lg:pt-18 lg:pb-28">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="grid items-center gap-10 lg:grid-cols-12 lg:gap-14">
                                {/* Coluna Texto & Ação */}
                                <div className="text-center lg:col-span-7 lg:text-left">
                                    {/* Etiqueta Superior (Badge) */}
                                    <div className="inline-flex items-center gap-2 rounded-full border border-[#ded5ca] bg-white px-4 py-2 text-xs font-semibold text-[#214fae] shadow-xs sm:text-sm">
                                        <Store className="size-4 text-[#3167d8]" />
                                        <span>
                                            A ferramenta completa para o seu
                                            espaço de beleza e estética
                                        </span>
                                    </div>

                                    {/* Título Principal (H1) */}
                                    <h1 className="mt-5 font-display text-3xl font-extrabold tracking-tight text-[#17252a] sm:text-5xl lg:text-6xl lg:leading-[1.12]">
                                        Tenha seu próprio site de agendamento na
                                        internet, acabe com os furos de horário
                                        e coloque ordem no seu dinheiro.
                                    </h1>

                                    {/* Subtítulo */}
                                    <p className="mt-5 text-base leading-relaxed text-[#525f5a] sm:text-lg sm:leading-8">
                                        Tudo o que você precisa para gerenciar
                                        seus clientes, sua equipe e seu caixa em
                                        um só lugar. Seus clientes agendam
                                        sozinhos 24 horas por dia, sua equipe vê
                                        os horários direto no celular e você
                                        nunca mais passa sufoco fechando
                                        comissões na sexta-feira.
                                    </p>

                                    {/* 3 Pontos Rápidos de Benefício */}
                                    <ul className="mt-6 space-y-3 text-left sm:space-y-3.5">
                                        <li className="flex items-start gap-3 text-sm sm:text-base text-[#17252a]">
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
                                        <li className="flex items-start gap-3 text-sm sm:text-base text-[#17252a]">
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
                                        <li className="flex items-start gap-3 text-sm sm:text-base text-[#17252a]">
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

                                    {/* Pílulas de Benefício Rápido */}
                                    <div className="mt-6 flex flex-wrap justify-center gap-2 lg:justify-start">
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">
                                            <Smartphone className="size-3.5 text-emerald-600" />
                                            Sem aplicativo para baixar
                                        </span>
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-[#214fae]">
                                            <CalendarCheck className="size-3.5 text-[#3167d8]" />
                                            Lembrete automático
                                        </span>
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">
                                            <Clock className="size-3.5 text-amber-600" />
                                            24 horas no ar
                                        </span>
                                    </div>

                                    {/* Botão Principal de Ação (CTA) */}
                                    <div className="mt-8 flex flex-col items-center gap-4 sm:flex-row lg:items-start">
                                        <Link
                                            href={register()}
                                            className="group flex w-full items-center justify-center gap-3 rounded-2xl bg-[#3167d8] px-7 py-4 text-center text-base sm:text-lg font-bold text-white shadow-xl shadow-[#3167d8]/25 transition-all hover:bg-[#2551b3] hover:shadow-2xl hover:shadow-[#3167d8]/35 active:scale-98 sm:w-auto"
                                        >
                                            <span>
                                                👉 Quero experimentar no meu
                                                espaço agora
                                            </span>
                                            <ArrowRight className="size-5 transition-transform group-hover:translate-x-1" />
                                        </Link>
                                    </div>

                                    {/* Micro-chamada de Confiança */}
                                    <p className="mt-3.5 flex items-center justify-center gap-2 text-xs sm:text-sm font-medium text-[#65726e] lg:justify-start">
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
                                    <div className="relative mx-auto max-w-sm sm:max-w-md">
                                        {/* Mockup Card Celular / Site Próprio */}
                                        <div className="relative rounded-3xl border border-[#ded5ca] bg-white p-4 shadow-2xl shadow-[#17252a]/10 sm:p-6">
                                            {/* Header do Mockup */}
                                            <div className="flex items-center justify-between border-b border-[#f0eae0] pb-3.5 sm:pb-4">
                                                <div className="flex items-center gap-2.5 sm:gap-3">
                                                    <div className="flex size-10 sm:size-11 items-center justify-center rounded-2xl bg-[#3167d8] text-white shadow-xs">
                                                        <AppLogoIcon className="size-6 text-white" />
                                                    </div>
                                                    <div>
                                                        <p className="text-[10px] sm:text-xs font-semibold tracking-wider text-[#3167d8] uppercase">
                                                            Site Próprio
                                                            Exclusivo
                                                        </p>
                                                        <p className="font-display text-sm sm:text-base font-bold text-[#17252a]">
                                                            Studio Bella &
                                                            Estética
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] sm:text-[11px] font-bold text-emerald-700">
                                                    Online 24h
                                                </span>
                                            </div>

                                            {/* Preview: Agendamento Simples */}
                                            <div className="mt-3.5 sm:mt-4 space-y-2.5 sm:space-y-3">
                                                <div className="rounded-2xl border border-[#e8e2d8] bg-[#f8f6f0] p-3 sm:p-3.5">
                                                    <p className="text-[10px] sm:text-xs font-bold tracking-wider text-[#65726e] uppercase">
                                                        1. Escolha o serviço
                                                    </p>
                                                    <div className="mt-2 flex items-center justify-between rounded-xl border border-[#ded5ca] bg-white p-2 sm:p-2.5 shadow-xs">
                                                        <div>
                                                            <p className="text-xs sm:text-sm font-bold text-[#17252a]">
                                                                Corte & Escova
                                                                Modelada
                                                            </p>
                                                            <p className="text-[11px] sm:text-xs text-[#717d79]">
                                                                50 min • Com
                                                                lavagem especial
                                                            </p>
                                                        </div>
                                                        <span className="text-xs sm:text-sm font-bold text-[#3167d8]">
                                                            R$ 130
                                                        </span>
                                                    </div>
                                                </div>

                                                <div className="rounded-2xl border border-[#e8e2d8] bg-[#f8f6f0] p-3 sm:p-3.5">
                                                    <p className="text-[10px] sm:text-xs font-bold tracking-wider text-[#65726e] uppercase">
                                                        2. Profissional &
                                                        Horário
                                                    </p>
                                                    <div className="mt-2 grid grid-cols-3 gap-2">
                                                        <div className="rounded-xl border border-[#3167d8] bg-[#edf2ff] p-1.5 sm:p-2 text-center text-xs font-bold text-[#214fae]">
                                                            14:30
                                                        </div>
                                                        <div className="rounded-xl border border-[#ded5ca] bg-white p-1.5 sm:p-2 text-center text-xs font-medium text-[#65726e]">
                                                            16:00
                                                        </div>
                                                        <div className="rounded-xl border border-[#ded5ca] bg-white p-1.5 sm:p-2 text-center text-xs font-medium text-[#65726e]">
                                                            17:15
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Lembrete no WhatsApp */}
                                                <div className="rounded-2xl border border-emerald-300/80 bg-[#e7f8ef] p-3 sm:p-3.5 shadow-xs">
                                                    <div className="flex items-start gap-2.5">
                                                        <div className="flex size-7 items-center justify-center rounded-full bg-emerald-600 text-white shrink-0 mt-0.5">
                                                            <MessageSquare className="size-3.5" />
                                                        </div>
                                                        <div className="flex-1">
                                                            <div className="flex items-center justify-between">
                                                                <p className="text-xs font-bold text-emerald-950">
                                                                    WhatsApp • Lembrete Automático
                                                                </p>
                                                                <span className="text-[10px] font-medium text-emerald-800">
                                                                    13:30 (1h antes)
                                                                </span>
                                                            </div>
                                                            <p className="mt-1 text-xs leading-relaxed text-emerald-900">
                                                                &ldquo;Oi Camila! Seu horário para Corte &amp; Escova está confirmado para hoje às 14:30. Esperamos você no Studio Bella!&rdquo;
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Google Agenda */}
                                                <div className="rounded-2xl border border-[#ded5ca] bg-white p-3 sm:p-3.5 shadow-xs">
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center gap-2.5">
                                                            <div className="flex size-8 items-center justify-center rounded-xl bg-[#edf2ff] text-[#3167d8]">
                                                                <CalendarCheck className="size-4" />
                                                            </div>
                                                            <div>
                                                                <p className="text-xs font-bold text-[#17252a]">
                                                                    Google Agenda Sincronizada
                                                                </p>
                                                                <p className="text-[11px] text-[#65726e]">
                                                                    14:30 – 15:20 • Camila F. • Sala 1
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <span className="rounded-md bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-[#214fae]">
                                                            No Celular
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Card Flutuante de Comanda & Pix */}
                                        <div className="mt-3.5 sm:mt-4 rounded-2xl border border-[#ded5ca] bg-white p-3.5 sm:p-4 shadow-xl">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2.5 sm:gap-3">
                                                    <div className="flex size-9 sm:size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-800">
                                                        <Receipt className="size-4 sm:size-5" />
                                                    </div>
                                                    <div>
                                                        <p className="text-[11px] sm:text-xs font-medium text-[#65726e]">
                                                            Comanda #1042 • Pix Recebido
                                                        </p>
                                                        <p className="text-base sm:text-lg font-bold text-[#17252a]">
                                                            R$ 130,00 • Caixa Fechado
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                                                    Taxa R$ 0,00
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
                        className="border-y border-[#ded5ca] bg-white/60 py-12 sm:py-16 lg:py-24"
                    >
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3.5 py-1.5 text-xs font-bold text-amber-900">
                                    <AlertTriangle className="size-4 text-amber-700" />
                                    ⚠️ Você já parou para pensar nisso?
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-2xl font-extrabold tracking-tight text-[#17252a] sm:text-3xl lg:text-4xl">
                                    O erro que a maioria comete ao colocar links
                                    de outros aplicativos na bio do seu
                                    Instagram.
                                </h2>

                                {/* Texto de Apoio */}
                                <div className="mt-5 space-y-3.5 text-sm sm:text-base leading-relaxed text-[#525f5a]">
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
                                        dos concorrentes na mesma tela.
                                    </p>
                                    <p className="rounded-2xl border border-red-200 bg-red-50/80 p-4 font-semibold text-red-900 text-sm sm:text-base">
                                        Sem perceber,{' '}
                                        <span className="underline decoration-red-400 underline-offset-4">
                                            você pagou o marketing para mandar
                                            seu cliente para a concorrência.
                                        </span>
                                    </p>
                                </div>
                            </div>

                            {/* Comparativo Visual Lado a Lado (Alto Contraste) */}
                            <div className="mt-10 sm:mt-12 grid gap-6 md:grid-cols-2">
                                {/* O que acontece hoje nos outros apps */}
                                <div className="relative flex flex-col justify-between rounded-3xl border border-red-200 bg-white p-6 shadow-sm sm:p-8">
                                    <div>
                                        <div className="flex items-center justify-between">
                                            <span className="inline-flex items-center gap-2 rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-800">
                                                <XCircle className="size-4" />
                                                Outros aplicativos (Agregadores)
                                            </span>
                                            <span className="text-xs font-semibold text-red-600">
                                                Perda de Clientes
                                            </span>
                                        </div>
                                        <h3 className="mt-4 text-lg sm:text-xl font-bold text-[#17252a]">
                                            O cliente se perde e vê seus
                                            concorrentes
                                        </h3>
                                        <ul className="mt-5 space-y-3 text-sm text-[#65726e]">
                                            <li className="flex items-start gap-2.5">
                                                <X className="mt-0.5 size-4 shrink-0 text-red-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Concorrentes na mesma tela:
                                                    </strong>{' '}
                                                    Seu cliente vê ofertas e
                                                    promoções de outros salões ao
                                                    lado do seu preço.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2.5">
                                                <X className="mt-0.5 size-4 shrink-0 text-red-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Obrigam a baixar app:
                                                    </strong>{' '}
                                                    Exigem cadastro, download e
                                                    senha na loja do celular,
                                                    causando desistência.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2.5">
                                                <X className="mt-0.5 size-4 shrink-0 text-red-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Taxas sobre o seu suor:
                                                    </strong>{' '}
                                                    Cobram porcentagens abusivas
                                                    sobre o dinheiro suado do
                                                    seu trabalho.
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div className="mt-6 rounded-xl border border-red-100 bg-red-50 p-3 text-xs font-medium text-red-700">
                                        ⚠️ Você investe no anúncio, mas quem
                                        ganha o cliente pode ser o vizinho.
                                    </div>
                                </div>

                                {/* Como funciona no seu site próprio */}
                                <div className="relative flex flex-col justify-between rounded-3xl border-2 border-[#3167d8] bg-linear-to-b from-[#edf2ff]/60 to-white p-6 shadow-xl shadow-[#3167d8]/10 sm:p-8">
                                    <div>
                                        <div className="flex items-center justify-between">
                                            <span className="inline-flex items-center gap-2 rounded-full bg-[#3167d8] px-3 py-1 text-xs font-bold text-white">
                                                <CheckCircle2 className="size-4" />
                                                No seu próprio site
                                            </span>
                                            <span className="text-xs font-bold text-[#214fae]">
                                                100% Exclusivo
                                            </span>
                                        </div>
                                        <h3 className="mt-4 text-lg sm:text-xl font-bold text-[#17252a]">
                                            No Caldas Gestão, a estrela é o seu
                                            espaço
                                        </h3>
                                        <ul className="mt-5 space-y-3 text-sm text-[#525f5a]">
                                            <li className="flex items-start gap-2.5">
                                                <ShieldCheck className="mt-0.5 size-4 shrink-0 text-[#3167d8]" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        100% a sua marca:
                                                    </strong>{' '}
                                                    Apenas os seus serviços, seus
                                                    preços, suas fotos e a sua
                                                    equipe. Zero concorrência.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2.5">
                                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Sem aplicativo para
                                                        baixar:
                                                    </strong>{' '}
                                                    O cliente clica no link e
                                                    agenda em 30 segundos direto
                                                    no navegador do celular.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2.5">
                                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Atenção exclusiva:
                                                    </strong>{' '}
                                                    Sua marca ganha autoridade e
                                                    você não divide a atenção de
                                                    quem quer ser atendido por
                                                    você.
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div className="mt-6 flex flex-wrap gap-2">
                                        <span className="inline-flex items-center rounded-lg bg-[#3167d8]/10 px-2.5 py-1 text-xs font-semibold text-[#214fae]">
                                            Sem aplicativo para baixar
                                        </span>
                                        <span className="inline-flex items-center rounded-lg bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                                            24 horas no ar
                                        </span>
                                        <span className="inline-flex items-center rounded-lg bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                                            Sua marca valorizada
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* =========================================================
                        DOBRA 3: VOCÊ SE IDENTIFICA COM ISSO? (Os 4 problemas)
                    ========================================================= */}
                    <section className="py-12 sm:py-16 lg:py-24">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-[#e8e2d8] px-3.5 py-1.5 text-xs font-bold text-[#4b5552]">
                                    <Store className="size-4 text-[#3167d8]" />A
                                    vida real de quem tem um espaço
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-2xl font-extrabold tracking-tight text-[#17252a] sm:text-3xl lg:text-4xl">
                                    Quantas dessas 4 dores de cabeça você
                                    enfrentou só nesta última semana?
                                </h2>
                            </div>

                            {/* 4 Cards de Problemas */}
                            <div className="mt-10 sm:mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                {/* O cliente que fura */}
                                <div className="flex flex-col justify-between rounded-3xl border border-[#ded5ca] bg-white p-6 shadow-sm transition hover:shadow-md">
                                    <div>
                                        <div className="flex items-center justify-between">
                                            <div className="flex size-12 items-center justify-center rounded-2xl bg-red-100 text-red-700">
                                                <UserX className="size-6" />
                                            </div>
                                            <span className="rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-700">
                                                Prejuízo direto
                                            </span>
                                        </div>
                                        <h3 className="mt-5 text-base sm:text-lg font-bold text-[#17252a]">
                                            O cliente que fura e deixa a cadeira
                                            vazia
                                        </h3>
                                        <ul className="mt-4 space-y-2.5 text-xs sm:text-sm text-[#525f5a]">
                                            <li className="flex items-start gap-2">
                                                <span className="mt-0.5 text-red-500 font-bold">•</span>
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Horário em vão:
                                                    </strong>{' '}
                                                    Você recusa outros agendamentos
                                                    e a pessoa não comparece.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <span className="mt-0.5 text-red-500 font-bold">•</span>
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Sem aviso prévio:
                                                    </strong>{' '}
                                                    Nem manda mensagem avisando do
                                                    imprevisto de última hora.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <span className="mt-0.5 text-red-500 font-bold">•</span>
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Cadeira vazia:
                                                    </strong>{' '}
                                                    Dinheiro que sumiu do seu
                                                    caixa sem volta no fim do dia.
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div className="mt-6 rounded-xl border border-red-100 bg-red-50 p-2.5 text-xs font-semibold text-red-800">
                                        Horário ocioso = Prejuízo no bolso
                                    </div>
                                </div>

                                {/* O sufoco na hora de fechar o caixa */}
                                <div className="flex flex-col justify-between rounded-3xl border border-[#ded5ca] bg-white p-6 shadow-sm transition hover:shadow-md">
                                    <div>
                                        <div className="flex items-center justify-between">
                                            <div className="flex size-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-800">
                                                <Receipt className="size-6" />
                                            </div>
                                            <span className="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-800">
                                                Dor de cabeça
                                            </span>
                                        </div>
                                        <h3 className="mt-5 text-base sm:text-lg font-bold text-[#17252a]">
                                            O sufoco na hora de fechar o caixa
                                        </h3>
                                        <ul className="mt-4 space-y-2.5 text-xs sm:text-sm text-[#525f5a]">
                                            <li className="flex items-start gap-2">
                                                <span className="mt-0.5 text-amber-600 font-bold">•</span>
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Comandas rasuradas:
                                                    </strong>{' '}
                                                    Papéis espalhados e letras
                                                    ilegíveis na bancada.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <span className="mt-0.5 text-amber-600 font-bold">•</span>
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Calculadora e estresse:
                                                    </strong>{' '}
                                                    Discussões sobre quem
                                                    atendeu o quê e divisão de
                                                    valores.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <span className="mt-0.5 text-amber-600 font-bold">•</span>
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Sexta exaustiva:
                                                    </strong>{' '}
                                                    Horas preciosas perdidas
                                                    tentando bater comissões na
                                                    mão.
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div className="mt-6 rounded-xl border border-amber-100 bg-amber-50 p-2.5 text-xs font-semibold text-amber-800">
                                        Contas demoradas e atrito na equipe
                                    </div>
                                </div>

                                {/* A renda montanha-russa */}
                                <div className="flex flex-col justify-between rounded-3xl border border-[#ded5ca] bg-white p-6 shadow-sm transition hover:shadow-md">
                                    <div>
                                        <div className="flex items-center justify-between">
                                            <div className="flex size-12 items-center justify-center rounded-2xl bg-blue-100 text-[#214fae]">
                                                <DollarSign className="size-6" />
                                            </div>
                                            <span className="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-[#214fae]">
                                                Insegurança
                                            </span>
                                        </div>
                                        <h3 className="mt-5 text-base sm:text-lg font-bold text-[#17252a]">
                                            A renda montanha-russa todo início
                                            de mês
                                        </h3>
                                        <ul className="mt-4 space-y-2.5 text-xs sm:text-sm text-[#525f5a]">
                                            <li className="flex items-start gap-2">
                                                <span className="mt-0.5 text-blue-600 font-bold">•</span>
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Incerteza financeira:
                                                    </strong>{' '}
                                                    Um mês é ótimo, no outro dá
                                                    aperto no peito com os
                                                    boletos do dia 10.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <span className="mt-0.5 text-blue-600 font-bold">•</span>
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Dependência da sorte:
                                                    </strong>{' '}
                                                    Esperar o cliente decidir
                                                    vir gera ansiedade todo dia.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <span className="mt-0.5 text-blue-600 font-bold">•</span>
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Zero planejamento:
                                                    </strong>{' '}
                                                    Impossível investir no
                                                    espaço sem saber quanto vai
                                                    sobrar.
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div className="mt-6 rounded-xl border border-blue-100 bg-blue-50 p-2.5 text-xs font-semibold text-[#214fae]">
                                        Falta de previsibilidade de receita
                                    </div>
                                </div>

                                {/* O cliente que sumiu */}
                                <div className="flex flex-col justify-between rounded-3xl border border-[#ded5ca] bg-white p-6 shadow-sm transition hover:shadow-md">
                                    <div>
                                        <div className="flex items-center justify-between">
                                            <div className="flex size-12 items-center justify-center rounded-2xl bg-rose-100 text-rose-700">
                                                <UserMinus className="size-6" />
                                            </div>
                                            <span className="rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700">
                                                Perda silenciosa
                                            </span>
                                        </div>
                                        <h3 className="mt-5 text-base sm:text-lg font-bold text-[#17252a]">
                                            O cliente que sumiu e ninguém
                                            percebeu
                                        </h3>
                                        <ul className="mt-4 space-y-2.5 text-xs sm:text-sm text-[#525f5a]">
                                            <li className="flex items-start gap-2">
                                                <span className="mt-0.5 text-rose-500 font-bold">•</span>
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Sumiço imperceptível:
                                                    </strong>{' '}
                                                    A pessoa vinha todo mês, mas
                                                    sumiu há 40 dias e ninguém
                                                    notou.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <span className="mt-0.5 text-rose-500 font-bold">•</span>
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Fuga para concorrente:
                                                    </strong>{' '}
                                                    Quando você repara, ela já
                                                    se acostumou em outro salão.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <span className="mt-0.5 text-rose-500 font-bold">•</span>
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Sem lembrete de retorno:
                                                    </strong>{' '}
                                                    Falta de convite carinhoso
                                                    chamando a pessoa para voltar.
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div className="mt-6 rounded-xl border border-rose-100 bg-rose-50 p-2.5 text-xs font-semibold text-rose-800">
                                        Clientes escapando sem você ver
                                    </div>
                                </div>
                            </div>

                            {/* Fechamento da Dobra (Callout Empático) */}
                            <div className="mx-auto mt-10 sm:mt-12 max-w-4xl rounded-3xl border border-[#ded5ca] bg-[#fffdf9] p-6 sm:p-8 text-center shadow-md">
                                <p className="font-display text-base sm:text-lg leading-relaxed font-medium text-[#17252a] italic">
                                    “A culpa não é sua. Você aprendeu a ser um
                                    excelente profissional na sua arte, mas
                                    ninguém te deu uma ferramenta simples para o
                                    seu negócio funcionar sem sugar sua paz.”
                                </p>
                            </div>
                        </div>
                    </section>

                    {/* =========================================================
                        DOBRA 4: COMO FUNCIONA A TRANSFORMAÇÃO (Os 4 Passos)
                    ========================================================= */}
                    <section
                        id="como-funciona"
                        className="border-t border-[#ded5ca] bg-white/70 py-12 sm:py-16 lg:py-24"
                    >
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-[#edf2ff] px-3.5 py-1.5 text-xs font-bold text-[#214fae]">
                                    <Repeat className="size-4 text-[#3167d8]" />
                                    Como o Caldas Gestão muda a sua rotina
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-2xl font-extrabold tracking-tight text-[#17252a] sm:text-3xl lg:text-4xl">
                                    Quatro passos práticos para colocar ordem na
                                    casa e ter paz na gestão.
                                </h2>
                            </div>

                            {/* Grid dos 4 Passos */}
                            <div className="mt-10 sm:mt-12 grid gap-6 md:grid-cols-2">
                                {/* 01. Seu próprio site na internet */}
                                <div className="relative flex flex-col justify-between rounded-3xl border border-[#ded5ca] bg-white p-6 shadow-sm transition hover:shadow-md sm:p-8">
                                    <div>
                                        <div className="flex items-center justify-between">
                                            <span className="flex size-11 items-center justify-center rounded-2xl bg-[#17252a] text-lg font-bold text-white">
                                                01
                                            </span>
                                            <Smartphone className="size-6 text-[#3167d8]" />
                                        </div>
                                        <h3 className="mt-5 text-xl sm:text-2xl font-bold text-[#17252a]">
                                            01. Seu próprio site na internet
                                        </h3>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <span className="rounded-full bg-[#edf2ff] px-2.5 py-0.5 text-xs font-semibold text-[#214fae]">
                                                Sem baixar aplicativo
                                            </span>
                                            <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                                24 horas no ar
                                            </span>
                                        </div>
                                        <ul className="mt-4 space-y-2.5 text-sm text-[#525f5a]">
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Página exclusiva:
                                                    </strong>{' '}
                                                    O cliente clica no link da sua
                                                    bio do Instagram ou WhatsApp
                                                    com sua marca, logo e fotos.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Agendamento em 30s:
                                                    </strong>{' '}
                                                    Ele escolhe o serviço, o
                                                    profissional e o horário
                                                    ideal sem atrito.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Direto no navegador:
                                                    </strong>{' '}
                                                    Não precisa baixar app na
                                                    loja nem ocupar espaço na
                                                    memória do celular.
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                {/* 02. Agenda no celular de toda a equipe */}
                                <div className="relative flex flex-col justify-between rounded-3xl border border-[#ded5ca] bg-white p-6 shadow-sm transition hover:shadow-md sm:p-8">
                                    <div>
                                        <div className="flex items-center justify-between">
                                            <span className="flex size-11 items-center justify-center rounded-2xl bg-[#3167d8] text-lg font-bold text-white">
                                                02
                                            </span>
                                            <CalendarCheck className="size-6 text-emerald-600" />
                                        </div>
                                        <h3 className="mt-5 text-xl sm:text-2xl font-bold text-[#17252a]">
                                            02. Agenda no celular de toda a
                                            equipe
                                        </h3>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <span className="rounded-full bg-[#edf2ff] px-2.5 py-0.5 text-xs font-semibold text-[#214fae]">
                                                Google Agenda integrada
                                            </span>
                                            <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                                Lembretes automáticos
                                            </span>
                                        </div>
                                        <ul className="mt-4 space-y-2.5 text-sm text-[#525f5a]">
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Sincronização imediata:
                                                    </strong>{' '}
                                                    Compromissos aparecem no
                                                    Google Agenda do celular de
                                                    cada colaborador.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Avisos automáticos:
                                                    </strong>{' '}
                                                    O cliente recebe lembrete
                                                    antes do horário e confirma a
                                                    presença.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Fim dos furos:
                                                    </strong>{' '}
                                                    Redução drástica de
                                                    ausências sem você precisar
                                                    mandar mensagem manual.
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                {/* 03. Dinheiro garantido todo mês */}
                                <div className="relative flex flex-col justify-between rounded-3xl border border-[#ded5ca] bg-white p-6 shadow-sm transition hover:shadow-md sm:p-8">
                                    <div>
                                        <div className="flex items-center justify-between">
                                            <span className="flex size-11 items-center justify-center rounded-2xl bg-[#efa83f] text-lg font-bold text-[#17252a]">
                                                03
                                            </span>
                                            <Repeat className="size-6 text-amber-700" />
                                        </div>
                                        <h3 className="mt-5 text-xl sm:text-2xl font-bold text-[#17252a]">
                                            03. Dinheiro garantido todo mês
                                        </h3>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <span className="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                                Receita no dia 1º
                                            </span>
                                            <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                                Pacotes & Assinaturas
                                            </span>
                                        </div>
                                        <ul className="mt-4 space-y-2.5 text-sm text-[#525f5a]">
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Planos de mensalidade:
                                                    </strong>{' '}
                                                    Crie assinaturas para corte
                                                    mensal, manutenção quinzenal
                                                    ou tratamentos.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Pagamento adiantado:
                                                    </strong>{' '}
                                                    Venda pacotes de sessões e
                                                    garanta o dinheiro antes
                                                    mesmo do atendimento.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Previsibilidade real:
                                                    </strong>{' '}
                                                    Comece o mês com faturamento
                                                    garantido para pagar as
                                                    contas sem sufoco.
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                {/* 04. Comanda única e comissão na hora */}
                                <div className="relative flex flex-col justify-between rounded-3xl border border-[#ded5ca] bg-white p-6 shadow-sm transition hover:shadow-md sm:p-8">
                                    <div>
                                        <div className="flex items-center justify-between">
                                            <span className="flex size-11 items-center justify-center rounded-2xl bg-emerald-600 text-lg font-bold text-white">
                                                04
                                            </span>
                                            <Receipt className="size-6 text-emerald-700" />
                                        </div>
                                        <h3 className="mt-5 text-xl sm:text-2xl font-bold text-[#17252a]">
                                            04. Comanda única e comissão na hora
                                        </h3>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                                Comissão automática
                                            </span>
                                            <span className="rounded-full bg-[#edf2ff] px-2.5 py-0.5 text-xs font-semibold text-[#214fae]">
                                                Zero discussão
                                            </span>
                                        </div>
                                        <ul className="mt-4 space-y-2.5 text-sm text-[#525f5a]">
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Tudo somado na hora:
                                                    </strong>{' '}
                                                    Junte serviços e produtos
                                                    vendidos numa comanda
                                                    simples e rápida.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Divisão automática:
                                                    </strong>{' '}
                                                    O sistema separa o valor do
                                                    espaço e a comissão de cada
                                                    profissional com precisão.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    <strong className="text-[#17252a]">
                                                        Transparência total:
                                                    </strong>{' '}
                                                    Sem papel rasurado, sem
                                                    discussão de valores e com
                                                    harmonia na equipe.
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* =========================================================
                        DOBRA 5: O QUE VOCÊ TEM EM MÃOS (Os 6 Recursos Essenciais)
                    ========================================================= */}
                    <section
                        id="recursos"
                        className="border-t border-[#ded5ca] py-12 sm:py-16 lg:py-24"
                    >
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3.5 py-1.5 text-xs font-bold text-emerald-800">
                                    <CheckCircle2 className="size-4" />
                                    Tudo pronto para usar
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-2xl font-extrabold tracking-tight text-[#17252a] sm:text-3xl lg:text-4xl">
                                    Tudo o que seu espaço precisa, reunido em
                                    uma única tela.
                                </h2>
                                <p className="mt-4 text-base text-[#525f5a] sm:text-lg">
                                    Sem funções complicadas que ninguém usa.
                                    Cada recurso foi desenhado para resolver uma
                                    tarefa real do seu dia.
                                </p>
                            </div>

                            {/* Seletor de Abas Interativo para Desktop & Mobile */}
                            <div className="mt-10 sm:mt-12 flex overflow-x-auto pb-2 scrollbar-none snap-x sm:flex-wrap sm:justify-center gap-2 sm:gap-3">
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
                                            className={`flex snap-start shrink-0 items-center gap-2 rounded-2xl px-4 py-2.5 text-xs font-semibold transition-all sm:text-sm ${
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
                    <section className="border-t border-[#ded5ca] bg-white/60 py-12 sm:py-16 lg:py-24">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-[#f3eee5] px-3.5 py-1.5 text-xs font-bold text-[#17252a]">
                                    <Users className="size-4 text-[#3167d8]" />
                                    No seu ritmo
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-2xl font-extrabold tracking-tight text-[#17252a] sm:text-3xl lg:text-4xl">
                                    Não importa se você trabalha sozinho ou com
                                    10 profissionais: o Caldas Gestão funciona
                                    para você.
                                </h2>
                            </div>

                            {/* Duas Colunas */}
                            <div className="mt-10 sm:mt-14 grid gap-8 lg:grid-cols-2">
                                {/* Coluna 1 — Se você atende sozinho(a) */}
                                <div className="rounded-3xl border border-[#ded5ca] bg-white p-6 sm:p-8 shadow-md">
                                    <div className="flex size-12 sm:size-14 items-center justify-center rounded-2xl bg-[#edf2ff] text-[#3167d8]">
                                        <User className="size-6 sm:size-7" />
                                    </div>
                                    <h3 className="mt-5 sm:mt-6 text-xl sm:text-2xl font-bold text-[#17252a]">
                                        Se você atende sozinho(a):
                                    </h3>
                                    <ul className="mt-6 space-y-4 text-sm sm:text-base leading-relaxed text-[#525f5a]">
                                        <li className="flex items-start gap-3">
                                            <Check className="mt-1 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="text-[#17252a]">
                                                    Assistente digital 24h:
                                                </strong>{' '}
                                                seu site próprio recebe agendamentos sozinho enquanto você atende, sem interrupções.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <Check className="mt-1 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="text-[#17252a]">
                                                    Fim das mensagens picadas:
                                                </strong>{' '}
                                                economize até 2 horas por dia de conversas longas no WhatsApp tentando achar horários vagos.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <Check className="mt-1 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="text-[#17252a]">
                                                    Foco total no atendimento:
                                                </strong>{' '}
                                                encante quem senta na sua cadeira ou deita na sua maca sem a ansiedade do celular apitando.
                                            </span>
                                        </li>
                                    </ul>
                                    <div className="mt-6 rounded-2xl border border-blue-100 bg-[#edf2ff]/60 p-3.5 text-xs font-semibold text-[#214fae]">
                                        💡 Comece sozinho e adicione profissionais conforme seu espaço crescer, com poucos cliques.
                                    </div>
                                </div>

                                {/* Coluna 2 — Se você tem equipe ou várias cadeiras */}
                                <div className="rounded-3xl border border-[#ded5ca] bg-white p-6 sm:p-8 shadow-md">
                                    <div className="flex size-12 sm:size-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-800">
                                        <Building2 className="size-6 sm:size-7" />
                                    </div>
                                    <h3 className="mt-5 sm:mt-6 text-xl sm:text-2xl font-bold text-[#17252a]">
                                        Se você tem equipe ou várias cadeiras:
                                    </h3>
                                    <ul className="mt-6 space-y-4 text-sm sm:text-base leading-relaxed text-[#525f5a]">
                                        <li className="flex items-start gap-3">
                                            <Check className="mt-1 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="text-[#17252a]">
                                                    Acesso individual no celular:
                                                </strong>{' '}
                                                cada profissional visualiza apenas a própria agenda e seus atendimentos, sem confusão.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <Check className="mt-1 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="text-[#17252a]">
                                                    Comissões 100% transparentes:
                                                </strong>{' '}
                                                relatório pronto no final da semana ou mês, sem rasuras, sem calculadora e sem atrito na equipe.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <Check className="mt-1 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="text-[#17252a]">
                                                    Visão gerencial de dono:
                                                </strong>{' '}
                                                acompanhe em tempo real quem mais atende, o faturamento diário e o lucro seguro do espaço.
                                            </span>
                                        </li>
                                    </ul>
                                    <div className="mt-6 rounded-2xl border border-amber-200/80 bg-amber-50/80 p-3.5 text-xs font-semibold text-amber-900">
                                        🏢 Suporte para múltiplas unidades caso você decida expandir sua operação.
                                    </div>
                                </div>
                            </div>

                            {/* Nota de Rodapé da Dobra */}
                            <div className="mt-8 text-center">
                                <p className="text-xs sm:text-sm font-medium text-[#65726e] italic">
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
                        className="border-t border-[#ded5ca] py-12 sm:py-16 lg:py-24"
                    >
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3.5 py-1.5 text-xs font-bold text-emerald-800">
                                    <ShieldCheck className="size-4" />
                                    Transparência em primeiro lugar
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-2xl font-extrabold tracking-tight text-[#17252a] sm:text-3xl lg:text-4xl">
                                    O Caldas Gestão é para o seu negócio? Veja
                                    com sinceridade:
                                </h2>
                            </div>

                            {/* Comparativo Lado a Lado */}
                            <div className="mt-10 sm:mt-14 grid gap-8 lg:grid-cols-2">
                                {/* É PARA VOCÊ que: */}
                                <div className="rounded-3xl border-2 border-emerald-500/30 bg-emerald-50/40 p-6 sm:p-8 md:p-10 shadow-sm">
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-9 items-center justify-center rounded-full bg-emerald-600 text-white">
                                            <Check className="size-5 stroke-[3]" />
                                        </span>
                                        <h3 className="font-display text-xl font-bold text-emerald-950 sm:text-2xl">
                                            O Caldas Gestão É PARA VOCÊ que:
                                        </h3>
                                    </div>
                                    <ul className="mt-6 space-y-4">
                                        <li className="flex items-start gap-3 text-sm sm:text-base text-[#17252a]">
                                            <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="font-semibold text-emerald-950">
                                                    Espaços de beleza e bem-estar:
                                                </strong>{' '}
                                                salões, barbearias, clínicas de estética, estúdios de sobrancelhas, cílios, unhas e spas.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-sm sm:text-base text-[#17252a]">
                                            <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="font-semibold text-emerald-950">
                                                    Fim do malabarismo no WhatsApp:
                                                </strong>{' '}
                                                para quem cansou de perder horas preciosas tentando achar horário vago em agenda de papel.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-sm sm:text-base text-[#17252a]">
                                            <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="font-semibold text-emerald-950">
                                                    Marca própria valorizada:
                                                </strong>{' '}
                                                quer ter o seu próprio site na internet e valorizar o nome do seu espaço como marca exclusiva.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-sm sm:text-base text-[#17252a]">
                                            <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="font-semibold text-emerald-950">
                                                    Caixa e comissões no automático:
                                                </strong>{' '}
                                                busca acabar com discussões de repasse e ter certeza de quanto sobra de lucro real no caixa.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-sm sm:text-base text-[#17252a]">
                                            <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                            <span>
                                                <strong className="font-semibold text-emerald-950">
                                                    Previsibilidade no dia 1º:
                                                </strong>{' '}
                                                sonha em ter receita recorrente garantida no começo do mês através de pacotes e planos mensais.
                                            </span>
                                        </li>
                                    </ul>
                                </div>

                                {/* NÃO É PARA VOCÊ que: */}
                                <div className="rounded-3xl border border-red-200 bg-red-50/40 p-6 sm:p-8 md:p-10 shadow-sm">
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-9 items-center justify-center rounded-full bg-red-600 text-white">
                                            <X className="size-5 stroke-[3]" />
                                        </span>
                                        <h3 className="font-display text-xl font-bold text-red-950 sm:text-2xl">
                                            O Caldas Gestão NÃO É PARA VOCÊ que:
                                        </h3>
                                    </div>
                                    <ul className="mt-6 space-y-4">
                                        <li className="flex items-start gap-3 text-sm sm:text-base text-[#525f5a]">
                                            <XCircle className="mt-0.5 size-5 shrink-0 text-red-500" />
                                            <span>
                                                <strong className="font-semibold text-red-950">
                                                    Apego ao caderno de papel:
                                                </strong>{' '}
                                                prefere o risco de anotações rasuradas, ilegíveis ou perdidas se o caderno molhar.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-sm sm:text-base text-[#525f5a]">
                                            <XCircle className="mt-0.5 size-5 shrink-0 text-red-500" />
                                            <span>
                                                <strong className="font-semibold text-red-950">
                                                    Aceita furos de horário:
                                                </strong>{' '}
                                                acha normal o cliente faltar sem avisar e prefere arcar sozinho com o prejuízo do horário ocioso.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-sm sm:text-base text-[#525f5a]">
                                            <XCircle className="mt-0.5 size-5 shrink-0 text-red-500" />
                                            <span>
                                                <strong className="font-semibold text-red-950">
                                                    Divulga concorrentes de graça:
                                                </strong>{' '}
                                                insiste em manter links de aplicativos de terceiros na bio, enviando clientes para o vizinho.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3 text-sm sm:text-base text-[#525f5a]">
                                            <XCircle className="mt-0.5 size-5 shrink-0 text-red-500" />
                                            <span>
                                                <strong className="font-semibold text-red-950">
                                                    Descaso com o dinheiro:
                                                </strong>{' '}
                                                não se importa em saber se o espaço está dando lucro real ou prejuízo no fim do mês.
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* =========================================================
                        DOBRA 8: DÚVIDAS FREQUENTES
                    ========================================================= */}
                    <section
                        id="faq"
                        className="border-t border-[#ded5ca] bg-white/70 py-12 sm:py-16 lg:py-24"
                    >
                        <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                            <div className="text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-[#edf2ff] px-3.5 py-1.5 text-xs font-bold text-[#214fae]">
                                    <HelpCircle className="size-4 text-[#3167d8]" />
                                    Tire suas dúvidas sem rodeios
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-2xl font-extrabold tracking-tight text-[#17252a] sm:text-3xl lg:text-4xl">
                                    Dúvidas Frequentes
                                </h2>
                                <p className="mt-3 text-base text-[#525f5a]">
                                    Tudo o que você precisa saber antes de dar
                                    esse passo importante para o seu espaço.
                                </p>
                            </div>

                            {/* Acordeão Interativo */}
                            <div className="mt-10 sm:mt-12 space-y-4">
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
                                                <div className="border-t border-[#f0eae0] px-5 pt-3 pb-6 text-sm sm:text-base leading-relaxed text-[#525f5a] sm:px-6 sm:pb-7">
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
                    <section className="border-t border-[#ded5ca] py-12 sm:py-16 lg:py-24">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                {/* Chapeuzinho (Tag) */}
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-[#f3eee5] px-3.5 py-1.5 text-xs font-bold text-[#17252a]">
                                    <Clock className="size-4 text-[#efa83f]" />
                                    A decisão em suas mãos
                                </span>

                                {/* Título */}
                                <h2 className="mt-4 font-display text-2xl font-extrabold tracking-tight text-[#17252a] sm:text-3xl lg:text-4xl">
                                    Hoje você tem dois caminhos pela frente para
                                    o futuro do seu espaço:
                                </h2>
                            </div>

                            {/* Dois Caminhos */}
                            <div className="mt-10 sm:mt-14 grid gap-8 lg:grid-cols-2">
                                {/* Opção 1: Sobrecarga */}
                                <div className="rounded-3xl border border-stone-300 bg-stone-100/80 p-6 sm:p-8 md:p-10 shadow-xs">
                                    <span className="inline-block rounded-full bg-stone-200 px-3 py-1 text-xs font-bold text-stone-700">
                                        Rotina de Sobrecarga e Incerteza
                                    </span>
                                    <h3 className="mt-4 text-xl sm:text-2xl font-bold text-stone-900">
                                        Continuar no sufoco do dia a dia
                                    </h3>
                                    <ul className="mt-6 space-y-3.5 text-sm sm:text-base text-stone-700">
                                        <li className="flex items-start gap-2.5">
                                            <span className="mt-1 flex size-4 shrink-0 items-center justify-center rounded-full bg-stone-300 text-stone-700 text-xs font-bold">
                                                ×
                                            </span>
                                            <span>
                                                <strong className="text-stone-900">Mensagens picadas:</strong> responder WhatsApp até tarde da noite tentando conciliar horários.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-2.5">
                                            <span className="mt-1 flex size-4 shrink-0 items-center justify-center rounded-full bg-stone-300 text-stone-700 text-xs font-bold">
                                                ×
                                            </span>
                                            <span>
                                                <strong className="text-stone-900">Furos e prejuízo:</strong> clientes faltando de última hora e deixando a cadeira vazia sem aviso.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-2.5">
                                            <span className="mt-1 flex size-4 shrink-0 items-center justify-center rounded-full bg-stone-300 text-stone-700 text-xs font-bold">
                                                ×
                                            </span>
                                            <span>
                                                <strong className="text-stone-900">Fins de semana perdidos:</strong> horas somando notas e recalculando comissões na ponta do lápis.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-2.5">
                                            <span className="mt-1 flex size-4 shrink-0 items-center justify-center rounded-full bg-stone-300 text-stone-700 text-xs font-bold">
                                                ×
                                            </span>
                                            <span>
                                                <strong className="text-stone-900">Insegurança financeira:</strong> terminar o mês sem saber para onde foi o dinheiro e sem previsão de lucro.
                                            </span>
                                        </li>
                                    </ul>
                                    <div className="mt-6 rounded-2xl border border-stone-200 bg-stone-200/70 p-3.5 text-xs font-semibold text-stone-800">
                                        ⚠️ Mais um ano trabalhando sem parar e sem ver o dinheiro sobrar na conta.
                                    </div>
                                </div>

                                {/* Opção 2: Paz e Controle */}
                                <div className="rounded-3xl border-2 border-[#3167d8] bg-linear-to-b from-white to-[#edf2ff]/70 p-6 sm:p-8 md:p-10 shadow-xl">
                                    <span className="inline-block rounded-full bg-[#3167d8] px-3 py-1 text-xs font-bold text-white">
                                        Rotina com Paz, Ordem e Lucro
                                    </span>
                                    <h3 className="mt-4 text-xl sm:text-2xl font-bold text-[#17252a]">
                                        Ter um espaço profissional e organizado
                                    </h3>
                                    <ul className="mt-6 space-y-3.5 text-sm sm:text-base text-[#525f5a]">
                                        <li className="flex items-start gap-2.5">
                                            <Check className="mt-1 size-4 shrink-0 text-emerald-600 stroke-[3]" />
                                            <span>
                                                <strong className="text-[#17252a]">Site próprio 24h:</strong> seus clientes agendam sozinhos a qualquer hora, valorizando sua marca.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-2.5">
                                            <Check className="mt-1 size-4 shrink-0 text-emerald-600 stroke-[3]" />
                                            <span>
                                                <strong className="text-[#17252a]">Lembretes automáticos:</strong> fim definitivo dos furos com notificações diretas no WhatsApp.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-2.5">
                                            <Check className="mt-1 size-4 shrink-0 text-emerald-600 stroke-[3]" />
                                            <span>
                                                <strong className="text-[#17252a]">Comissões transparentes:</strong> relatórios prontos em segundos, sem estresse com colaboradores.
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-2.5">
                                            <Check className="mt-1 size-4 shrink-0 text-emerald-600 stroke-[3]" />
                                            <span>
                                                <strong className="text-[#17252a]">Faturamento previsível:</strong> receita garantida no dia 1º do mês com pacotes e planos mensais.
                                            </span>
                                        </li>
                                    </ul>
                                    <div className="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-3.5 text-xs font-semibold text-emerald-800">
                                        ✅ Mais tempo com sua família, valorização da sua marca e tranquilidade financeira.
                                    </div>
                                </div>
                            </div>

                            {/* Frase de Impacto e CTA */}
                            <div className="mt-12 sm:mt-14 text-center">
                                <p className="font-display text-xl font-extrabold text-[#17252a] sm:text-2xl">
                                    Qual dessas duas rotinas você escolhe para
                                    o seu negócio a partir de hoje?
                                </p>
                                <div className="mt-6 flex justify-center">
                                    <Link
                                        href={register()}
                                        className="group inline-flex w-full sm:w-auto items-center justify-center gap-3 rounded-2xl bg-[#3167d8] px-8 py-4 text-base sm:text-lg font-bold text-white shadow-xl shadow-[#3167d8]/20 transition-all hover:bg-[#2551b3] hover:shadow-2xl active:scale-98"
                                    >
                                        <span>
                                            Quero ter paz e controle no meu espaço
                                        </span>
                                        <ArrowRight className="size-5 transition-transform group-hover:translate-x-1" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* =========================================================
                        DOBRA 10: CHAMADA FINAL (Fechamento sem Risco)
                    ========================================================= */}
                    <section className="relative overflow-hidden bg-[#17252a] py-14 sm:py-20 lg:py-28 text-white">
                        <div className="relative z-10 mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                            {/* Título de Fechamento */}
                            <h2 className="font-display text-2xl font-extrabold tracking-tight sm:text-3xl lg:text-4xl">
                                Dê o primeiro passo para ter mais tempo livre e
                                um espaço muito mais lucrativo.
                            </h2>

                            {/* Subtítulo */}
                            <p className="mx-auto mt-5 sm:mt-6 max-w-2xl text-base leading-relaxed text-stone-300 sm:text-lg">
                                Comece seu teste agora mesmo em menos de 2
                                minutos. Veja como é fácil organizar sua agenda,
                                sua equipe e suas finanças.
                            </p>

                            {/* Botão Principal de Ação (CTA Final) */}
                            <div className="mt-8 sm:mt-10 flex justify-center">
                                <Link
                                    href={register()}
                                    className="group inline-flex w-full sm:w-auto items-center justify-center gap-3 rounded-2xl bg-[#efa83f] px-9 py-4 sm:py-5 text-lg sm:text-xl font-bold text-[#17252a] shadow-2xl shadow-[#efa83f]/30 transition-all hover:bg-[#f3b55c] active:scale-98"
                                >
                                    <span>
                                        👉 Criar Minha Conta Grátis Agora
                                    </span>
                                    <ArrowRight className="size-6 transition-transform group-hover:translate-x-1" />
                                </Link>
                            </div>

                            {/* Garantia e Micro-chamada de Alívio */}
                            <div className="mt-6 flex items-center justify-center gap-2 text-xs sm:text-sm font-semibold text-stone-300">
                                <ShieldCheck className="size-4 sm:size-5 text-emerald-400 shrink-0" />
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
