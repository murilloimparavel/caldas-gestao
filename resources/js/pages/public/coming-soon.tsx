import { Head } from '@inertiajs/react';
import { ArrowUpRight, Sparkles } from 'lucide-react';

type Branding = {
    name: string;
    logoUrl?: string | null;
    primaryColor?: string | null;
    accentColor?: string | null;
};

export default function ComingSoon({ branding }: { branding: Branding }) {
    const primaryColor = branding.primaryColor ?? '#2f65d9';
    const accentColor = branding.accentColor ?? '#efaa43';

    return (
        <>
            <Head title={`${branding.name} — Em breve`} />
            <main className="relative flex min-h-dvh flex-col overflow-hidden bg-[#102024] text-[#f8f4ec]">
                <div
                    className="pointer-events-none absolute -top-48 -right-32 size-[34rem] rounded-full opacity-25 blur-3xl"
                    style={{ backgroundColor: accentColor }}
                />
                <div
                    className="pointer-events-none absolute -bottom-56 -left-40 size-[32rem] rounded-full opacity-30 blur-3xl"
                    style={{ backgroundColor: primaryColor }}
                />

                <div className="relative mx-auto flex w-full max-w-6xl grow flex-col px-6 py-7 sm:px-10 lg:px-16">
                    <header className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            {branding.logoUrl ? (
                                <img
                                    src={branding.logoUrl}
                                    alt=""
                                    className="size-10 rounded-xl object-contain"
                                />
                            ) : (
                                <div
                                    className="flex size-10 items-center justify-center rounded-xl text-lg font-bold text-white"
                                    style={{ backgroundColor: primaryColor }}
                                >
                                    {branding.name.charAt(0).toUpperCase()}
                                </div>
                            )}
                            <span className="text-sm font-semibold tracking-wide text-white/90">
                                {branding.name}
                            </span>
                        </div>
                        <span className="rounded-full border border-white/15 px-3 py-1.5 text-xs tracking-[0.16em] text-white/55 uppercase">
                            Em construção
                        </span>
                    </header>

                    <section className="flex grow items-center py-20 sm:py-28">
                        <div className="max-w-3xl">
                            <div className="mb-8 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm text-white/70 backdrop-blur">
                                <Sparkles
                                    className="size-4"
                                    style={{ color: accentColor }}
                                />
                                Um novo espaço está chegando
                            </div>
                            <h1 className="max-w-3xl text-5xl leading-[0.98] font-semibold tracking-[-0.055em] text-balance sm:text-7xl lg:text-8xl">
                                Grandes coisas estão a caminho.
                            </h1>
                            <p className="mt-8 max-w-xl text-lg leading-8 text-white/60 sm:text-xl">
                                Estamos preparando uma experiência especial para
                                você. Em breve, este será o ponto de encontro
                                para conhecer nossos serviços e novidades.
                            </p>
                            <div className="mt-10 flex items-center gap-3 text-sm text-white/45">
                                <span
                                    className="size-2 rounded-full"
                                    style={{ backgroundColor: accentColor }}
                                />
                                Este domínio está conectado e funcionando.
                            </div>
                        </div>
                    </section>

                    <footer className="flex flex-col gap-3 border-t border-white/10 pt-5 text-xs text-white/40 sm:flex-row sm:items-center sm:justify-between">
                        <span>
                            © {new Date().getFullYear()} {branding.name}
                        </span>
                        <a
                            href="https://caldasindica.com"
                            className="inline-flex items-center gap-1 transition-colors hover:text-white/75"
                            rel="noreferrer"
                            target="_blank"
                        >
                            Desenvolvido por Caldas Indica · Caldas Gestão
                            <ArrowUpRight className="size-3" />
                        </a>
                    </footer>
                </div>
            </main>
        </>
    );
}

ComingSoon.layout = null;
