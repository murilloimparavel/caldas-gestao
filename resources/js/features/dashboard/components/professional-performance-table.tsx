import { TrendingUp, TrendingDown, User } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import type { ProfessionalPerformance } from '../types';

type ProfessionalPerformanceTableProps = {
    data?: ProfessionalPerformance[];
};

const DEFAULT_PERFORMANCE: ProfessionalPerformance[] = [
    {
        id: '1',
        name: 'Camila Silva',
        avatarUrl: '',
        totalServices: 48,
        changePercentage: 14.2,
        averageTicket: 'R$ 185,00',
    },
    {
        id: '2',
        name: 'Lucas Mendes',
        avatarUrl: '',
        totalServices: 39,
        changePercentage: 6.5,
        averageTicket: 'R$ 140,00',
    },
    {
        id: '3',
        name: 'Mariana Costa',
        avatarUrl: '',
        totalServices: 32,
        changePercentage: -2.1,
        averageTicket: 'R$ 165,00',
    },
    {
        id: '4',
        name: 'Rafael Lima',
        avatarUrl: '',
        totalServices: 23,
        changePercentage: 8.0,
        averageTicket: 'R$ 120,00',
    },
];

export function ProfessionalPerformanceTable({ data = DEFAULT_PERFORMANCE }: ProfessionalPerformanceTableProps) {
    return (
        <Card className="border-border/60">
            <CardHeader className="pb-3">
                <CardTitle className="text-base font-semibold">Desempenho por Profissional</CardTitle>
                <CardDescription className="text-xs">Atendimentos executados e ticket médio individual</CardDescription>
            </CardHeader>
            <CardContent className="p-0">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead>
                            <tr className="border-y border-border/50 bg-muted/30 text-muted-foreground">
                                <th className="py-2.5 px-4 font-medium">Profissional</th>
                                <th className="py-2.5 px-4 font-medium text-center">Atendimentos</th>
                                <th className="py-2.5 px-4 font-medium text-center">Variação %</th>
                                <th className="py-2.5 px-4 font-medium text-right">Ticket Médio</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border/40">
                            {data.map((prof) => {
                                const initials = prof.name
                                    .split(' ')
                                    .map((n) => n[0])
                                    .join('')
                                    .slice(0, 2)
                                    .toUpperCase();

                                const isUp = prof.changePercentage >= 0;

                                return (
                                    <tr key={prof.id} className="transition-colors hover:bg-muted/20">
                                        <td className="py-3 px-4">
                                            <div className="flex items-center gap-2.5">
                                                <Avatar className="size-8 border border-border/50">
                                                    {prof.avatarUrl && <AvatarImage src={prof.avatarUrl} alt={prof.name} />}
                                                    <AvatarFallback className="bg-primary/10 text-[10px] font-semibold text-primary">
                                                        {initials || <User className="size-3.5" />}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <span className="font-medium text-foreground">{prof.name}</span>
                                            </div>
                                        </td>
                                        <td className="py-3 px-4 text-center font-semibold text-foreground">
                                            {prof.totalServices}
                                        </td>
                                        <td className="py-3 px-4 text-center">
                                            <span
                                                className={`inline-flex items-center gap-1 font-semibold ${
                                                    isUp
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : 'text-red-600 dark:text-red-400'
                                                }`}
                                            >
                                                {isUp ? <TrendingUp className="size-3" /> : <TrendingDown className="size-3" />}
                                                {isUp ? '+' : ''}
                                                {prof.changePercentage}%
                                            </span>
                                        </td>
                                        <td className="py-3 px-4 text-right font-medium text-foreground">
                                            {prof.averageTicket}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}
