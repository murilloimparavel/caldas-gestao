import { Check, Search, UserRound, X } from 'lucide-react';
import type { KeyboardEvent, ReactNode } from 'react';
import { createPortal } from 'react-dom';
import {
    useCallback,
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
} from 'react';
import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';
import selectorOptions from '@/routes/selector-options';

export type CustomerPickerOption = {
    id: string;
    name: string;
    phone?: string | null;
};

type CustomerPickerProps = {
    action?: ReactNode;
    allowAnonymous?: boolean;
    anonymousLabel?: string;
    anonymousSelected?: boolean;
    className?: string;
    customEmpty?: ReactNode;
    disabled?: boolean;
    error?: unknown;
    helper?: ReactNode;
    id?: string;
    label?: string;
    name?: string;
    onAnonymousChange?: (selected: boolean) => void;
    onChange: (customerId: string) => void;
    options: CustomerPickerOption[];
    placeholder?: string;
    required?: boolean;
    remoteSearch?: boolean;
    selectedOption?: CustomerPickerOption | null;
    value: string;
};

type PickerPosition = {
    left: number;
    top?: number;
    bottom?: number;
    width: number;
    maxHeight: number;
};

function positionsMatch(
    current: PickerPosition | null,
    next: PickerPosition,
): boolean {
    return (
        current?.left === next.left &&
        current?.top === next.top &&
        current?.bottom === next.bottom &&
        current?.width === next.width &&
        current?.maxHeight === next.maxHeight
    );
}

function normalizeSearch(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLocaleLowerCase('pt-BR');
}

function initials(name: string): string {
    const words = name.trim().split(/\s+/u).filter(Boolean);

    if (words.length === 0) {
        return '?';
    }

    return words
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('');
}

function CustomerAvatar({
    name,
    anonymous = false,
}: {
    name: string;
    anonymous?: boolean;
}) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'flex size-9 shrink-0 items-center justify-center rounded-xl text-xs font-bold',
                anonymous
                    ? 'bg-amber-500/12 text-amber-700 dark:text-amber-300'
                    : 'bg-primary/10 text-primary',
            )}
        >
            {anonymous ? <UserRound className="size-4" /> : initials(name)}
        </span>
    );
}

export function CustomerPicker({
    action,
    allowAnonymous = false,
    anonymousLabel = 'Cliente avulso / Não identificado',
    anonymousSelected = false,
    className,
    customEmpty,
    disabled = false,
    error,
    helper,
    id: providedId,
    label,
    name = 'customer_id',
    onAnonymousChange,
    onChange,
    options,
    placeholder = 'Buscar por nome ou telefone…',
    required = false,
    remoteSearch = true,
    selectedOption = null,
    value,
}: CustomerPickerProps) {
    const generatedId = useId();
    const inputId = providedId ?? `${generatedId}-input`;
    const listId = `${inputId}-listbox`;
    const labelId = `${inputId}-label`;
    const helperId = `${inputId}-helper`;
    const errorId = `${inputId}-error`;
    const selectionId = `${inputId}-selection`;
    const rootRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const listboxRef = useRef<HTMLDivElement>(null);
    const scrollContainerRef = useRef<HTMLDivElement>(null);
    const activeOptionRef = useRef<HTMLButtonElement>(null);
    const blurTimeoutRef = useRef<number | null>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);
    const [position, setPosition] = useState<PickerPosition | null>(null);
    const [portalContainer, setPortalContainer] = useState<HTMLElement | null>(
        null,
    );
    const [remoteOptions, setRemoteOptions] = useState<CustomerPickerOption[]>(
        [],
    );
    const [remoteLoading, setRemoteLoading] = useState(false);
    const [remoteError, setRemoteError] = useState<string | null>(null);
    const [remotePage, setRemotePage] = useState(1);
    const [remoteHasMore, setRemoteHasMore] = useState(false);
    const abortControllerRef = useRef<AbortController | null>(null);
    const requestSequenceRef = useRef(0);
    const [remoteSearchKey, setRemoteSearchKey] = useState<string | null>(null);

    const seededOptions = useMemo(
        () =>
            Array.from(
                new Map(
                    [selectedOption, ...options]
                        .filter(
                            (option): option is CustomerPickerOption =>
                                option !== null,
                        )
                        .map((option) => [option.id, option]),
                ).values(),
            ),
        [options, selectedOption],
    );
    const selected = useMemo(
        () =>
            [...remoteOptions, ...seededOptions].find(
                (option) => option.id === value,
            ),
        [remoteOptions, seededOptions, value],
    );
    const normalizedQuery = normalizeSearch(query);
    const phoneQuery = query.replace(/\D/g, '');
    const localFilteredOptions = useMemo(() => {
        const filtered = seededOptions.filter((option) => {
            if (!normalizedQuery) {
                return true;
            }

            const normalizedName = normalizeSearch(option.name);
            const normalizedPhone = (option.phone ?? '').replace(/\D/g, '');

            return (
                normalizedName.includes(normalizedQuery) ||
                (phoneQuery.length > 0 && normalizedPhone.includes(phoneQuery))
            );
        });

        return filtered.slice(0, 8);
    }, [normalizedQuery, phoneQuery, seededOptions]);
    const pickerOptions = useMemo(() => {
        const merged = new Map(
            localFilteredOptions.map((option) => [option.id, option]),
        );

        if (remoteSearch && !remoteError && remoteSearchKey === query.trim()) {
            for (const option of remoteOptions) {
                merged.set(option.id, option);
            }
        }

        if (selected) {
            merged.set(selected.id, selected);
        }

        return Array.from(merged.values());
    }, [
        localFilteredOptions,
        query,
        remoteSearchKey,
        remoteError,
        remoteOptions,
        remoteSearch,
        selected,
    ]);
    const anonymousIndex = allowAnonymous ? 0 : -1;
    const optionOffset = allowAnonymous ? 1 : 0;
    const optionCount = pickerOptions.length + optionOffset;
    const errorMessage =
        typeof error === 'string'
            ? error
            : Array.isArray(error)
              ? error.find(
                    (message): message is string => typeof message === 'string',
                )
              : undefined;
    const describedBy =
        [
            helper ? helperId : undefined,
            errorMessage ? errorId : undefined,
            selected || anonymousSelected ? selectionId : undefined,
        ]
            .filter(Boolean)
            .join(' ') || undefined;
    const visibleActiveIndex = Math.min(
        activeIndex,
        Math.max(optionCount - 1, 0),
    );

    const loadRemoteOptions = useCallback(
        async (
            search: string,
            page: number,
            append: boolean,
        ): Promise<void> => {
            if (!remoteSearch) {
                return;
            }

            abortControllerRef.current?.abort();
            const controller = new AbortController();
            const requestSequence = ++requestSequenceRef.current;

            abortControllerRef.current = controller;
            setRemoteLoading(true);
            setRemoteError(null);

            try {
                const url = selectorOptions.index.url('customers', {
                    query: {
                        search: search.trim() || undefined,
                        page,
                        per_page: 25,
                    },
                });
                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error('Não foi possível carregar os clientes.');
                }

                const payload = (await response.json()) as {
                    data?: CustomerPickerOption[];
                    meta?: { has_more?: boolean };
                };

                if (requestSequence !== requestSequenceRef.current) {
                    return;
                }

                setRemoteSearchKey(search.trim());
                setRemoteOptions((current) => {
                    if (!append) {
                        return payload.data ?? [];
                    }

                    const merged = new Map(
                        current.map((option) => [option.id, option]),
                    );

                    for (const option of payload.data ?? []) {
                        merged.set(option.id, option);
                    }

                    return Array.from(merged.values());
                });
                setRemotePage(page);
                setRemoteHasMore(Boolean(payload.meta?.has_more));
            } catch (error) {
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                ) {
                    return;
                }

                if (requestSequence === requestSequenceRef.current) {
                    setRemoteError(
                        error instanceof Error
                            ? error.message
                            : 'Não foi possível carregar os clientes.',
                    );
                    setRemoteSearchKey(null);
                    setRemoteOptions([]);
                    setRemoteHasMore(false);
                }
            } finally {
                if (requestSequence === requestSequenceRef.current) {
                    setRemoteLoading(false);
                }
            }
        },
        [remoteSearch],
    );

    useEffect(() => {
        if (!remoteSearch || !open) {
            return;
        }

        const timeout = window.setTimeout(() => {
            void loadRemoteOptions(query, 1, false);
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            abortControllerRef.current?.abort();
        };
    }, [loadRemoteOptions, open, query, remoteSearch]);

    useEffect(() => {
        return () => abortControllerRef.current?.abort();
    }, []);

    const loadMoreRemoteOptions = (): void => {
        if (!remoteSearch || remoteLoading || !remoteHasMore) {
            return;
        }

        void loadRemoteOptions(query, remotePage + 1, true);
    };

    const updatePosition = useCallback(() => {
        const anchor = rootRef.current;

        if (!anchor) {
            return;
        }

        const rect = anchor.getBoundingClientRect();
        const viewportPadding = 12;
        const visualViewport = window.visualViewport;
        const viewportLeft = visualViewport?.offsetLeft ?? 0;
        const viewportTop = visualViewport?.offsetTop ?? 0;
        const viewportWidth = visualViewport?.width ?? window.innerWidth;
        const viewportHeight = visualViewport?.height ?? window.innerHeight;
        const viewportRight = viewportLeft + viewportWidth;
        const viewportBottom = viewportTop + viewportHeight;
        const containerRect = portalContainer?.getBoundingClientRect();
        const boundsTop = Math.max(
            viewportTop + viewportPadding,
            containerRect?.top ?? viewportPadding,
        );
        const boundsBottom = Math.min(
            viewportBottom - viewportPadding,
            containerRect?.bottom ?? viewportBottom - viewportPadding,
        );
        const boundsLeft = Math.max(
            viewportLeft + viewportPadding,
            containerRect?.left ?? viewportLeft + viewportPadding,
        );
        const boundsRight = Math.min(
            viewportRight - viewportPadding,
            containerRect?.right ?? viewportRight - viewportPadding,
        );
        const spaceBelow = boundsBottom - rect.bottom - 8;
        const spaceAbove = rect.top - boundsTop - 8;
        const opensBelow = spaceBelow >= 220 || spaceBelow >= spaceAbove;
        const availableSpace = Math.max(
            0,
            Math.min(opensBelow ? spaceBelow : spaceAbove, 360),
        );
        const width = Math.max(
            0,
            Math.min(rect.width, boundsRight - boundsLeft),
        );
        const containerLeft = containerRect?.left ?? 0;
        const containerTop = containerRect?.top ?? 0;
        const containerBottom = containerRect?.bottom ?? viewportBottom;
        const nextPosition: PickerPosition = {
            left: Math.min(
                Math.max(rect.left - containerLeft, boundsLeft - containerLeft),
                boundsRight - width - containerLeft,
            ),
            ...(opensBelow
                ? { top: rect.bottom + 8 - containerTop }
                : { bottom: containerBottom - rect.top + 8 }),
            width,
            maxHeight: availableSpace,
        };

        setPosition((current) =>
            positionsMatch(current, nextPosition) ? current : nextPosition,
        );
    }, [portalContainer]);

    useEffect(() => {
        const handlePointerDown = (event: PointerEvent) => {
            const target = event.target as Node;

            if (
                !rootRef.current?.contains(target) &&
                !listboxRef.current?.contains(target)
            ) {
                setOpen(false);
                setPosition(null);
                setPortalContainer(null);
            }
        };

        document.addEventListener('pointerdown', handlePointerDown);

        return () =>
            document.removeEventListener('pointerdown', handlePointerDown);
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        const handleViewportChange = () => updatePosition();
        updatePosition();
        window.addEventListener('resize', handleViewportChange);
        window.addEventListener('scroll', handleViewportChange, true);
        window.visualViewport?.addEventListener('resize', handleViewportChange);
        window.visualViewport?.addEventListener('scroll', handleViewportChange);
        const resizeObserver =
            typeof ResizeObserver !== 'undefined'
                ? new ResizeObserver(handleViewportChange)
                : null;
        resizeObserver?.observe(rootRef.current as Element);

        return () => {
            window.removeEventListener('resize', handleViewportChange);
            window.removeEventListener('scroll', handleViewportChange, true);
            window.visualViewport?.removeEventListener(
                'resize',
                handleViewportChange,
            );
            window.visualViewport?.removeEventListener(
                'scroll',
                handleViewportChange,
            );
            resizeObserver?.disconnect();
        };
    }, [open, updatePosition]);

    useEffect(() => {
        if (open) {
            const scrollContainer = scrollContainerRef.current;
            const activeOption = activeOptionRef.current;

            if (!scrollContainer || !activeOption) {
                return;
            }

            const containerRect = scrollContainer.getBoundingClientRect();
            const optionRect = activeOption.getBoundingClientRect();

            if (optionRect.top < containerRect.top) {
                scrollContainer.scrollTop -= containerRect.top - optionRect.top;
            } else if (optionRect.bottom > containerRect.bottom) {
                scrollContainer.scrollTop +=
                    optionRect.bottom - containerRect.bottom;
            }
        }
    }, [activeIndex, open, pickerOptions.length]);

    const openPicker = () => {
        if (disabled) {
            return;
        }

        if (blurTimeoutRef.current !== null) {
            window.clearTimeout(blurTimeoutRef.current);
            blurTimeoutRef.current = null;
        }

        setPortalContainer(
            rootRef.current?.closest<HTMLElement>(
                '[data-slot="dialog-content"]',
            ) ?? document.body,
        );
        setPosition(null);
        setOpen(true);
        setActiveIndex(anonymousSelected ? anonymousIndex : 0);
        requestAnimationFrame(() => inputRef.current?.focus());
    };

    const clearSelection = () => {
        inputRef.current?.setCustomValidity('');
        onChange('');
        onAnonymousChange?.(false);
        setQuery('');
        setPortalContainer(
            rootRef.current?.closest<HTMLElement>(
                '[data-slot="dialog-content"]',
            ) ?? document.body,
        );
        setPosition(null);
        setOpen(true);
        requestAnimationFrame(() => inputRef.current?.focus());
    };

    const selectAnonymous = () => {
        if (!allowAnonymous) {
            return;
        }

        inputRef.current?.setCustomValidity('');
        onChange('');
        onAnonymousChange?.(true);
        setQuery('');
        setOpen(false);
        setPosition(null);
        setPortalContainer(null);
    };

    const selectOption = (option: CustomerPickerOption) => {
        inputRef.current?.setCustomValidity('');
        onChange(option.id);
        onAnonymousChange?.(false);
        setQuery('');
        setOpen(false);
        setPosition(null);
        setPortalContainer(null);
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLElement>) => {
        const currentActiveIndex = Math.min(
            activeIndex,
            Math.max(optionCount - 1, 0),
        );

        if (
            !open &&
            (event.key === 'Enter' ||
                event.key === ' ' ||
                event.key === 'ArrowDown')
        ) {
            event.preventDefault();
            openPicker();

            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            setActiveIndex(
                Math.min(currentActiveIndex + 1, Math.max(optionCount - 1, 0)),
            );
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex(Math.max(currentActiveIndex - 1, 0));
        } else if (event.key === 'Enter' && open) {
            event.preventDefault();

            if (allowAnonymous && currentActiveIndex === anonymousIndex) {
                selectAnonymous();
            } else {
                const option = pickerOptions[currentActiveIndex - optionOffset];

                if (option) {
                    selectOption(option);
                }
            }
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
            setPosition(null);
            setPortalContainer(null);
            requestAnimationFrame(() => inputRef.current?.focus());
        }
    };

    return (
        <div ref={rootRef} className={cn('min-w-0 space-y-1.5', className)}>
            {label ? (
                <div className="flex items-center justify-between gap-3">
                    <label
                        id={labelId}
                        htmlFor={inputId}
                        className="block text-sm font-medium text-foreground"
                    >
                        {label}
                        {required ? (
                            <span className="ml-1 text-destructive">*</span>
                        ) : null}
                    </label>
                    {action}
                </div>
            ) : null}
            {helper ? (
                <p id={helperId} className="text-xs text-muted-foreground">
                    {helper}
                </p>
            ) : null}

            <div className="relative">
                <div className="relative">
                    {!selected && !anonymousSelected ? (
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                    ) : null}
                    <input
                        ref={inputRef}
                        id={inputId}
                        type="text"
                        role="combobox"
                        value={query}
                        onChange={(event) => {
                            const nextQuery = event.target.value;
                            const selectionWillBeCleared =
                                Boolean(value) || anonymousSelected;

                            event.currentTarget.setCustomValidity(
                                required && nextQuery.trim()
                                    ? 'Selecione um cliente da lista.'
                                    : '',
                            );

                            if (selectionWillBeCleared) {
                                onChange('');
                                onAnonymousChange?.(false);
                            }

                            setQuery(nextQuery);
                            setOpen(true);
                            setActiveIndex(allowAnonymous ? 0 : 0);
                        }}
                        onFocus={openPicker}
                        onBlur={() => {
                            blurTimeoutRef.current = window.setTimeout(() => {
                                setOpen(false);
                                setPosition(null);
                                setPortalContainer(null);
                                blurTimeoutRef.current = null;
                            }, 120);
                        }}
                        onKeyDown={handleKeyDown}
                        placeholder={
                            selected || anonymousSelected ? '' : placeholder
                        }
                        autoComplete="off"
                        disabled={disabled}
                        required={required && !selected && !anonymousSelected}
                        aria-autocomplete="list"
                        aria-controls={open ? listId : undefined}
                        aria-expanded={open}
                        aria-haspopup="listbox"
                        aria-label={label ? undefined : 'Cliente'}
                        aria-labelledby={label ? labelId : undefined}
                        aria-describedby={describedBy}
                        aria-activedescendant={
                            open && optionCount > 0
                                ? `${listId}-option-${visibleActiveIndex}`
                                : undefined
                        }
                        aria-invalid={Boolean(error)}
                        aria-required={required}
                        className={cn(
                            'h-11 w-full rounded-xl border border-input bg-background pr-12 text-sm shadow-xs transition-[border-color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-70',
                            selected || anonymousSelected
                                ? 'absolute inset-0 z-10 h-full border-transparent bg-transparent px-3 text-transparent caret-transparent placeholder:text-transparent focus-visible:text-foreground focus-visible:caret-foreground'
                                : 'pl-10',
                        )}
                    />
                    {selected || anonymousSelected ? (
                        <div
                            data-slot="customer-selection"
                            className="flex min-h-11 w-full min-w-0 items-start gap-2 rounded-xl border border-input bg-background py-2 pr-12 pl-2 text-sm shadow-xs"
                        >
                            <CustomerAvatar
                                name={selected?.name ?? anonymousLabel}
                                anonymous={!selected}
                            />
                            <span
                                data-slot="customer-name"
                                className="min-w-0 flex-1 self-center py-0.5 text-sm font-semibold break-words text-foreground"
                            >
                                {selected?.name ?? anonymousLabel}
                            </span>
                        </div>
                    ) : null}
                    {selected || anonymousSelected ? (
                        <span id={selectionId} className="sr-only">
                            Cliente selecionado:{' '}
                            {selected?.name ?? anonymousLabel}. Digite para
                            pesquisar outro cliente.
                        </span>
                    ) : null}
                    {selected || anonymousSelected ? (
                        <button
                            type="button"
                            className="absolute top-1/2 right-2 z-20 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                            aria-label="Limpar cliente selecionado"
                            onClick={clearSelection}
                            disabled={disabled}
                        >
                            <X className="size-4" aria-hidden="true" />
                        </button>
                    ) : null}
                </div>

                <input type="hidden" name={name} value={value} />

                {open && !disabled && position && portalContainer
                    ? createPortal(
                          <div
                              ref={listboxRef}
                              className="fixed z-[60] overflow-hidden rounded-2xl border border-border bg-popover shadow-xl shadow-black/10 dark:shadow-black/30"
                              style={{
                                  left: position.left,
                                  top: position.top,
                                  bottom: position.bottom,
                                  width: position.width,
                              }}
                          >
                              <div className="flex items-center justify-between border-b border-border/70 px-3 py-2 text-[11px] text-muted-foreground">
                                  <span aria-live="polite">
                                      {remoteLoading
                                          ? 'Carregando clientes…'
                                          : `${pickerOptions.length} resultado${pickerOptions.length === 1 ? '' : 's'}`}
                                  </span>
                                  <span className="hidden sm:inline">
                                      ↑↓ navegar · Enter selecionar
                                  </span>
                              </div>
                              <div
                                  id={listId}
                                  ref={scrollContainerRef}
                                  role="listbox"
                                  aria-label="Resultados de clientes"
                                  className="overflow-y-auto p-1.5"
                                  onScroll={(event) => {
                                      const listbox = event.currentTarget;

                                      if (
                                          listbox.scrollTop +
                                              listbox.clientHeight >=
                                          listbox.scrollHeight - 48
                                      ) {
                                          loadMoreRemoteOptions();
                                      }
                                  }}
                                  style={{
                                      maxHeight: Math.max(
                                          position.maxHeight - 42,
                                          0,
                                      ),
                                  }}
                              >
                                  {allowAnonymous ? (
                                      <button
                                          id={`${listId}-option-${anonymousIndex}`}
                                          type="button"
                                          role="option"
                                          aria-selected={anonymousSelected}
                                          onMouseDown={(event) =>
                                              event.preventDefault()
                                          }
                                          onMouseEnter={() =>
                                              setActiveIndex(anonymousIndex)
                                          }
                                          onClick={selectAnonymous}
                                          className={cn(
                                              'flex min-h-12 w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition-colors',
                                              visibleActiveIndex ===
                                                  anonymousIndex
                                                  ? 'bg-amber-500/10'
                                                  : 'hover:bg-muted/70',
                                          )}
                                      >
                                          <CustomerAvatar
                                              name={anonymousLabel}
                                              anonymous
                                          />
                                          <span className="min-w-0 flex-1">
                                              <span className="block truncate text-sm font-semibold text-foreground">
                                                  {anonymousLabel}
                                              </span>
                                              <span className="block text-xs text-muted-foreground">
                                                  Abrir sem vincular a um
                                                  cadastro
                                              </span>
                                          </span>
                                          {anonymousSelected ? (
                                              <Check
                                                  className="size-4 text-primary"
                                                  aria-hidden="true"
                                              />
                                          ) : null}
                                      </button>
                                  ) : null}

                                  {pickerOptions.map((option, index) => {
                                      const optionIndex = index + optionOffset;
                                      const isActive =
                                          visibleActiveIndex === optionIndex;
                                      const isSelected = value === option.id;

                                      return (
                                          <button
                                              key={option.id}
                                              id={`${listId}-option-${optionIndex}`}
                                              type="button"
                                              role="option"
                                              aria-selected={isSelected}
                                              ref={
                                                  isActive
                                                      ? activeOptionRef
                                                      : undefined
                                              }
                                              onMouseDown={(event) =>
                                                  event.preventDefault()
                                              }
                                              onMouseEnter={() =>
                                                  setActiveIndex(optionIndex)
                                              }
                                              onClick={() =>
                                                  selectOption(option)
                                              }
                                              className={cn(
                                                  'flex min-h-12 w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition-colors',
                                                  isActive
                                                      ? 'bg-primary/10'
                                                      : 'hover:bg-muted/70',
                                              )}
                                          >
                                              <CustomerAvatar
                                                  name={option.name}
                                              />
                                              <span className="min-w-0 flex-1">
                                                  <span className="block truncate text-sm font-semibold text-foreground">
                                                      {option.name}
                                                  </span>
                                                  <span className="block truncate text-xs text-muted-foreground">
                                                      {option.phone ??
                                                          'Telefone não informado'}
                                                  </span>
                                              </span>
                                              {isSelected ? (
                                                  <Check
                                                      className="size-4 text-primary"
                                                      aria-hidden="true"
                                                  />
                                              ) : null}
                                          </button>
                                      );
                                  })}

                                  {remoteError ? (
                                      <div
                                          role="alert"
                                          className="px-3 py-5 text-center"
                                      >
                                          <p className="text-sm font-medium text-foreground">
                                              Não foi possível carregar os
                                              clientes
                                          </p>
                                          <p className="mt-1 text-xs text-muted-foreground">
                                              {remoteError}
                                          </p>
                                      </div>
                                  ) : null}
                                  {!remoteLoading &&
                                  !remoteError &&
                                  pickerOptions.length === 0
                                      ? (customEmpty ?? (
                                            <div
                                                role="status"
                                                className="px-3 py-5 text-center"
                                            >
                                                <UserRound
                                                    className="mx-auto size-5 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                <p className="mt-2 text-sm font-medium text-foreground">
                                                    Nenhum cliente encontrado
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Tente outro nome ou
                                                    telefone.
                                                </p>
                                            </div>
                                        ))
                                      : null}
                              </div>
                          </div>,
                          portalContainer,
                      )
                    : null}
            </div>
            {errorMessage ? (
                <InputError id={errorId} message={errorMessage} role="alert" />
            ) : null}
        </div>
    );
}
