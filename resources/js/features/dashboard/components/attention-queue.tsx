import { AlertCircle } from 'lucide-react';
import type { AttentionItem } from '../types';

const levelClasses: Record<AttentionItem['level'], string> = {
    high: 'bg-red-500',
    medium: 'bg-amber-500',
    low: 'bg-slate-400',
};

export function AttentionQueue({ items }: { items: AttentionItem[] }) {
    return (
        <aside
            className="surface-panel overflow-hidden"
            aria-labelledby="attention-title"
        >
            <header className="border-b border-border px-5 py-4">
                <div className="flex items-center gap-2 text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                    <AlertCircle
                        className="size-4 text-accent-foreground"
                        aria-hidden="true"
                    />
                    Requer atenção
                </div>
                <h2
                    id="attention-title"
                    className="mt-1 text-lg font-semibold tracking-tight text-foreground"
                >
                    Antes do próximo horário
                </h2>
            </header>
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
                                <span className="mt-0.5 block text-xs text-muted-foreground">
                                    {item.detail}
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="px-5 py-8 text-sm text-muted-foreground">
                    Nenhum alerta pendente. Os avisos aparecerão quando os
                    módulos operacionais estiverem conectados.
                </p>
            )}
        </aside>
    );
}
