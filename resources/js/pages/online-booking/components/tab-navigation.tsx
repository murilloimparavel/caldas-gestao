import {
    BellRing,
    Clock3,
    GalleryHorizontalEnd,
    Globe2,
    Link2,
    Scissors,
    Settings2,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import type { TabKey } from '../types';

export const tabs: { key: TabKey; label: string; icon: LucideIcon }[] = [
    { key: 'details', label: 'Detalhes', icon: Globe2 },
    { key: 'settings', label: 'Configurações', icon: Settings2 },
    { key: 'link', label: 'Link público', icon: Link2 },
    { key: 'gallery', label: 'Galeria', icon: GalleryHorizontalEnd },
    { key: 'services', label: 'Serviços', icon: Scissors },
    { key: 'hours', label: 'Horários', icon: Clock3 },
    { key: 'confirmation', label: 'Confirmação', icon: BellRing },
];

export function TabNavigation({
    activeTab,
    onChange,
}: {
    activeTab: TabKey;
    onChange: (tab: TabKey) => void;
}) {
    return (
        <div className="overflow-x-auto border-b border-border/70">
            <div
                aria-label="Configuração do agendamento online"
                className="flex min-w-max gap-1"
                role="tablist"
            >
                {tabs.map(({ key, label, icon: Icon }) => (
                    <button
                        key={key}
                        type="button"
                        role="tab"
                        aria-selected={activeTab === key}
                        onClick={() => onChange(key)}
                        className={`relative flex items-center gap-2 px-3 py-3 text-sm font-medium transition-colors after:absolute after:inset-x-2 after:bottom-0 after:h-0.5 ${activeTab === key ? 'text-primary after:bg-primary' : 'text-muted-foreground after:bg-transparent hover:text-foreground'}`}
                    >
                        <Icon aria-hidden="true" className="size-4" />
                        {label}
                    </button>
                ))}
            </div>
        </div>
    );
}
