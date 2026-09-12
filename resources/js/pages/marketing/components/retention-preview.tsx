import {
    ArrowUpRight,
    Check,
    Clock,
    MessageCircle,
    RefreshCw,
    Sparkles,
    TrendingUp,
    Users,
} from 'lucide-react';

export function RetentionPreview() {
    return (
        <div className="flex flex-col gap-5">
            {/* Top Retention Metrics */}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div className="flex flex-col rounded-xl border border-white/10 bg-white/[0.02] p-3.5">
                    <div className="flex items-center justify-between text-3xs text-[#A9A79D]">
                        <span>Clientes Ativos</span>
                        <Users className="size-3.5 text-[#C8FF3D]" />
                    </div>
                    <p className="mt-2 text-xl font-bold text-white">348</p>
                    <span className="mt-1 flex items-center gap-1 text-3xs font-semibold text-emerald-400">
                        <TrendingUp className="size-3" /> +14% neste mês
                    </span>
                </div>

                <div className="flex flex-col rounded-xl border border-white/10 bg-white/[0.02] p-3.5">
                    <div className="flex items-center justify-between text-3xs text-[#A9A79D]">
                        <span>Ciclo Médio de Retorno</span>
                        <Clock className="size-3.5 text-cyan-400" />
                    </div>
                    <p className="mt-2 text-xl font-bold text-white">21 dias</p>
                    <span className="mt-1 text-3xs text-[#A9A79D]">era 38 dias sem lembretes</span>
                </div>

                <div className="flex flex-col rounded-xl border border-white/10 bg-white/[0.02] p-3.5">
                    <div className="flex items-center justify-between text-3xs text-[#A9A79D]">
                        <span>Receita Previsível</span>
                        <RefreshCw className="size-3.5 text-[#C8FF3D]" />
                    </div>
                    <p className="mt-2 text-xl font-bold text-[#C8FF3D]">R$ 8.450/mês</p>
                    <span className="mt-1 text-3xs text-[#A9A79D]">em assinaturas e pacotes</span>
                </div>
            </div>

            {/* Smart Retention Suggestions Table */}
            <div className="flex flex-col gap-3">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Sparkles className="size-4 text-[#C8FF3D]" />
                        <span className="text-xs font-bold text-white">
                            Oportunidades de Retorno (Inteligência Caldas)
                        </span>
                    </div>
                    <span className="rounded-full bg-[#C8FF3D]/10 px-2 py-0.5 text-3xs font-bold text-[#C8FF3D]">
                        18 clientes em momento ideal
                    </span>
                </div>

                <div className="flex flex-col gap-2.5">
                    {/* Item 1 - Inactive approaching threshold */}
                    <div className="flex flex-col justify-between gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 sm:flex-row sm:items-center">
                        <div className="flex items-start gap-3">
                            <div className="flex size-7 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-xs font-bold text-amber-400">
                                MD
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <p className="text-xs font-bold text-white">Mariana Duarte</p>
                                    <span className="rounded bg-amber-500/20 px-1.5 py-0.5 text-3xs font-semibold text-amber-300">
                                        38 dias sem agendar
                                    </span>
                                </div>
                                <p className="mt-0.5 text-3xs text-[#D4D0C5]">
                                    Costuma retocar mechas a cada 30 dias com Juliana Costa
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            className="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-md bg-[#25D366] px-3 py-1.5 text-3xs font-bold text-black transition hover:bg-[#20bd5a]"
                        >
                            <MessageCircle className="size-3.5" />
                            <span>Enviar Lembrete WhatsApp</span>
                        </button>
                    </div>

                    {/* Item 2 - Package remaining sessions */}
                    <div className="flex flex-col justify-between gap-3 rounded-lg border border-white/10 bg-white/[0.03] p-3 sm:flex-row sm:items-center">
                        <div className="flex items-start gap-3">
                            <div className="flex size-7 shrink-0 items-center justify-center rounded-full bg-sky-500/20 text-xs font-bold text-sky-400">
                                CE
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <p className="text-xs font-bold text-white">Carlos Eduardo</p>
                                    <span className="rounded bg-sky-500/20 px-1.5 py-0.5 text-3xs font-semibold text-sky-300">
                                        Pacote 4x Barba (3/4 usadas)
                                    </span>
                                </div>
                                <p className="mt-0.5 text-3xs text-[#A9A79D]">
                                    Restam 1 sessão contratada • Validade até 30/Nov
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            className="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-md border border-white/15 bg-white/5 px-3 py-1.5 text-3xs font-bold text-white transition hover:bg-white/10"
                        >
                            <span>Agendar Última Sessão</span>
                            <ArrowUpRight className="size-3" />
                        </button>
                    </div>

                    {/* Item 3 - Recurring Subscriber */}
                    <div className="flex flex-col justify-between gap-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 sm:flex-row sm:items-center">
                        <div className="flex items-start gap-3">
                            <div className="flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-xs font-bold text-emerald-400">
                                PN
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <p className="text-xs font-bold text-white">Patrícia Nogueira</p>
                                    <span className="rounded bg-emerald-500/20 px-1.5 py-0.5 text-3xs font-semibold text-emerald-300">
                                        Clube VIP Mensal (Ativo)
                                    </span>
                                </div>
                                <p className="mt-0.5 text-3xs text-[#D4D0C5]">
                                    Próxima mensalidade: R$ 240,00 no dia 05 • Débito automático PIX
                                </p>
                            </div>
                        </div>
                        <span className="flex items-center gap-1 text-3xs font-semibold text-emerald-400">
                            <Check className="size-3.5" /> Renovação Garantida
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default RetentionPreview;
