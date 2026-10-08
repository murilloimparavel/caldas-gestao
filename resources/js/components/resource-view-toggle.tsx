import { LayoutGrid, List } from 'lucide-react';
import { useHydratedLocalStorageState } from '@/hooks/use-hydrated-local-storage-state';
import { cn } from '@/lib/utils';

export type ResourceView = 'cards' | 'list';

const parseResourceView = (storedView: string): ResourceView =>
    storedView === 'list' ? 'list' : 'cards';

type ResourceViewToggleProps = {
    value: ResourceView;
    onChange: (value: ResourceView) => void;
};

export function useResourceView(storageKey: string): {
    view: ResourceView;
    setView: (view: ResourceView) => void;
} {
    const [view, setView] = useHydratedLocalStorageState<ResourceView>(
        storageKey,
        'cards',
        parseResourceView,
    );

    return { view, setView: (nextView) => setView(nextView) };
}

export function ResourceViewToggle({
    value,
    onChange,
}: ResourceViewToggleProps) {
    const options: {
        icon: typeof LayoutGrid;
        label: string;
        value: ResourceView;
    }[] = [
        { icon: LayoutGrid, label: 'Cartões', value: 'cards' },
        { icon: List, label: 'Lista', value: 'list' },
    ];

    return (
        <div
            aria-label="Visualização dos resultados"
            className="hidden items-center rounded-lg border border-border bg-muted/60 p-1 text-xs font-medium md:flex"
            role="group"
        >
            {options.map((option) => {
                const Icon = option.icon;

                return (
                    <button
                        key={option.value}
                        type="button"
                        aria-label={`Exibir em ${option.label.toLowerCase()}`}
                        aria-pressed={value === option.value}
                        title={`Exibir em ${option.label.toLowerCase()}`}
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'inline-flex size-8 items-center justify-center rounded-md transition-colors',
                            value === option.value
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <Icon aria-hidden="true" className="size-4" />
                    </button>
                );
            })}
        </div>
    );
}
