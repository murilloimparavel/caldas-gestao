import { Minus, TrendingDown, TrendingUp } from 'lucide-react';

type MetricDeltaProps = { value: number; label?: string };

export function MetricDelta({
    value,
    label = 'vs. período anterior',
}: MetricDeltaProps) {
    const direction = value > 0 ? 'up' : value < 0 ? 'down' : 'neutral';
    const Icon =
        direction === 'up'
            ? TrendingUp
            : direction === 'down'
              ? TrendingDown
              : Minus;
    const tone =
        direction === 'up'
            ? 'text-emerald-600 dark:text-emerald-400'
            : direction === 'down'
              ? 'text-destructive'
              : 'text-muted-foreground';

    return (
        <span
            className={`inline-flex items-center gap-1 text-xs font-semibold ${tone}`}
            aria-label={`${value > 0 ? 'aumento de' : value < 0 ? 'queda de' : 'sem variação de'} ${Math.abs(value)} por cento`}
        >
            <Icon className="size-3.5" aria-hidden="true" />
            {value > 0 ? '+' : ''}
            {value}%
            <span className="font-normal text-muted-foreground">{label}</span>
        </span>
    );
}
