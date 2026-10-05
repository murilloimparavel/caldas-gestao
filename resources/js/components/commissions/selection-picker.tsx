import { Check, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import selectorOptions from '@/routes/selector-options';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { RemoteOptionPicker } from '@/components/remote-option-picker';
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

type RemotePayload = {
    data?: Item[];
    meta?: { has_more?: boolean };
};

const normalize = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('pt-BR')
        .trim();

export function SelectionPicker({
    kind,
    items,
    categories,
    selectedIds,
    onChange,
}: SelectionPickerProps) {
    const [query, setQuery] = useState('');
    const [categoryId, setCategoryId] = useState('all');
    const [remoteItems, setRemoteItems] = useState<Item[]>([]);
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);
    const [retryCount, setRetryCount] = useState(0);
    const loadedKey = useRef(`${kind}:${query}:${categoryId}`);
    const selectedIdsRef = useRef(selectedIds);

    useEffect(() => {
        selectedIdsRef.current = selectedIds;
    }, [selectedIds]);

    const resource = kind === 'service' ? 'services' : 'products';
    const requestKey = `${kind}:${query}:${categoryId}`;

    useEffect(() => {
        const controller = new AbortController();
        const isNewRequest = loadedKey.current !== requestKey;
        const requestPage = isNewRequest ? 1 : page;
        const timeout = window.setTimeout(() => {
            setLoading(true);
            setError(false);

            void fetch(
                selectorOptions.index.url(resource, {
                    query: {
                        search: query,
                        page: requestPage,
                        per_page: 25,
                        ...(categoryId !== 'all'
                            ? { category_id: categoryId }
                            : {}),
                    },
                }),
                {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            )
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('selector-options-request-failed');
                    }

                    return response.json() as Promise<RemotePayload>;
                })
                .then((payload) => {
                    const next = payload.data ?? [];

                    setRemoteItems((current) => {
                        const selectedCurrent = current.filter((item) =>
                            selectedIdsRef.current.includes(item.id),
                        );

                        if (requestPage === 1 || isNewRequest) {
                            return Array.from(
                                new Map(
                                    [...selectedCurrent, ...next].map(
                                        (item) => [item.id, item],
                                    ),
                                ).values(),
                            );
                        }

                        return Array.from(
                            new Map(
                                [...current, ...next].map((item) => [
                                    item.id,
                                    item,
                                ]),
                            ).values(),
                        );
                    });
                    setHasMore(payload.meta?.has_more === true);
                    loadedKey.current = requestKey;
                })
                .catch((reason: unknown) => {
                    if (
                        reason instanceof DOMException &&
                        reason.name === 'AbortError'
                    ) {
                        return;
                    }

                    setError(true);
                })
                .finally(() => setLoading(false));
        }, 220);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [categoryId, kind, page, query, requestKey, resource, retryCount]);

    const selectedItems = useMemo(
        () =>
            Array.from(
                new Map(
                    [...items, ...remoteItems]
                        .filter((item) => selectedIds.includes(item.id))
                        .map((item) => [item.id, item]),
                ).values(),
            ),
        [items, remoteItems, selectedIds],
    );
    const mergedItems = useMemo(
        () =>
            Array.from(
                new Map(
                    [...selectedItems, ...remoteItems].map((item) => [
                        item.id,
                        item,
                    ]),
                ).values(),
            ),
        [remoteItems, selectedItems],
    );
    const itemsHaveCategoryData = mergedItems.some(
        (item) => item.category_id !== undefined && item.category_id !== null,
    );
    const filteredItems = useMemo(() => {
        const normalizedQuery = normalize(query);

        return mergedItems.filter((item) => {
            const matchesQuery = normalize(item.name).includes(normalizedQuery);
            const matchesCategory =
                categoryId === 'all' ||
                !itemsHaveCategoryData ||
                item.category_id === categoryId;

            return matchesQuery && matchesCategory;
        });
    }, [categoryId, itemsHaveCategoryData, mergedItems, query]);

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
                        Pesquise e selecione itens; suas seleções continuam ao
                        trocar de página ou filtro.
                    </p>
                </div>
                <Badge variant="secondary" className="rounded-full px-3">
                    {selectedIds.length} selecionado
                    {selectedIds.length === 1 ? '' : 's'}
                </Badge>
            </div>

            <div className="grid gap-2 sm:grid-cols-[1fr_minmax(220px,320px)]">
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={query}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setPage(1);
                        }}
                        placeholder={`Buscar ${kind === 'service' ? 'serviço' : 'produto'}`}
                        className="h-10 pl-9"
                        aria-label={`Buscar ${kind === 'service' ? 'serviço' : 'produto'}`}
                    />
                </div>
                <RemoteOptionPicker
                    id="commission_category_filter"
                    name="category_filter"
                    options={categories}
                    placeholder="Todas as categorias"
                    resource="categories"
                    selectedOption={categories.find(
                        (category) => category.id === categoryId,
                    )}
                    value={categoryId === 'all' ? '' : categoryId}
                    onChange={(value) => {
                        setCategoryId(value || 'all');
                        setPage(1);
                    }}
                />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2 border-y border-border/70 py-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={toggleVisible}
                    disabled={visibleIds.length === 0}
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
                <span
                    className="text-xs text-muted-foreground"
                    aria-live="polite"
                >
                    {loading
                        ? 'Carregando…'
                        : `${filteredItems.length} resultado${filteredItems.length === 1 ? '' : 's'}`}
                </span>
            </div>

            {error && (
                <div
                    role="alert"
                    className="flex items-center justify-between gap-3 rounded-xl border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive"
                >
                    <span>Não foi possível carregar os itens.</span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setRetryCount((current) => current + 1)}
                    >
                        Tentar novamente
                    </Button>
                </div>
            )}

            <div className="max-h-72 space-y-1 overflow-y-auto pr-1">
                {filteredItems.length === 0 && !loading ? (
                    <div className="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
                        {query || categoryId !== 'all'
                            ? 'Nenhum item encontrado com esses filtros.'
                            : 'Nenhum item disponível.'}
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
                                    'flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 transition-colors',
                                    selected
                                        ? 'border-primary/50 bg-primary/8'
                                        : 'border-transparent hover:border-border hover:bg-background/70',
                                )}
                            >
                                <input
                                    type="checkbox"
                                    checked={selected}
                                    onChange={() => toggleItem(item.id)}
                                    className="size-4 rounded border-input text-primary accent-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    aria-label={`Selecionar ${item.name}`}
                                />
                                <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">
                                    {item.name}
                                </span>
                                <span className="text-xs whitespace-nowrap text-muted-foreground">
                                    {new Intl.NumberFormat('pt-BR', {
                                        style: 'currency',
                                        currency: 'BRL',
                                    }).format((price ?? 0) / 100)}
                                </span>
                            </label>
                        );
                    })
                )}
                {loading && (
                    <p
                        role="status"
                        className="px-2 py-3 text-center text-xs text-muted-foreground"
                    >
                        Carregando opções…
                    </p>
                )}
            </div>

            {hasMore && (
                <Button
                    type="button"
                    variant="outline"
                    className="w-full"
                    disabled={loading}
                    onClick={() => setPage((current) => current + 1)}
                >
                    {loading ? 'Carregando…' : 'Carregar mais opções'}
                </Button>
            )}

            {selectedIds.length > 0 && (
                <div className="flex flex-wrap gap-1.5 border-t border-border/70 pt-2">
                    {selectedItems.slice(0, 6).map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => toggleItem(item.id)}
                            className="inline-flex max-w-full items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-xs text-primary hover:bg-primary/20"
                            aria-label={`Remover ${item.name}`}
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
