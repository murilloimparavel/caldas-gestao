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

const tabGroups: {
    label: string;
    keys: TabKey[];
}[] = [
    { label: 'Aparência', keys: ['details', 'settings', 'gallery'] },
    { label: 'Agendamento', keys: ['services', 'hours', 'confirmation'] },
    { label: 'Link público', keys: ['link'] },
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
            <div aria-label="Configuração do agendamento online" role="tablist">
                <div className="grid min-w-[42rem] gap-2 lg:grid-cols-4">
                    {tabGroups.map((group) => {
                        const groupTabs = tabs.filter((tab) =>
                            group.keys.includes(tab.key),
                        );

                        return (
                            <div key={group.label} className="space-y-1">
                                <p className="px-2 text-[10px] font-bold tracking-[0.16em] text-slate-500 uppercase">
                                    {group.label}
                                </p>
                                <div className="flex gap-1">
                                    {groupTabs.map(
                                        ({ key, label, icon: Icon }) => (
                                            <button
                                                key={key}
                                                type="button"
                                                role="tab"
                                                aria-selected={
                                                    activeTab === key
                                                }
                                                onClick={() => onChange(key)}
                                                className={`relative flex min-w-0 flex-1 items-center justify-center gap-2 rounded-xl px-2 py-3 text-xs font-semibold transition-all ${activeTab === key ? bookingUi.navActive : bookingUi.navIdle}`}
                                            >
                                                <Icon
                                                    aria-hidden="true"
                                                    className="size-4 shrink-0"
                                                />
                                                <span className="truncate">
                                                    {label}
                                                </span>
                                            </button>
                                        ),
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
