import { Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AdminAuthLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { branding } = usePage<{
        branding: { name: string; logoUrl?: string | null };
    }>().props;

    return (
        <div className="min-h-dvh bg-[#071417] text-[#edf6f1] lg:grid lg:grid-cols-[1.05fr_0.95fr]">
            <aside className="relative hidden overflow-hidden border-r border-[#244247] lg:flex lg:flex-col lg:justify-between lg:p-12">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(73,169,142,0.22),transparent_35%),radial-gradient(circle_at_80%_80%,rgba(241,180,81,0.15),transparent_30%)]" />
                <div className="relative z-10 flex items-center gap-3">
                    <AppLogoIcon className="size-10 text-[#49a98e]" />
                    <span className="font-mono text-xs tracking-[0.24em] text-[#b2c9c0] uppercase">
                        {branding.name}
                    </span>
                </div>

                <div className="relative z-10 max-w-lg space-y-8">
                    <div className="flex size-12 items-center justify-center rounded-2xl border border-[#39645d] bg-[#102b2b] text-[#f1b451] shadow-[0_0_40px_rgba(73,169,142,0.18)]">
                        <ShieldCheck className="size-6" />
                    </div>
                    <div className="space-y-4">
                        <p className="font-mono text-xs tracking-[0.28em] text-[#f1b451] uppercase">
                            SaaS control room
                        </p>
                        <h1 className="max-w-xl text-4xl leading-tight font-semibold tracking-[-0.04em] text-balance xl:text-5xl">
                            Operação central para cada cliente da plataforma.
                        </h1>
                        <p className="max-w-md text-base leading-7 text-[#abc1b9]">
                            Acompanhe contas, planos e sinais de cobrança em um
                            espaço reservado para a equipe de administração.
                        </p>
                    </div>
                </div>

                <p className="relative z-10 font-mono text-[0.65rem] tracking-[0.18em] text-[#6f8c83] uppercase">
                    acesso restrito · sessão protegida
                </p>
            </aside>

            <main className="flex min-h-dvh items-center justify-center px-6 py-10 sm:px-10 lg:px-16">
                <div className="w-full max-w-md space-y-8">
                    <div className="flex items-center justify-between lg:hidden">
                        <Link href={home()} className="flex items-center gap-3">
                            <AppLogoIcon className="size-9 text-[#49a98e]" />
                            <span className="font-mono text-xs tracking-[0.18em] text-[#b2c9b0] uppercase">
                                {branding.name}
                            </span>
                        </Link>
                        <ShieldCheck className="size-5 text-[#f1b451]" />
                    </div>

                    <div className="space-y-3">
                        <p className="font-mono text-xs tracking-[0.24em] text-[#f1b451] uppercase">
                            painel administrativo
                        </p>
                        <h2 className="text-3xl font-semibold tracking-[-0.035em] text-[#edf6f1]">
                            {title}
                        </h2>
                        <p className="text-sm leading-6 text-[#9cb5ac]">
                            {description}
                        </p>
                    </div>

                    <div className="rounded-3xl border border-[#244247] bg-[#0d2024]/80 p-6 shadow-[0_24px_80px_rgba(0,0,0,0.28)] backdrop-blur sm:p-8">
                        {children}
                    </div>
                </div>
            </main>
        </div>
    );
}
