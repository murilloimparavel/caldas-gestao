import { Check, Filter, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type {
    CommissionCategoryOption,
    CommissionProductOption,
    CommissionServiceOption,
} from './commission-rule-types';

type Item = CommissionServiceOption | CommissionProductOption;

type SelectionPickerProps = {
    kind: 'service' | 'product';
    items: Item[];
    categories: CommissionCategoryOption[];
    selectedIds: string[];
    onChange: (ids: string[]) => void;
};

export function SelectionPicker({
    kind,
    items,
    categories,
    selectedIds,
    onChange,
}: SelectionPickerProps) {
    const [query, setQuery] = useState('');
    const [categoryId, setCategoryId] = useState('all');
    const itemsHaveCategoryData = items.some(
        (item) => item.category_id !== undefined && item.category_id !== null,
    );

    const filteredItems = useMemo(() => {
        const normalizedQuery = query.trim().toLocaleLowerCase();

        return items.filter((item) => {
            const matchesQuery = item.name
                .toLocaleLowerCase()
                .includes(normalizedQuery);
            const matchesCategory =
                categoryId === 'all' ||
                !itemsHaveCategoryData ||
                item.category_id === categoryId;

            return matchesQuery && matchesCategory;
        });
    }, [categoryId, items, itemsHaveCategoryData, query]);

    const visibleIds = filteredItems.map((item) => item.id);
    const allVisibleSelected =
        visibleIds.length > 0 &&
        visibleIds.every((id) => selectedIds.includes(id));
    const someVisibleSelected = visibleIds.some((id) =>
        selectedIds.includes(id),
    );

    const toggleVisible = () => {
        if (allVisibleSelected) {
            onChange(selectedIds.filter((id) => !visibleIds.includes(id)));

            return;
        }

        onChange(Array.from(new Set([...selectedIds, ...visibleIds])));
    };

    const toggleItem = (id: string) => {
        onChange(
            selectedIds.includes(id)
                ? selectedIds.filter((selectedId) => selectedId !== id)
                : [...selectedIds, id],
        );
    };

    return (
        <div className="space-y-3 rounded-2xl border border-border/80 bg-muted/20 p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p className="text-sm font-semibold text-foreground">
                        {kind === 'service' ? 'Serviços' : 'Produtos'}{' '}
                        específicos
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Refine a lista e selecione apenas o que receberá esta
                        regra.
                    </p>
                </div>
                <Badge variant="secondary" className="rounded-full px-3">
                    {selectedIds.length} selecionado
                    {selectedIds.length === 1 ? '' : 's'}
                </Badge>
            </div>

            <div className="grid gap-2 sm:grid-cols-[1fr_190px]">
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={`Buscar ${kind === 'service' ? 'serviço' : 'produto'}`}
                        className="h-10 pl-9"
                    />
                </div>
                <div className="relative">
                    <Filter className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <select
                        value={categoryId}
                        onChange={(event) => setCategoryId(event.target.value)}
                        disabled={
                            categories.length === 0 || !itemsHaveCategoryData
                        }
                        className="h-10 w-full appearance-none rounded-md border border-input bg-background px-9 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        aria-label="Filtrar por categoria"
                    >
                        <option value="all">
                            {itemsHaveCategoryData
                                ? 'Todas as categorias'
                                : 'Filtro de categoria indisponível'}
                        </option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2 border-y border-border/70 py-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={toggleVisible}
                    className="gap-2 px-2"
                >
                    <span
                        className={cn(
                            'flex h-4 w-4 items-center justify-center rounded border',
                            allVisibleSelected
                                ? 'border-primary bg-primary text-primary-foreground'
                                : someVisibleSelected
                                  ? 'border-primary bg-primary/20 text-primary'
                                  : 'border-input',
                        )}
                    >
                        {allVisibleSelected && <Check className="h-3 w-3" />}
                        {!allVisibleSelected && someVisibleSelected && (
                            <span className="h-1.5 w-1.5 rounded-sm bg-primary" />
                        )}
                    </span>
                    {allVisibleSelected
                        ? 'Desmarcar resultados'
                        : 'Selecionar resultados'}
                </Button>
                <span className="text-xs text-muted-foreground">
                    {filteredItems.length} resultado
                    {filteredItems.length === 1 ? '' : 's'}
                </span>
            </div>

            <div className="max-h-72 space-y-1 overflow-y-auto pr-1">
                {filteredItems.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
                        Nenhum item encontrado com esses filtros.
                    </div>
                ) : (
                    filteredItems.map((item) => {
                        const selected = selectedIds.includes(item.id);
                        const price =
                            kind === 'service'
                                ? (item as CommissionServiceOption).price_cents
                                : (item as CommissionProductOption)
                                      .sale_price_cents;

                        return (
                            <label
                                key={item.id}
                                className={cn(
                                    'flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 transition-colors',
                                    selected
                                        ? 'border-primary/50 bg-primary/8'
                                        : 'border-transparent hover:border-border hover:bg-background/70',
                                )}
                            >
                                <input
                                    type="checkbox"
                                    checked={selected}
                                    onChange={() => toggleItem(item.id)}
                                    className="sr-only"
                                />
                                <span
                                    className={cn(
                                        'flex h-4 w-4 shrink-0 items-center justify-center rounded border',
                                        selected
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-input',
                                    )}
                                >
                                    {selected && <Check className="h-3 w-3" />}
                                </span>
                                <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">
                                    {item.name}
                                </span>
                                <span className="text-xs whitespace-nowrap text-muted-foreground">
                                    {new Intl.NumberFormat('pt-BR', {
                                        style: 'currency',
                                        currency: 'BRL',
                                    }).format(price / 100)}
                                </span>
                            </label>
                        );
                    })
                )}
            </div>

            {selectedIds.length > 0 && (
                <div className="flex flex-wrap gap-1.5 border-t border-border/70 pt-2">
                    {items
                        .filter((item) => selectedIds.includes(item.id))
                        .slice(0, 6)
                        .map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => toggleItem(item.id)}
                                className="inline-flex max-w-full items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-xs text-primary hover:bg-primary/20"
                            >
                                <span className="max-w-36 truncate">
                                    {item.name}
                                </span>
                                <X className="h-3 w-3" />
                            </button>
                        ))}
                    {selectedIds.length > 6 && (
                        <span className="self-center text-xs text-muted-foreground">
                            +{selectedIds.length - 6} outros
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}
