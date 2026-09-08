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
                    <div className="overflow-x-auto">
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
                                        prof.avatar_url ?? prof.avatarUrl ?? '';
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
                                                                src={avatarUrl}
                                                                alt={prof.name}
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
                )}
            </CardContent>
        </Card>
    );
}
