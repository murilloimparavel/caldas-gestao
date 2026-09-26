import { router } from '@inertiajs/react';
import { Calendar as CalendarIcon, Check, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import type { PeriodFilter } from '../types';

type DashboardHeaderProps = {
    userName: string;
    period: PeriodFilter;
    startDate: string;
    endDate: string;
};

const PERIOD_OPTIONS: { value: PeriodFilter; label: string }[] = [
    { value: 'today', label: 'Hoje' },
    { value: '7d', label: '7 dias' },
    { value: '30d', label: '30 dias' },
    { value: 'this_month', label: 'Este mês' },
    { value: 'custom', label: 'Personalizado' },
];

export function DashboardHeader({
    userName,
    period,
    startDate: initialStartDate,
    endDate: initialEndDate,
}: DashboardHeaderProps) {
    const [selectedPeriod, setSelectedPeriod] = useState<PeriodFilter>(period);
    const [startDate, setStartDate] = useState(initialStartDate);
    const [endDate, setEndDate] = useState(initialEndDate);
    const [isLoading, setIsLoading] = useState(false);
    const [feedback, setFeedback] = useState('');
    const hasValidCustomRange = Boolean(
        startDate && endDate && startDate <= endDate,
    );
    const customRangeError =
        !startDate || !endDate
            ? 'Informe a data inicial e a data final.'
            : 'A data final deve ser igual ou posterior à data inicial.';

    const handlePeriodChange = (newPeriod: PeriodFilter) => {
        setSelectedPeriod(newPeriod);

        if (newPeriod !== 'custom') {
            const periodLabel = PERIOD_OPTIONS.find(
                (option) => option.value === newPeriod,
            )?.label;
            setFeedback(
                `Carregando dados de ${periodLabel ?? 'período selecionado'}…`,
            );
            setIsLoading(true);
            router.get(
                dashboard().url,
                { preset: newPeriod },
                {
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: () => setFeedback('Período atualizado.'),
                    onError: () =>
                        setFeedback('Não foi possível atualizar o período.'),
                    onCancel: () => setFeedback('Atualização cancelada.'),
                    onFinish: () => setIsLoading(false),
                },
            );
        } else {
            setFeedback(
                'Escolha a data inicial e a data final para aplicar o período.',
            );
        }
    };

    const handleApplyCustomDates = () => {
        if (hasValidCustomRange) {
            setFeedback('Carregando dados do período personalizado…');
            setIsLoading(true);
            router.get(
                dashboard().url,
                { preset: 'custom', start_date: startDate, end_date: endDate },
                {
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: () =>
                        setFeedback('Período personalizado atualizado.'),
                    onError: () =>
                        setFeedback(
                            'Não foi possível atualizar o período personalizado.',
                        ),
                    onCancel: () => setFeedback('Atualização cancelada.'),
                    onFinish: () => setIsLoading(false),
                },
            );
        } else {
            setFeedback(customRangeError);
        }
    };

    const handleRefresh = () => {
        setFeedback('Atualizando dados do dashboard…');
        setIsLoading(true);
        router.reload({
            onSuccess: () => setFeedback('Dashboard atualizado.'),
            onError: () =>
                setFeedback('Não foi possível atualizar o dashboard.'),
            onCancel: () => setFeedback('Atualização cancelada.'),
            onFinish: () => {
                setIsLoading(false);
            },
        });
    };

    return (
        <div className="flex flex-col gap-6 border-b border-border/40 pb-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 className="font-display text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    Olá, {userName} 👋
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Acompanhe os resultados e o ritmo operacional em tempo real.
                </p>
            </div>

            <div className="flex w-full flex-col gap-3 lg:w-auto lg:min-w-[min(100%,34rem)]">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="flex-1 space-y-1.5">
                        <label
                            htmlFor="dashboard-period"
                            className="text-[0.68rem] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                        >
                            Período de análise
                        </label>
                        <Select
                            value={selectedPeriod}
                            disabled={isLoading}
                            onValueChange={(v) =>
                                handlePeriodChange(v as PeriodFilter)
                            }
                        >
                            <SelectTrigger
                                id="dashboard-period"
                                className="h-11 w-full bg-background/70 px-3.5 shadow-sm transition-colors hover:bg-background"
                                aria-label="Período de análise"
                            >
                                <CalendarIcon className="mr-2.5 size-4 text-primary" />
                                <SelectValue placeholder="Selecione o período" />
                            </SelectTrigger>
                            <SelectContent>
                                {PERIOD_OPTIONS.map((opt) => (
                                    <SelectItem
                                        key={opt.value}
                                        value={opt.value}
                                    >
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <Button
                        variant="outline"
                        size="icon"
                        onClick={handleRefresh}
                        title="Atualizar dados"
                        aria-label="Atualizar dados do dashboard"
                        className="size-11 shrink-0 bg-background/70 shadow-sm"
                        disabled={isLoading}
                    >
                        <RefreshCw
                            className={`size-4 ${isLoading ? 'animate-spin' : ''}`}
                        />
                    </Button>
                </div>

                {selectedPeriod === 'custom' && (
                    <div
                        className="rounded-xl border border-border/70 bg-background/35 p-3 shadow-sm"
                        role="group"
                        aria-label="Intervalo personalizado"
                    >
                        <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="dashboard-start-date"
                                    className="text-xs font-medium text-foreground"
                                >
                                    Data inicial
                                </label>
                                <Input
                                    id="dashboard-start-date"
                                    type="date"
                                    value={startDate}
                                    max={endDate || undefined}
                                    onChange={(e) =>
                                        setStartDate(e.target.value)
                                    }
                                    className="h-11 w-full bg-background text-sm shadow-none"
                                    aria-label="Data inicial"
                                    aria-invalid={!hasValidCustomRange}
                                    aria-describedby="dashboard-date-help"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="dashboard-end-date"
                                    className="text-xs font-medium text-foreground"
                                >
                                    Data final
                                </label>
                                <Input
                                    id="dashboard-end-date"
                                    type="date"
                                    value={endDate}
                                    min={startDate || undefined}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="h-11 w-full bg-background text-sm shadow-none"
                                    aria-label="Data final"
                                    aria-invalid={!hasValidCustomRange}
                                    aria-describedby="dashboard-date-help"
                                />
                            </div>
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={handleApplyCustomDates}
                                className="h-11 gap-2 px-4 sm:mb-0"
                                aria-label="Aplicar período personalizado"
                                disabled={!hasValidCustomRange || isLoading}
                            >
                                <Check className="size-4" />
                                Aplicar
                            </Button>
                        </div>
                        <p
                            id="dashboard-date-help"
                            className={`mt-2 text-[0.7rem] ${hasValidCustomRange ? 'text-muted-foreground' : 'text-destructive'}`}
                            role="status"
                        >
                            {hasValidCustomRange
                                ? 'Escolha o intervalo para atualizar os indicadores.'
                                : customRangeError}
                        </p>
                    </div>
                )}
                <p
                    className="min-h-4 text-xs text-muted-foreground"
                    aria-live="polite"
                >
                    {feedback}
                </p>
            </div>
        </div>
    );
}
