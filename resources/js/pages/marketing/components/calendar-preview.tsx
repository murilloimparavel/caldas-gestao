import { ChevronLeft, ChevronRight, Clock, Lock, Plus } from 'lucide-react';

export function CalendarPreview() {
    return (
        <div className="flex flex-col gap-4">
            {/* Top Toolbar Preview */}
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-4">
                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-1 rounded-lg border border-white/10 bg-white/5 p-1">
                        <button
                            type="button"
                            className="rounded p-1 text-[#A9A79D] transition hover:bg-white/10 hover:text-white"
                            aria-label="Dia anterior"
                        >
                            <ChevronLeft className="size-4" />
                        </button>
                        <button
                            type="button"
                            className="rounded p-1 text-[#A9A79D] transition hover:bg-white/10 hover:text-white"
                            aria-label="Próximo dia"
                        >
                            <ChevronRight className="size-4" />
                        </button>
                    </div>
                    <div>
                        <span className="text-sm font-bold text-[#F2EFE7]">
                            Hoje, 14 Out
                        </span>
                        <span className="ml-2 text-3xs font-medium text-[#C8FF3D]">
                            32 agendamentos
                        </span>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <div className="hidden rounded-lg border border-white/10 bg-white/5 p-1 text-3xs font-medium text-[#A9A79D] sm:flex">
                        <span className="rounded bg-white/10 px-2.5 py-1 text-white">
                            Dia
                        </span>
                        <span className="px-2.5 py-1">Semana</span>
                        <span className="px-2.5 py-1">Mês</span>
                    </div>
                    <div className="flex items-center gap-1.5 rounded-lg bg-[#C8FF3D] px-3 py-1.5 text-xs font-bold text-[#0A0C0B]">
                        <Plus className="size-3.5" />
                        <span>Novo</span>
                    </div>
                </div>
            </div>

            {/* Calendar Multi-Column Grid */}
            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                {/* Professional 1 */}
                <div className="flex flex-col rounded-xl border border-white/10 bg-white/[0.02] p-3">
                    <div className="flex items-center justify-between border-b border-white/10 pb-2.5">
                        <div className="flex items-center gap-2">
                            <div className="flex size-7 items-center justify-center rounded-full bg-[#C8FF3D]/20 text-xs font-bold text-[#C8FF3D]">
                                MS
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-[#F2EFE7]">
                                    Matheus Silva
                                </p>
                                <p className="text-3xs text-[#A9A79D]">
                                    Master Barber
                                </p>
                            </div>
                        </div>
                        <span className="text-3xs font-medium text-[#A9A79D]">
                            6 horários
                        </span>
                    </div>

                    <div className="mt-3 flex flex-col gap-2.5">
                        {/* Appointment 1 */}
                        <div className="group relative rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-2.5 transition hover:border-emerald-400">
                            <div className="flex items-center justify-between text-3xs font-medium text-emerald-400">
                                <span>09:00 - 09:45</span>
                                <span className="rounded-full bg-emerald-500/20 px-1.5 py-0.5 text-3xs font-semibold">
                                    Concluído
                                </span>
                            </div>
                            <p className="mt-1 text-xs font-bold text-white">
                                Lucas Andrade
                            </p>
                            <p className="text-3xs text-[#D4D0C5]">
                                Corte Degradê + Barba Alinhada
                            </p>
                            <div className="mt-2 flex items-center justify-between text-3xs text-emerald-300/80">
                                <span>R$ 85,00</span>
                                <span className="flex items-center gap-1">
                                    <Clock className="size-3" /> Pago (PIX)
                                </span>
                            </div>
                        </div>

                        {/* Appointment 2 */}
                        <div className="group relative rounded-lg border border-violet-500/40 bg-violet-500/15 p-2.5 transition hover:border-violet-400">
                            <div className="flex items-center justify-between text-3xs font-medium text-violet-300">
                                <span>10:00 - 11:00</span>
                                <span className="rounded-full bg-violet-500/30 px-1.5 py-0.5 text-3xs font-semibold text-violet-200">
                                    Em atendimento
                                </span>
                            </div>
                            <p className="mt-1 text-xs font-bold text-white">
                                Rodrigo Mello
                            </p>
                            <p className="text-3xs text-[#D4D0C5]">
                                Barboterapia Completa
                            </p>
                            <div className="mt-2 flex items-center justify-between text-3xs text-violet-300/80">
                                <span>R$ 95,00</span>
                                <span className="text-[#C8FF3D]">
                                    Comanda aberta
                                </span>
                            </div>
                        </div>

                        {/* Blocked slot */}
                        <div className="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-2.5 py-2 text-3xs text-[#A9A79D]">
                            <Lock className="size-3 text-[#A9A79D]" />
                            <span>12:00 - 13:00 • Intervalo Almoço</span>
                        </div>
                    </div>
                </div>

                {/* Professional 2 */}
                <div className="flex flex-col rounded-xl border border-white/10 bg-white/[0.02] p-3">
                    <div className="flex items-center justify-between border-b border-white/10 pb-2.5">
                        <div className="flex items-center gap-2">
                            <div className="flex size-7 items-center justify-center rounded-full bg-cyan-400/20 text-xs font-bold text-cyan-400">
                                JC
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-[#F2EFE7]">
                                    Juliana Costa
                                </p>
                                <p className="text-3xs text-[#A9A79D]">
                                    Colorista & Estilo
                                </p>
                            </div>
                        </div>
                        <span className="text-3xs font-medium text-[#A9A79D]">
                            4 horários
                        </span>
                    </div>

                    <div className="mt-3 flex flex-col gap-2.5">
                        {/* Appointment 1 */}
                        <div className="group relative rounded-lg border border-sky-500/30 bg-sky-500/10 p-2.5 transition hover:border-sky-400">
                            <div className="flex items-center justify-between text-3xs font-medium text-sky-400">
                                <span>09:30 - 11:30</span>
                                <span className="rounded-full bg-sky-500/20 px-1.5 py-0.5 text-3xs font-semibold">
                                    Confirmado
                                </span>
                            </div>
                            <p className="mt-1 text-xs font-bold text-white">
                                Camila Moreira
                            </p>
                            <p className="text-3xs text-[#D4D0C5]">
                                Mechas Balayage + Nutrição
                            </p>
                            <div className="mt-2 flex items-center justify-between text-3xs text-sky-300/80">
                                <span>R$ 380,00</span>
                                <span>Google Cal Conectado</span>
                            </div>
                        </div>

                        {/* Appointment 2 */}
                        <div className="group relative rounded-lg border border-amber-500/30 bg-amber-500/10 p-2.5 transition hover:border-amber-400">
                            <div className="flex items-center justify-between text-3xs font-medium text-amber-400">
                                <span>14:00 - 15:00</span>
                                <span className="rounded-full bg-amber-500/20 px-1.5 py-0.5 text-3xs font-semibold">
                                    Agendado
                                </span>
                            </div>
                            <p className="mt-1 text-xs font-bold text-white">
                                Fernanda Dias
                            </p>
                            <p className="text-3xs text-[#D4D0C5]">
                                Escova Modelada + Hidratação
                            </p>
                            <div className="mt-2 flex items-center justify-between text-3xs text-amber-300/80">
                                <span>R$ 130,00</span>
                                <span className="text-amber-300">
                                    Site online
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Professional 3 */}
                <div className="hidden flex-col rounded-xl border border-white/10 bg-white/[0.02] p-3 md:flex">
                    <div className="flex items-center justify-between border-b border-white/10 pb-2.5">
                        <div className="flex items-center gap-2">
                            <div className="flex size-7 items-center justify-center rounded-full bg-amber-400/20 text-xs font-bold text-amber-400">
                                RL
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-[#F2EFE7]">
                                    Rafael Lima
                                </p>
                                <p className="text-3xs text-[#A9A79D]">
                                    Estética Facial
                                </p>
                            </div>
                        </div>
                        <span className="text-3xs font-medium text-[#A9A79D]">
                            5 horários
                        </span>
                    </div>

                    <div className="mt-3 flex flex-col gap-2.5">
                        {/* Appointment 1 */}
                        <div className="group relative rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-2.5 transition hover:border-emerald-400">
                            <div className="flex items-center justify-between text-3xs font-medium text-emerald-400">
                                <span>10:30 - 11:30</span>
                                <span className="rounded-full bg-emerald-500/20 px-1.5 py-0.5 text-3xs font-semibold">
                                    Confirmado
                                </span>
                            </div>
                            <p className="mt-1 text-xs font-bold text-white">
                                Carla Nogueira
                            </p>
                            <p className="text-3xs text-[#D4D0C5]">
                                Limpeza Profunda + Peeling
                            </p>
                            <div className="mt-2 flex items-center justify-between text-3xs text-emerald-300/80">
                                <span>R$ 190,00</span>
                                <span className="text-[#C8FF3D]">
                                    Pacote 2/4
                                </span>
                            </div>
                        </div>

                        {/* Free Slot */}
                        <div className="flex items-center justify-between rounded-lg border border-dashed border-white/15 p-2 text-3xs text-[#777A70] transition hover:border-[#C8FF3D]/40 hover:text-[#C8FF3D]">
                            <span>14:00 Horário Livre</span>
                            <span className="flex items-center gap-1 font-semibold text-[#C8FF3D]">
                                <Plus className="size-3" /> Agendar
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default CalendarPreview;
