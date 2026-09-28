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
import { bookingUi } from './design-tokens';

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
        <div className={`overflow-x-auto ${bookingUi.nav}`}>
            <div
                aria-label="Configuração do agendamento online"
                className="flex min-w-max gap-1 lg:grid lg:grid-cols-7"
                role="tablist"
            >
                {tabs.map(({ key, label, icon: Icon }) => (
                    <button
                        key={key}
                        type="button"
                        role="tab"
                        aria-selected={activeTab === key}
                        onClick={() => onChange(key)}
                        className={`relative flex items-center justify-center gap-2 rounded-xl px-3 py-3 text-xs font-semibold transition-all ${activeTab === key ? bookingUi.navActive : bookingUi.navIdle}`}
                    >
                        <Icon aria-hidden="true" className="size-4" />
                        {label}
                    </button>
                ))}
            </div>
        </div>
    );
}
