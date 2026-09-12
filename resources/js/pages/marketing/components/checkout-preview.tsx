import {
    CheckCircle2,
    CreditCard,
    DollarSign,
    Package,
    QrCode,
    Receipt,
    Scissors,
    Sparkles,
} from 'lucide-react';

export function CheckoutPreview() {
    return (
        <div className="flex flex-col gap-5">
            {/* Comanda Header */}
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-4">
                <div className="flex items-center gap-3">
                    <div className="flex size-9 items-center justify-center rounded-xl bg-[#C8FF3D]/10 text-[#C8FF3D]">
                        <Receipt className="size-5" />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-bold text-white">Comanda #1048</span>
                            <span className="rounded-full border border-[#C8FF3D]/30 bg-[#C8FF3D]/10 px-2 py-0.5 text-3xs font-bold text-[#C8FF3D]">
                                Aberta
                            </span>
                        </div>
                        <p className="text-3xs text-[#A9A79D]">Cliente: Camila Moreira • Atendimento das 09:30</p>
                    </div>
                </div>

                <div className="text-right">
                    <span className="text-3xs font-medium text-[#A9A79D] uppercase">Total Comanda</span>
                    <p className="text-lg font-bold text-[#C8FF3D]">R$ 300,00</p>
                </div>
            </div>

            {/* Items and Checkout split view */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                {/* Items List */}
                <div className="flex flex-col gap-2.5 lg:col-span-7">
                    <span className="text-3xs font-bold tracking-wider text-[#A9A79D] uppercase">
                        Serviços & Produtos Lançados
                    </span>

                    <div className="flex items-center justify-between rounded-lg border border-white/10 bg-white/[0.03] p-3">
                        <div className="flex items-center gap-2.5">
                            <div className="flex size-7 items-center justify-center rounded-md bg-sky-500/20 text-sky-400">
                                <Scissors className="size-3.5" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-white">Corte & Escova Modelada</p>
                                <p className="text-3xs text-[#A9A79D]">Profissional: Juliana Costa</p>
                            </div>
                        </div>
                        <span className="text-xs font-bold text-white">R$ 140,00</span>
                    </div>

                    <div className="flex items-center justify-between rounded-lg border border-white/10 bg-white/[0.03] p-3">
                        <div className="flex items-center gap-2.5">
                            <div className="flex size-7 items-center justify-center rounded-md bg-violet-500/20 text-violet-400">
                                <Sparkles className="size-3.5" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-white">Tratamento Reconstrutor</p>
                                <p className="text-3xs text-[#A9A79D]">Profissional: Juliana Costa</p>
                            </div>
                        </div>
                        <span className="text-xs font-bold text-white">R$ 95,00</span>
                    </div>

                    <div className="flex items-center justify-between rounded-lg border border-white/10 bg-white/[0.03] p-3">
                        <div className="flex items-center gap-2.5">
                            <div className="flex size-7 items-center justify-center rounded-md bg-amber-500/20 text-amber-400">
                                <Package className="size-3.5" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-white">Óleo Nutritivo 60ml</p>
                                <p className="text-3xs text-emerald-400">Baixa automática em estoque</p>
                            </div>
                        </div>
                        <span className="text-xs font-bold text-white">R$ 85,00</span>
                    </div>
                </div>

                {/* Totals & Payment Methods */}
                <div className="flex flex-col justify-between rounded-xl border border-white/10 bg-white/[0.02] p-4 lg:col-span-5">
                    <div className="space-y-2.5 border-b border-white/10 pb-3 text-xs">
                        <div className="flex justify-between text-[#A9A79D]">
                            <span>Subtotal</span>
                            <span>R$ 320,00</span>
                        </div>
                        <div className="flex justify-between text-emerald-400">
                            <span>Desconto Fidelidade</span>
                            <span>- R$ 20,00</span>
                        </div>
                        <div className="flex justify-between pt-1 text-sm font-bold text-white">
                            <span>Total Líquido</span>
                            <span className="text-[#C8FF3D]">R$ 300,00</span>
                        </div>
                    </div>

                    {/* Commissions Preview */}
                    <div className="my-3 rounded-lg border border-white/10 bg-white/5 p-2.5 text-3xs">
                        <div className="flex items-center justify-between font-semibold text-[#A9A79D]">
                            <span>Comissão Juliana (40%)</span>
                            <span className="text-white">R$ 86,00</span>
                        </div>
                        <p className="mt-1 text-[#777A70]">Calculada e vinculada ao fechamento do caixa.</p>
                    </div>

                    {/* Payment CTA */}
                    <div className="space-y-2">
                        <div className="grid grid-cols-3 gap-1.5 text-3xs font-semibold">
                            <button
                                type="button"
                                className="flex flex-col items-center gap-1 rounded-lg border border-[#C8FF3D] bg-[#C8FF3D]/10 py-2 text-[#C8FF3D]"
                            >
                                <QrCode className="size-4" />
                                <span>PIX</span>
                            </button>
                            <button
                                type="button"
                                className="flex flex-col items-center gap-1 rounded-lg border border-white/10 bg-white/5 py-2 text-[#A9A79D] transition hover:text-white"
                            >
                                <CreditCard className="size-4" />
                                <span>Cartão</span>
                            </button>
                            <button
                                type="button"
                                className="flex flex-col items-center gap-1 rounded-lg border border-white/10 bg-white/5 py-2 text-[#A9A79D] transition hover:text-white"
                            >
                                <DollarSign className="size-4" />
                                <span>Dinheiro</span>
                            </button>
                        </div>

                        <button
                            type="button"
                            className="flex w-full items-center justify-center gap-2 rounded-lg bg-[#C8FF3D] py-2.5 text-xs font-bold text-[#0A0C0B] transition hover:bg-[#F2EFE7]"
                        >
                            <CheckCircle2 className="size-4" />
                            <span>Fechar Comanda</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default CheckoutPreview;
