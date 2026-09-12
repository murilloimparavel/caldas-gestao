import { TrendingUp, TrendingDown, User } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import type { ProfessionalPerformance } from '../types';

type ProfessionalPerformanceTableProps = {
    data?: ProfessionalPerformance[];
};

export function ProfessionalPerformanceTable({
    data = [],
}: ProfessionalPerformanceTableProps) {
    const hasData = data.length > 0;

    const formatCurrency = (cents?: number | string) => {
        if (typeof cents === 'string') {
            return cents;
        }

        if (typeof cents === 'number') {
            return (cents / 100).toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL',
            });
        }

        return 'R$ 0,00';
    };

    return (
        <Card className="border-border/60">
            <CardHeader className="pb-3">
                <CardTitle className="text-base font-semibold">
                    Desempenho por Profissional
                </CardTitle>
                <CardDescription className="text-xs">
                    Atendimentos executados e ticket médio individual
                </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
                {!hasData ? (
                    <div className="flex h-36 items-center justify-center p-4 text-xs text-muted-foreground">
                        Nenhum profissional com atendimentos no período
                    </div>
                ) : (
                    <>
                        <div className="grid gap-2 p-3 sm:hidden">
                            {data.map((prof) => {
                                const totalServices =
                                    prof.services_count ??
                                    prof.totalServices ??
                                    0;

                                const variation =
                                    prof.variation_percentage ??
                                    prof.changePercentage ??
                                    0;
                                const avgTicket =
                                    prof.average_ticket_cents !== undefined
                                        ? formatCurrency(
                                              prof.average_ticket_cents,
                                          )
                                        : formatCurrency(prof.averageTicket);
                                const initials = prof.name
                                    .split(' ')
                                    .map((n) => n[0])
                                    .join('')
                                    .slice(0, 2)
                                    .toUpperCase();

                                return (
                                    <article
                                        key={prof.id}
                                        className="rounded-xl border border-border/60 bg-muted/20 p-3"
                                    >
                                        <div className="flex items-center gap-2.5">
                                            <Avatar className="size-9 border border-border/50">
                                                {(prof.avatar_url ??
                                                    prof.avatarUrl) && (
                                                    <AvatarImage
                                                        src={
                                                            prof.avatar_url ??
                                                            prof.avatarUrl
                                                        }
                                                        alt=""
                                                    />
                                                )}
                                                <AvatarFallback className="bg-primary/10 text-[10px] font-semibold text-primary">
                                                    {initials || (
                                                        <User className="size-3.5" />
                                                    )}
                                                </AvatarFallback>
                                            </Avatar>
                                            <span className="min-w-0 flex-1 truncate text-sm font-semibold text-foreground">
                                                {prof.name}
                                            </span>
                                            <span
                                                className={`inline-flex items-center gap-1 text-xs font-semibold ${variation >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'}`}
                                            >
                                                {variation >= 0 ? (
                                                    <TrendingUp
                                                        className="size-3"
                                                        aria-hidden="true"
                                                    />
                                                ) : (
                                                    <TrendingDown
                                                        className="size-3"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                {variation >= 0 ? '+' : ''}
                                                {variation}%
                                            </span>
                                        </div>
                                        <dl className="mt-3 grid grid-cols-2 gap-2 text-xs">
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Atendimentos
                                                </dt>
                                                <dd className="mt-0.5 font-semibold text-foreground">
                                                    {totalServices}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Ticket médio
                                                </dt>
                                                <dd className="mt-0.5 font-semibold text-foreground">
                                                    {avgTicket}
                                                </dd>
                                            </div>
                                        </dl>
                                    </article>
                                );
                            })}
                        </div>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-y border-border/50 bg-muted/30 text-muted-foreground">
                                        <th className="px-4 py-2.5 font-medium">
                                            Profissional
                                        </th>
                                        <th className="px-4 py-2.5 text-center font-medium">
                                            Atendimentos
                                        </th>
                                        <th className="px-4 py-2.5 text-center font-medium">
                                            Variação %
                                        </th>
                                        <th className="px-4 py-2.5 text-right font-medium">
                                            Ticket Médio
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/40">
                                    {data.map((prof) => {
                                        const avatarUrl =
                                            prof.avatar_url ??
                                            prof.avatarUrl ??
                                            '';
                                        const totalServices =
                                            prof.services_count ??
                                            prof.totalServices ??
                                            0;
                                        const variation =
                                            prof.variation_percentage ??
                                            prof.changePercentage ??
                                            0;
                                        const avgTicket =
                                            prof.average_ticket_cents !==
                                            undefined
                                                ? formatCurrency(
                                                      prof.average_ticket_cents,
                                                  )
                                                : formatCurrency(
                                                      prof.averageTicket,
                                                  );

                                        const initials = prof.name
                                            .split(' ')
                                            .map((n) => n[0])
                                            .join('')
                                            .slice(0, 2)
                                            .toUpperCase();

                                        const isUp = variation >= 0;

                                        return (
                                            <tr
                                                key={prof.id}
                                                className="transition-colors hover:bg-muted/20"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2.5">
                                                        <Avatar className="size-8 border border-border/50">
                                                            {avatarUrl && (
                                                                <AvatarImage
                                                                    src={
                                                                        avatarUrl
                                                                    }
                                                                    alt={
                                                                        prof.name
                                                                    }
                                                                />
                                                            )}
                                                            <AvatarFallback className="bg-primary/10 text-[10px] font-semibold text-primary">
                                                                {initials || (
                                                                    <User className="size-3.5" />
                                                                )}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <span className="font-medium text-foreground">
                                                            {prof.name}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-center font-semibold text-foreground">
                                                    {totalServices}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span
                                                        className={`inline-flex items-center gap-1 font-semibold ${
                                                            isUp
                                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                                : 'text-red-600 dark:text-red-400'
                                                        }`}
                                                    >
                                                        {isUp ? (
                                                            <TrendingUp className="size-3" />
                                                        ) : (
                                                            <TrendingDown className="size-3" />
                                                        )}
                                                        {isUp ? '+' : ''}
                                                        {variation}%
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-right font-medium text-foreground">
                                                    {avgTicket}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
