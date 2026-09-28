import { Badge } from '@/components/ui/badge';
import { bookingTokens, bookingUi } from './design-tokens';
import type { PublicSettings, Readiness } from '../types';

export function BookingHero({
    unitName,
    unitSlug,
    settings,
    readiness,
}: {
    unitName: string;
    unitSlug: string;
    settings: PublicSettings;
    readiness: Readiness;
}) {
    return (
        <div className={`${bookingUi.hero} ${bookingTokens.space.pageInset}`}>
            <div className="pointer-events-none absolute -top-32 -right-24 size-80 rounded-full bg-amber-300/10 blur-3xl" />
            <div
                className={`relative flex flex-col ${bookingTokens.space.heroContent} lg:flex-row lg:items-end lg:justify-between`}
            >
                <div>
                    <div
                        className={`mb-4 flex items-center gap-3 text-[11px] font-bold tracking-[0.28em] ${bookingTokens.color.accentDark} uppercase`}
                    >
                        <span className="size-2 rounded-full bg-amber-300" />
                        Canal público
                    </div>
                    <h1 className={bookingTokens.type.pageTitle}>
                        Seu agendamento, do seu jeito.
                    </h1>
                    <p
                        className={`mt-3 max-w-xl ${bookingTokens.type.body} leading-6 text-slate-300`}
                    >
                        Configure o link que seus clientes vão usar para
                        escolher serviços, profissionais e horários de
                        {` ${unitName}`}.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        className={`border-0 ${bookingTokens.color.accentSurface} ${bookingTokens.color.accentForeground}`}
                    >
                        {readiness.publishable
                            ? 'Pronto para publicar'
                            : 'Em preparação'}
                    </Badge>
                    <Badge className="border border-white/15 bg-white/10 text-white">
                        /{settings.public_slug ?? unitSlug}
                    </Badge>
                </div>
            </div>
        </div>
    );
}
