type ChartLegendItem = { label: string; colorClass?: string; color?: string };

export function ChartLegend({ items }: { items: ChartLegendItem[] }) {
    return (
        <ul
            className="flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted-foreground"
            aria-label="Legenda do gráfico"
        >
            {items.map((item) => (
                <li key={item.label} className="flex items-center gap-1.5">
                    <span
                        className={`size-2.5 rounded-full ${item.colorClass ?? ''}`}
                        style={
                            item.color
                                ? { backgroundColor: item.color }
                                : undefined
                        }
                        aria-hidden="true"
                    />
                    {item.label}
                </li>
            ))}
        </ul>
    );
}
