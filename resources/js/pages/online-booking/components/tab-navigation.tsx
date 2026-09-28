import {
    Clock3,
    GalleryHorizontalEnd,
    Globe2,
    Link2,
    Scissors,
    Settings2,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { KeyboardEvent } from 'react';

import type { TabKey } from '../types';
import { bookingUi } from './design-tokens';

export const tabs: { key: TabKey; label: string; icon: LucideIcon }[] = [
    { key: 'details', label: 'Detalhes', icon: Globe2 },
    { key: 'settings', label: 'Preferências', icon: Settings2 },
    { key: 'link', label: 'Link público', icon: Link2 },
    { key: 'gallery', label: 'Galeria', icon: GalleryHorizontalEnd },
    { key: 'services', label: 'Serviços', icon: Scissors },
    { key: 'hours', label: 'Horários', icon: Clock3 },
];

const tabGroups: {
    label: string;
    keys: TabKey[];
}[] = [
    { label: 'Aparência', keys: ['details', 'gallery'] },
    { label: 'Agendamento', keys: ['settings', 'services', 'hours'] },
    { label: 'Link público', keys: ['link'] },
    { label: 'Publicação', keys: ['publication'] },
];

const tabPanelId = 'online-booking-tabpanel';

function handleTabKeyDown(
    event: KeyboardEvent<HTMLButtonElement>,
    onChange: (tab: TabKey) => void,
) {
    const direction =
        event.key === 'ArrowRight'
            ? 1
            : event.key === 'ArrowLeft'
              ? -1
              : event.key === 'Home'
                ? 'first'
                : event.key === 'End'
                  ? 'last'
                  : null;

    if (direction === null) {
        return;
    }

    event.preventDefault();

    const navigationTabs = Array.from(
        event.currentTarget
            .closest('nav')
            ?.querySelectorAll<HTMLButtonElement>('[data-booking-tab]') ?? [],
    );
    const currentIndex = navigationTabs.indexOf(event.currentTarget);
    const nextIndex =
        direction === 'first'
            ? 0
            : direction === 'last'
              ? navigationTabs.length - 1
              : (currentIndex + direction + navigationTabs.length) %
                navigationTabs.length;
    const nextTab = navigationTabs[nextIndex];

    if (nextTab) {
        nextTab.focus();
        onChange(nextTab.dataset.bookingTab as TabKey);
    }
}

export function TabNavigation({
    activeTab,
    onChange,
}: {
    activeTab: TabKey;
    onChange: (tab: TabKey) => void;
}) {
    return (
        <nav
            aria-label="Configuração do agendamento online"
            className={`overflow-x-auto ${bookingUi.nav}`}
        >
            <div className="grid min-w-[42rem] gap-2 lg:grid-cols-4">
                {tabGroups.map((group) => {
                    const groupTabs = tabs.filter((tab) =>
                        group.keys.includes(tab.key),
                    );

                    return (
                        <section key={group.label} className="space-y-1">
                            <h2 className="px-2 text-[10px] font-bold tracking-[0.16em] text-slate-500 uppercase">
                                {group.label}
                            </h2>
                            <div
                                role="group"
                                aria-label={group.label}
                                className="flex gap-1"
                            >
                                {groupTabs.map(({ key, label, icon: Icon }) => (
                                    <button
                                        key={key}
                                        id={`booking-tab-${key}`}
                                        type="button"
                                        data-booking-tab={key}
                                        aria-controls={tabPanelId}
                                        aria-current={
                                            activeTab === key
                                                ? 'page'
                                                : undefined
                                        }
                                        tabIndex={activeTab === key ? 0 : -1}
                                        onClick={() => onChange(key)}
                                        onKeyDown={(event) =>
                                            handleTabKeyDown(event, onChange)
                                        }
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
                                ))}
                            </div>
                        </section>
                    );
                })}
            </div>
        </nav>
    );
}
