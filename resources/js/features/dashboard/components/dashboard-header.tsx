import { router } from '@inertiajs/react';
import { RefreshCw, Calendar as CalendarIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';
import type { PeriodFilter } from '../types';

type DashboardHeaderProps = {
    userName?: string;
    period?: PeriodFilter;
    startDate?: string;
    endDate?: string;
};

const PERIOD_OPTIONS: { value: PeriodFilter; label: string }[] = [
    { value: 'today', label: 'Hoje' },
    { value: '7days', label: '7 dias' },
    { value: '30days', label: '30 dias' },
    { value: 'this_month', label: 'Este mês' },
    { value: 'custom', label: 'Personalizado' },
];

export function DashboardHeader({
    userName = 'Usuário',
    period = '7days',
    startDate: initialStartDate = '',
    endDate: initialEndDate = '',
}: DashboardHeaderProps) {
    const [selectedPeriod, setSelectedPeriod] = useState<PeriodFilter>(period);
    const [startDate, setStartDate] = useState(initialStartDate);
    const [endDate, setEndDate] = useState(initialEndDate);
    const [isRefreshing, setIsRefreshing] = useState(false);

    const handlePeriodChange = (newPeriod: PeriodFilter) => {
        setSelectedPeriod(newPeriod);

        if (newPeriod !== 'custom') {
            router.get(
                dashboard().url,
                { period: newPeriod },
                { preserveState: true, preserveScroll: true }
            );
        }
    };

    const handleApplyCustomDates = () => {
        if (startDate && endDate) {
            router.get(
                dashboard().url,
                { period: 'custom', start_date: startDate, end_date: endDate },
                { preserveState: true, preserveScroll: true }
            );
        }
    };

    const handleRefresh = () => {
        setIsRefreshing(true);
        router.reload({
            onFinish: () => setIsRefreshing(false),
        });
    };

    return (
        <div className="flex flex-col gap-4 border-b border-border/40 pb-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 className="font-display text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    Olá, {userName} 👋
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Acompanhe os resultados e o ritmo operacional em tempo real.
                </p>
            </div>

            <div className="flex flex-wrap items-center gap-2.5">
                <Select value={selectedPeriod} onValueChange={(v) => handlePeriodChange(v as PeriodFilter)}>
                    <SelectTrigger className="w-[150px] bg-background">
                        <CalendarIcon className="mr-2 size-4 text-muted-foreground" />
                        <SelectValue placeholder="Selecione o período" />
                    </SelectTrigger>
                    <SelectContent>
                        {PERIOD_OPTIONS.map((opt) => (
                            <SelectItem key={opt.value} value={opt.value}>
                                {opt.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {selectedPeriod === 'custom' && (
                    <div className="flex items-center gap-2">
                        <Input
                            type="date"
                            value={startDate}
                            onChange={(e) => setStartDate(e.target.value)}
                            className="w-[135px] bg-background text-xs"
                        />
                        <span className="text-xs text-muted-foreground">até</span>
                        <Input
                            type="date"
                            value={endDate}
                            onChange={(e) => setEndDate(e.target.value)}
                            className="w-[135px] bg-background text-xs"
                        />
                        <Button size="sm" variant="secondary" onClick={handleApplyCustomDates}>
                            Filtrar
                        </Button>
                    </div>
                )}

                <Button
                    variant="outline"
                    size="icon"
                    onClick={handleRefresh}
                    title="Atualizar dados"
                    className="shrink-0 bg-background"
                >
                    <RefreshCw className={`size-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                </Button>
            </div>
        </div>
    );
}
