import type { AttentionItem } from '../types';
import { DashboardPanel } from './dashboard-panel';
import { EmptyState } from './empty-state';

const levelClasses: Record<AttentionItem['level'], string> = {
    high: 'bg-red-500',
    medium: 'bg-amber-500',
    low: 'bg-slate-400',
};

export function AttentionQueue({ items }: { items: AttentionItem[] }) {
    return (
        <DashboardPanel
            title="Antes do próximo horário"
            eyebrow="Requer atenção"
            description="Itens que merecem uma ação antes do próximo atendimento."
            className="h-full"
        >
            {items.length > 0 ? (
                <ul className="divide-y divide-border">
                    {items.map((item) => (
                        <li
                            key={item.id}
                            className="flex min-h-20 items-center gap-3 px-5 py-3"
                        >
                            <span
                                className={`size-2.5 shrink-0 rounded-full ${levelClasses[item.level]}`}
                                aria-hidden="true"
                            />
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-semibold text-foreground">
                                    {item.label}
                                </span>
                                <span className="sr-only">
                                    Prioridade{' '}
                                    {item.level === 'high'
                                        ? 'alta'
                                        : item.level === 'medium'
                                          ? 'média'
                                          : 'baixa'}
                                </span>
                                <span className="mt-0.5 block text-xs text-muted-foreground">
                                    {item.detail}
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
            ) : (
                <EmptyState message="Nada requer atenção neste período." />
            )}
        </DashboardPanel>
    );
}
