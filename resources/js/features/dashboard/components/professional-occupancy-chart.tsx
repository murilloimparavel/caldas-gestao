import { useMemo, useState } from 'react';
import {
    ArrowDownWideNarrow,
    ArrowUpWideNarrow,
    Clock3,
    MoveHorizontal,
    Users,
} from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { ProfessionalOccupancy } from '../types';

type ProfessionalOccupancyChartProps = {
    data: ProfessionalOccupancy;
};

function formatDuration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    if (hours === 0) {
        return `${remainingMinutes} min`;
    }

    return remainingMinutes > 0
        ? `${hours}h ${remainingMinutes}min`
        : `${hours}h`;
}

export function ProfessionalOccupancyChart({
    data,
}: ProfessionalOccupancyChartProps) {
    const [sortDescending, setSortDescending] = useState(true);
    const professionals = useMemo(() => {
        return [...data.professionals].sort((left, right) => {
            const leftRate = left.occupancyPercentage ?? -1;
            const rightRate = right.occupancyPercentage ?? -1;

            return sortDescending ? rightRate - leftRate : leftRate - rightRate;
        });
    }, [data.professionals, sortDescending]);

    const hasCapacity = data.availableMinutes > 0;

    return (
        <Card className="max-w-full min-w-0 overflow-hidden border-border/60">
            <CardHeader className="gap-3 px-3.5 pb-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4 sm:px-6 sm:pb-4">
                <div className="min-w-0">
                    <CardTitle className="text-base font-semibold sm:text-lg">
                        Ocupação da equipe
                    </CardTitle>
                    <CardDescription className="mt-1 max-w-full text-xs leading-relaxed sm:max-w-prose">
                        Tempo agendado em relação à disponibilidade
                    </CardDescription>
                </div>
                <button
                    type="button"
                    onClick={() => setSortDescending((current) => !current)}
                    className="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg border border-border/70 bg-background px-3 text-xs font-medium text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:min-h-9 sm:w-auto sm:self-start"
                    aria-label={
                        sortDescending
                            ? 'Ordenar da menor para a maior ocupação'
                            : 'Ordenar da maior para a menor ocupação'
                    }
                >
                    {sortDescending ? (
                        <ArrowDownWideNarrow
                            className="size-4"
                            aria-hidden="true"
                        />
                    ) : (
                        <ArrowUpWideNarrow
                            className="size-4"
                            aria-hidden="true"
                        />
                    )}
                    {sortDescending ? 'Maior ocupação' : 'Menor ocupação'}
                </button>
            </CardHeader>
            <CardContent className="space-y-4 px-3.5 pb-4 sm:space-y-5 sm:px-6 sm:pb-6">
                <div className="grid grid-cols-1 gap-2.5 min-[420px]:grid-cols-2 sm:gap-3">
                    <div className="rounded-xl border border-border/60 bg-muted/20 p-3 sm:p-4">
                        <p className="text-xs text-muted-foreground">
                            Ocupação geral
                        </p>
                        <p className="mt-1 text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                            {data.overallPercentage === null
                                ? '—'
                                : `${data.overallPercentage}%`}
                        </p>
                    </div>
                    <div className="rounded-xl border border-border/60 bg-muted/20 p-3 sm:p-4">
                        <p className="text-xs text-muted-foreground">
                            Tempo reservado
                        </p>
                        <p className="mt-1 text-lg font-semibold tracking-tight text-foreground sm:text-2xl">
                            {formatDuration(data.bookedMinutes)}
                        </p>
                    </div>
                </div>

                {!hasCapacity ? (
                    <div className="flex min-h-32 flex-col items-center justify-center rounded-xl border border-dashed border-border px-5 text-center">
                        <Clock3
                            className="mb-2 size-5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="text-sm font-medium text-foreground">
                            Cadastre os horários de trabalho
                        </p>
                        <p className="mt-1 max-w-sm text-xs leading-relaxed text-muted-foreground">
                            A taxa aparece quando a disponibilidade dos
                            profissionais estiver configurada para este período.
                        </p>
                    </div>
                ) : professionals.length === 0 ? (
                    <div className="flex min-h-32 flex-col items-center justify-center rounded-xl border border-dashed border-border px-5 text-center">
                        <Users
                            className="mb-2 size-5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="text-sm font-medium text-foreground">
                            Nenhum profissional ativo
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Profissionais ativos serão exibidos aqui.
                        </p>
                    </div>
                ) : (
                    <TooltipProvider delayDuration={100}>
                        <p
                            id="professional-occupancy-mobile-hint"
                            className="mb-2 flex items-center gap-1.5 text-3xs text-muted-foreground sm:hidden"
                        >
                            <MoveHorizontal
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            Deslize para ver todos os profissionais.
                        </p>
                        <div
                            className="-mx-1 max-w-full [scrollbar-width:auto] [scrollbar-color:var(--color-primary)_var(--color-muted)] overflow-x-auto overscroll-x-contain px-1 pb-3"
                            role="region"
                            aria-label="Ocupação por profissional"
                            aria-describedby="professional-occupancy-mobile-hint"
                            // The scroll region needs focus so keyboard users can pan it.
                            // eslint-disable-next-line jsx-a11y/no-noninteractive-tabindex
                            tabIndex={0}
                        >
                            <div className="flex min-h-56 min-w-max items-end gap-1.5 px-1 pt-3 min-[380px]:gap-2.5 sm:min-h-64 sm:gap-4 sm:pt-4">
                                {professionals.map((professional) => {
                                    const percentage =
                                        professional.occupancyPercentage;
                                    const barHeight =
                                        percentage === null
                                            ? 0
                                            : Math.min(percentage, 100);
                                    const initials = professional.name
                                        .split(' ')
                                        .map((part) => part[0])
                                        .join('')
                                        .slice(0, 2)
                                        .toUpperCase();
                                    const tone =
                                        percentage !== null && percentage >= 85
                                            ? 'from-rose-600 to-orange-400'
                                            : percentage !== null &&
                                                percentage >= 60
                                              ? 'from-primary to-sky-400'
                                              : 'from-emerald-600 to-lime-400';

                                    return (
                                        <Tooltip key={professional.id}>
                                            <TooltipTrigger asChild>
                                                <button
                                                    type="button"
                                                    className="group flex w-[4.8rem] shrink-0 flex-col items-center gap-1.5 rounded-xl px-1 pb-1 text-center transition-colors outline-none hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring min-[380px]:gap-2 sm:w-28 sm:gap-2"
                                                    aria-label={`${professional.name}: ${percentage === null ? 'sem agenda' : `${percentage}% de ocupação`}; ${formatDuration(professional.bookedMinutes)} agendadas`}
                                                >
                                                    <span className="text-sm font-semibold text-foreground tabular-nums">
                                                        {percentage === null
                                                            ? '—'
                                                            : `${percentage}%`}
                                                    </span>
                                                    <div className="relative flex h-32 w-8 items-end overflow-hidden rounded-full bg-muted/80 ring-1 ring-border/50 transition-transform duration-200 group-hover:scale-105 group-focus-visible:scale-105 min-[380px]:h-36 min-[380px]:w-9 sm:h-44 sm:w-10">
                                                        <div
                                                            className={`w-full rounded-full bg-gradient-to-t ${tone} transition-[height] duration-700 ease-out`}
                                                            style={{
                                                                height: `${barHeight}%`,
                                                            }}
                                                        />
                                                        {percentage ===
                                                            null && (
                                                            <span className="absolute inset-x-0 bottom-2 text-[9px] text-muted-foreground">
                                                                N/D
                                                            </span>
                                                        )}
                                                    </div>
                                                    <Avatar className="size-7 border border-border/60 sm:size-8">
                                                        {professional.avatarUrl && (
                                                            <AvatarImage
                                                                src={
                                                                    professional.avatarUrl
                                                                }
                                                                alt=""
                                                            />
                                                        )}
                                                        <AvatarFallback className="bg-primary/10 text-[10px] font-semibold text-primary">
                                                            {initials || (
                                                                <Users
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="w-full text-[11px] leading-tight font-medium break-words whitespace-normal text-foreground sm:text-xs">
                                                        {professional.name}
                                                    </span>
                                                </button>
                                            </TooltipTrigger>
                                            <TooltipContent
                                                side="top"
                                                className="max-w-56 space-y-1.5 rounded-lg bg-popover p-3 text-popover-foreground shadow-lg ring-1 ring-border"
                                            >
                                                <p className="font-semibold">
                                                    {professional.name}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {percentage === null
                                                        ? 'Sem disponibilidade cadastrada'
                                                        : `${percentage}% da agenda ocupada`}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {formatDuration(
                                                        professional.bookedMinutes,
                                                    )}{' '}
                                                    agendadas
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {formatDuration(
                                                        professional.availableMinutes,
                                                    )}{' '}
                                                    disponíveis
                                                </p>
                                            </TooltipContent>
                                        </Tooltip>
                                    );
                                })}
                            </div>
                        </div>
                    </TooltipProvider>
                )}

                <p className="border-t border-border/50 pt-3 text-[10px] leading-relaxed text-muted-foreground sm:text-[11px]">
                    Considera agendamentos ativos e concluídos; cancelamentos e
                    faltas ficam fora. Bloqueios reduzem o tempo disponível.
                </p>
            </CardContent>
        </Card>
    );
}
