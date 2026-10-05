import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import selectorOptions from '@/routes/selector-options';
import { Input } from '@/components/ui/input';

export type RemoteSelectorOption = {
    id: string;
    name: string;
    phone?: string | null;
    email?: string | null;
    price_cents?: number | null;
    sale_price_cents?: number | null;
    duration_minutes?: number | null;
    current_stock?: number | null;
    min_stock?: number | null;
    lock_version?: number | null;
    cost_price_cents?: number | null;
    unit_of_measure?: string | null;
    total_sessions?: number | null;
    validity_days?: number | null;
    billing_cycle?: string | null;
    type?: string | null;
    uniqueness_scope?: string | null;
};

type RemoteOptionPickerProps = {
    id: string;
    label?: string;
    name: string;
    options: RemoteSelectorOption[];
    placeholder: string;
    selectedOption?: RemoteSelectorOption;
    resource:
        | 'customers'
        | 'professionals'
        | 'services'
        | 'products'
        | 'inventory-products'
        | 'categories'
        | 'suppliers'
        | 'sale-categories'
        | 'packages'
        | 'subscription-plans';
    value: string;
    onChange: (value: string, option: RemoteSelectorOption | undefined) => void;
    disabled?: boolean;
    required?: boolean;
    localOptionsOnly?: boolean;
};

type PickerPosition = {
    left: number;
    maxHeight: number;
    top: number;
    width: number;
};

export function RemoteOptionPicker({
    id,
    label,
    name,
    options,
    placeholder,
    selectedOption,
    resource,
    value,
    onChange,
    disabled = false,
    required = false,
    localOptionsOnly = false,
}: RemoteOptionPickerProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const listboxRef = useRef<HTMLDivElement>(null);
    const optionRefs = useRef<Record<string, HTMLButtonElement | null>>({});
    const blurTimeoutRef = useRef<number | null>(null);
    const listboxId = `${id}-options`;
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [portalContainer, setPortalContainer] = useState<HTMLElement | null>(
        null,
    );
    const [selectedOptionState, setSelectedOptionState] = useState<
        RemoteSelectorOption | undefined
    >(selectedOption);
    const [remoteOptions, setRemoteOptions] = useState<RemoteSelectorOption[]>(
        [],
    );
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const [retry, setRetry] = useState(0);
    const normalizedQuery = query.trim();
    const requestKey = `${resource}:${normalizedQuery}`;
    const requestSequenceRef = useRef(0);
    const loadedKeyRef = useRef(requestKey);
    const [loadedKey, setLoadedKey] = useState(requestKey);
    const [position, setPosition] = useState({
        left: 0,
        maxHeight: 0,
        top: 0,
        width: 0,
    });

    const getPortalContainer = (): HTMLElement =>
        inputRef.current?.closest<HTMLElement>(
            '[data-slot="dialog-content"]',
        ) ?? document.body;

    useEffect(() => {
        if (localOptionsOnly) {
            return;
        }

        if (!open) {
            return;
        }

        const requestSequence = ++requestSequenceRef.current;
        const controller = new AbortController();
        const isNewRequestKey = requestKey !== loadedKeyRef.current;
        const requestPage = isNewRequestKey ? 1 : page;
        const timeout = window.setTimeout(() => {
            setLoading(true);
            setError(false);
            void fetch(
                selectorOptions.index.url(resource, {
                    query: {
                        ...(normalizedQuery ? { search: normalizedQuery } : {}),
                        page: requestPage,
                        per_page: 25,
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

                    return response.json();
                })
                .then(
                    (
                        payload: {
                            data?: RemoteSelectorOption[];
                            meta?: { has_more?: boolean };
                        } | null,
                    ) => {
                        if (requestSequence !== requestSequenceRef.current) {
                            return;
                        }

                        setRemoteOptions((current) =>
                            requestPage === 1 || isNewRequestKey
                                ? (payload?.data ?? [])
                                : [...current, ...(payload?.data ?? [])],
                        );
                        setHasMore(payload?.meta?.has_more === true);
                        loadedKeyRef.current = requestKey;
                        setLoadedKey(requestKey);
                    },
                )
                .catch((reason: unknown) => {
                    if (
                        reason instanceof DOMException &&
                        reason.name === 'AbortError'
                    ) {
                        return;
                    }

                    if (requestSequence === requestSequenceRef.current) {
                        setError(true);
                    }
                })
                .finally(() => {
                    if (requestSequence === requestSequenceRef.current) {
                        setLoading(false);
                    }
                });
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [
        localOptionsOnly,
        normalizedQuery,
        open,
        page,
        query,
        requestKey,
        resource,
        retry,
    ]);

    const updatePosition = useCallback(() => {
        const anchor = inputRef.current;

        if (!anchor) {
            return;
        }

        const rect = anchor.getBoundingClientRect();
        const viewport = window.visualViewport;
        const padding = 12;
        const viewportLeft = viewport?.offsetLeft ?? 0;
        const viewportTop = viewport?.offsetTop ?? 0;
        const viewportWidth = viewport?.width ?? window.innerWidth;
        const viewportHeight = viewport?.height ?? window.innerHeight;
        const viewportRight = viewportLeft + viewportWidth;
        const viewportBottom = viewportTop + viewportHeight;
        const containerRect =
            portalContainer && portalContainer !== document.body
                ? portalContainer.getBoundingClientRect()
                : null;
        const boundsLeft = Math.max(
            viewportLeft + padding,
            containerRect?.left ?? viewportLeft + padding,
        );
        const boundsRight = Math.min(
            viewportRight - padding,
            containerRect?.right ?? viewportRight - padding,
        );
        const boundsTop = Math.max(
            viewportTop + padding,
            containerRect?.top ?? viewportTop + padding,
        );
        const boundsBottom = Math.min(
            viewportBottom - padding,
            containerRect?.bottom ?? viewportBottom - padding,
        );
        const spaceBelow = Math.max(0, boundsBottom - rect.bottom - 4);
        const spaceAbove = Math.max(0, rect.top - boundsTop - 4);
        const opensBelow = spaceBelow >= 180 || spaceBelow >= spaceAbove;
        const availableSpace = opensBelow ? spaceBelow : spaceAbove;
        const width = Math.min(
            rect.width,
            Math.max(0, boundsRight - boundsLeft),
        );
        const maxHeight = Math.min(availableSpace, 320);
        const left = Math.min(
            Math.max(rect.left, boundsLeft),
            Math.max(boundsLeft, boundsRight - width),
        );
        const top = opensBelow
            ? Math.min(rect.bottom + 4, boundsBottom - maxHeight)
            : Math.max(boundsTop, rect.top - maxHeight - 4);
        const containerLeft = containerRect?.left ?? 0;
        const containerTop = containerRect?.top ?? 0;
        const nextPosition: PickerPosition = {
            left: left - containerLeft,
            maxHeight: Math.max(0, maxHeight),
            top: top - containerTop,
            width,
        };

        setPosition((current) =>
            current.left === nextPosition.left &&
            current.maxHeight === nextPosition.maxHeight &&
            current.top === nextPosition.top &&
            current.width === nextPosition.width
                ? current
                : nextPosition,
        );
    }, [portalContainer]);

    useEffect(() => {
        if (!open) {
            return;
        }

        updatePosition();
        window.addEventListener('resize', updatePosition);
        window.addEventListener('scroll', updatePosition, true);
        window.visualViewport?.addEventListener('resize', updatePosition);
        window.visualViewport?.addEventListener('scroll', updatePosition);
        const resizeObserver =
            typeof ResizeObserver !== 'undefined'
                ? new ResizeObserver(updatePosition)
                : null;

        if (inputRef.current) {
            resizeObserver?.observe(inputRef.current);
        }

        return () => {
            window.removeEventListener('resize', updatePosition);
            window.removeEventListener('scroll', updatePosition, true);
            window.visualViewport?.removeEventListener(
                'resize',
                updatePosition,
            );
            window.visualViewport?.removeEventListener(
                'scroll',
                updatePosition,
            );
            resizeObserver?.disconnect();
        };
    }, [open, updatePosition]);

    const displayedSelectedOption =
        selectedOption?.id === value
            ? selectedOption
            : selectedOptionState?.id === value
              ? selectedOptionState
              : undefined;
    const mergedOptions = useMemo(
        () =>
            Array.from(
                new Map(
                    [
                        ...options,
                        ...(displayedSelectedOption
                            ? [displayedSelectedOption]
                            : []),
                        ...(!localOptionsOnly && loadedKey === requestKey
                            ? remoteOptions
                            : []),
                    ].map((option) => [option.id, option]),
                ).values(),
            ),
        [
            loadedKey,
            localOptionsOnly,
            options,
            remoteOptions,
            requestKey,
            displayedSelectedOption,
        ],
    );
    const selected = mergedOptions.find((option) => option.id === value);
    const selectOption = (option: RemoteSelectorOption): void => {
        onChange(option.id, option);
        setSelectedOptionState(option);
        setQuery('');
        setPage(1);
        setActiveIndex(0);
        setOpen(false);
    };
    const normalize = (value: string) =>
        value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('pt-BR');
    const visibleOptions = mergedOptions.filter((option) =>
        normalize(option.name).includes(normalize(query)),
    );
    const safeActiveIndex = visibleOptions.length
        ? Math.min(Math.max(activeIndex, -1), visibleOptions.length - 1)
        : -1;

    const scrollOptionIntoView = useCallback(
        (optionId: string | undefined): void => {
            if (!optionId) {
                return;
            }

            const listbox = listboxRef.current;
            const optionKey = optionId.startsWith(`${listboxId}-`)
                ? optionId.slice(listboxId.length + 1)
                : optionId;
            const option = optionRefs.current[optionKey];

            if (!listbox || !option) {
                return;
            }

            const listboxRect = listbox.getBoundingClientRect();
            const optionRect = option.getBoundingClientRect();

            if (optionRect.top < listboxRect.top) {
                listbox.scrollTop -= listboxRect.top - optionRect.top;
            } else if (optionRect.bottom > listboxRect.bottom) {
                listbox.scrollTop += optionRect.bottom - listboxRect.bottom;
            }
        },
        [listboxId],
    );

    useEffect(() => {
        if (!open) {
            return;
        }

        const activeKey =
            safeActiveIndex === -1
                ? 'placeholder'
                : visibleOptions[safeActiveIndex]?.id;

        scrollOptionIntoView(
            activeKey ? `${listboxId}-${activeKey}` : undefined,
        );
    }, [
        listboxId,
        open,
        position,
        safeActiveIndex,
        scrollOptionIntoView,
        visibleOptions,
    ]);

    const clearSelection = (): void => {
        onChange('', undefined);
        setSelectedOptionState(undefined);
        setQuery('');
        setPage(1);
        setActiveIndex(-1);
        setOpen(false);
    };

    const listbox = open
        ? createPortal(
              <div
                  id={listboxId}
                  ref={listboxRef}
                  role="listbox"
                  className="fixed z-[100] overflow-y-auto rounded-md border border-input bg-popover p-1 text-sm shadow-lg"
                  style={{
                      left: position.left,
                      maxHeight: position.maxHeight,
                      top: position.top,
                      width: position.width,
                  }}
                  onScroll={(event) => {
                      const element = event.currentTarget;

                      if (
                          hasMore &&
                          !loading &&
                          element.scrollTop + element.clientHeight >=
                              element.scrollHeight - 24
                      ) {
                          setPage((current) => current + 1);
                      }
                  }}
              >
                  <button
                      type="button"
                      role="option"
                      id={`${listboxId}-placeholder`}
                      aria-selected={value === ''}
                      ref={(element) => {
                          optionRefs.current.placeholder = element;
                      }}
                      onMouseDown={(event) => event.preventDefault()}
                      onClick={() => {
                          clearSelection();
                      }}
                      className={`w-full rounded-sm px-2 py-1.5 text-left text-muted-foreground hover:bg-accent ${safeActiveIndex === -1 ? 'bg-accent' : ''}`}
                  >
                      {placeholder}
                  </button>
                  {visibleOptions.map((option, index) => (
                      <button
                          key={option.id}
                          id={`${listboxId}-${option.id}`}
                          ref={(element) => {
                              optionRefs.current[option.id] = element;
                          }}
                          type="button"
                          role="option"
                          aria-selected={option.id === value}
                          className={`w-full rounded-sm px-2 py-1.5 text-left hover:bg-accent ${index === safeActiveIndex ? 'bg-accent' : ''}`}
                          onMouseDown={(event) => {
                              event.preventDefault();

                              if (blurTimeoutRef.current !== null) {
                                  window.clearTimeout(blurTimeoutRef.current);
                                  blurTimeoutRef.current = null;
                              }
                          }}
                          onClick={() => selectOption(option)}
                      >
                          {option.name}
                      </button>
                  ))}
                  {loading && (
                      <p className="px-2 py-2 text-center text-xs text-muted-foreground">
                          Carregando…
                      </p>
                  )}
                  {error && (
                      <button
                          type="button"
                          className="w-full px-2 py-2 text-center text-xs text-destructive hover:underline"
                          onMouseDown={(event) => {
                              event.preventDefault();

                              if (blurTimeoutRef.current !== null) {
                                  window.clearTimeout(blurTimeoutRef.current);
                                  blurTimeoutRef.current = null;
                              }
                          }}
                          onClick={() => {
                              setRetry((current) => current + 1);
                              setOpen(true);
                              inputRef.current?.focus();
                          }}
                      >
                          Não foi possível carregar. Tentar novamente
                      </button>
                  )}
                  {!loading && !error && visibleOptions.length === 0 && (
                      <p className="px-2 py-3 text-center text-xs text-muted-foreground">
                          Nenhum resultado encontrado.
                      </p>
                  )}
              </div>,
              portalContainer ?? document.body,
          )
        : null;

    return (
        <div className="relative">
            {label && (
                <label
                    htmlFor={id}
                    className="mb-1 block text-xs font-medium text-muted-foreground"
                >
                    {label}
                </label>
            )}
            <input type="hidden" name={name} value={value} />
            <Input
                id={id}
                ref={inputRef}
                role="combobox"
                aria-expanded={open}
                aria-controls={listboxId}
                aria-autocomplete="list"
                aria-activedescendant={
                    open && safeActiveIndex === -1
                        ? `${listboxId}-placeholder`
                        : open && visibleOptions[safeActiveIndex]
                          ? `${listboxId}-${visibleOptions[safeActiveIndex].id}`
                          : undefined
                }
                aria-required={required}
                aria-label={label ?? placeholder}
                disabled={disabled}
                required={required && !value}
                value={
                    open
                        ? query || (selected?.name ?? '')
                        : (selected?.name ?? '')
                }
                placeholder={placeholder}
                onFocus={() => {
                    if (blurTimeoutRef.current !== null) {
                        window.clearTimeout(blurTimeoutRef.current);
                        blurTimeoutRef.current = null;
                    }

                    setPortalContainer(getPortalContainer());
                    setOpen(true);
                }}
                onClick={() => {
                    setPortalContainer(getPortalContainer());
                    setOpen(true);
                }}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setPage(1);
                    setActiveIndex(0);
                    setOpen(true);
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Escape') {
                        setOpen(false);
                    } else if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        setOpen(true);
                        const nextIndex =
                            visibleOptions.length === 0
                                ? -1
                                : Math.min(
                                      safeActiveIndex + 1,
                                      visibleOptions.length - 1,
                                  );
                        setActiveIndex(nextIndex);
                        scrollOptionIntoView(
                            nextIndex === -1
                                ? `${listboxId}-placeholder`
                                : visibleOptions[nextIndex]
                                  ? `${listboxId}-${visibleOptions[nextIndex].id}`
                                  : undefined,
                        );
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        const nextIndex = Math.max(safeActiveIndex - 1, -1);
                        setActiveIndex(nextIndex);
                        scrollOptionIntoView(
                            nextIndex === -1
                                ? `${listboxId}-placeholder`
                                : visibleOptions[nextIndex]
                                  ? `${listboxId}-${visibleOptions[nextIndex].id}`
                                  : undefined,
                        );
                    } else if (event.key === 'Enter' && open) {
                        event.preventDefault();
                        const option = visibleOptions[safeActiveIndex];

                        if (option) {
                            selectOption(option);
                        } else if (safeActiveIndex === -1) {
                            clearSelection();
                        }
                    }
                }}
                onBlur={() => {
                    blurTimeoutRef.current = window.setTimeout(() => {
                        setOpen(false);
                        blurTimeoutRef.current = null;
                    }, 120);
                }}
            />
            {listbox}
        </div>
    );
}
