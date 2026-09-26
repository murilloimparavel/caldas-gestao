import { Head } from '@inertiajs/react';
import { MetaPixel, trackMetaLead } from '@/components/meta-pixel';

interface BarberConversationProps {
    metaPixelId?: string | null;
}

const WHATSAPP_HREF =
    'https://wa.me/5564992697946?text=Oi!%20Quero%20descobrir%20como%20usar%20o%20aplicativo%20para%20organizar%20minha%20barbearia.';

export default function BarberConversation({
    metaPixelId,
}: BarberConversationProps) {
    return (
        <>
            <Head title="Organize sua barbearia" />
            <MetaPixel pixelId={metaPixelId} viewContentDelayMs={30_000} />
            <style>{`
                @keyframes whatsapp-cta-pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.055); }
                }
            `}</style>
            <main className="min-h-screen bg-white px-5 py-6 text-[#171717] sm:px-6">
                <div className="mx-auto flex min-h-[calc(100svh-3rem)] max-w-[440px] flex-col items-center text-center">
                    <p className="w-full text-xs font-bold tracking-[0.12em] uppercase">
                        Para donos de barbearia e salão
                    </p>

                    <div className="my-auto py-16">
                        <h1 className="mx-auto max-w-[380px] text-[clamp(2.25rem,10.5vw,4.5rem)] leading-[0.96] font-black tracking-[-0.075em]">
                            Enquanto você corta, sua agenda fica organizada e
                            seu dinheiro sob controle.
                        </h1>
                        <p className="mx-auto mt-6 max-w-[320px] text-base leading-6 text-[#5f5f5f]">
                            O aplicativo Caldas Gestão organiza sua agenda e seu
                            financeiro para você saber quem vem, quanto entrou e
                            quanto realmente sobrou no mês.
                        </p>
                        <ul className="mx-auto mt-7 max-w-[340px] space-y-3 text-left text-sm leading-5 font-medium">
                            <li className="flex items-start gap-3">
                                <span
                                    className="mt-1.5 size-1.5 shrink-0 rounded-full bg-[#171717]"
                                    aria-hidden="true"
                                />
                                <span>
                                    Seu cliente agenda enquanto você atende
                                </span>
                            </li>
                            <li className="flex items-start gap-3">
                                <span
                                    className="mt-1.5 size-1.5 shrink-0 rounded-full bg-[#171717]"
                                    aria-hidden="true"
                                />
                                <span>
                                    Acompanhe o próximo horário com clareza
                                </span>
                            </li>
                            <li className="flex items-start gap-3">
                                <span
                                    className="mt-1.5 size-1.5 shrink-0 rounded-full bg-[#171717]"
                                    aria-hidden="true"
                                />
                                <span>
                                    Veja se o movimento está virando dinheiro de
                                    verdade
                                </span>
                            </li>
                        </ul>

                        <a
                            href={WHATSAPP_HREF}
                            onClick={trackMetaLead}
                            className="mx-auto mt-8 flex min-h-14 w-full max-w-[360px] items-center justify-center gap-2.5 rounded-xl bg-[#25D366] px-5 text-center text-base font-bold text-[#0b2b18] shadow-[0_3px_0_#159447] transition-transform active:translate-y-0.5 motion-safe:animate-[whatsapp-cta-pulse_1.8s_ease-in-out_infinite]"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                className="size-5 fill-current"
                                aria-hidden="true"
                            >
                                <path d="M20.52 3.48A11.86 11.86 0 0 0 12.08 0C5.52 0 .18 5.34.18 11.9c0 2.1.55 4.15 1.6 5.96L.08 24l6.28-1.65a11.9 11.9 0 0 0 5.72 1.46h.01c6.56 0 11.9-5.34 11.9-11.9 0-3.18-1.24-6.17-3.47-8.43Zm-8.44 18.3h-.01a9.88 9.88 0 0 1-5.03-1.38l-.36-.21-3.73.98 1-3.64-.23-.37a9.87 9.87 0 0 1-1.52-5.26c0-5.45 4.44-9.88 9.89-9.88a9.82 9.82 0 0 1 7 2.9 9.86 9.86 0 0 1 2.89 7c0 5.44-4.44 9.87-9.89 9.87Zm5.42-7.4c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.47-.88-.78-1.47-1.74-1.64-2.04-.17-.3-.02-.46.13-.61.14-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.49 0 1.47 1.07 2.89 1.22 3.09.15.2 2.1 3.2 5.09 4.49.71.31 1.26.49 1.69.63.71.23 1.35.2 1.86.12.57-.08 1.76-.72 2.01-1.42.25-.7.25-1.3.17-1.42-.07-.12-.27-.2-.57-.35Z" />
                            </svg>
                            Quero conhecer o sistema
                        </a>
                        <p className="pt-4 text-center text-[11px] text-[#8a8a8a]">
                            Conversa rápida e sem compromisso.
                        </p>
                    </div>
                </div>
            </main>
        </>
    );
}
