import { Check, ChevronDown, Clock3, Package, Search, X } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
} from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

export type CatalogPickerItem = {
    id: string;
    name: string;
    price_cents: number;
    duration_minutes?: number;
    current_stock?: number;
};

type CatalogItemPickerProps = {
    id: string;
    name: string;
    type: 'service' | 'product';
    options: CatalogPickerItem[];
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    required?: boolean;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean | 'true' | 'false';
};

type PickerPosition = {
    left: number;
    top?: number;
    bottom?: number;
    width: number;
    maxHeight: number;
};

function normalize(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('pt-BR');
}

function formatPrice(cents: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(cents / 100);
}

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

export function CatalogItemPicker({
    id,
    name,
    type,
    options,
    value,
    onChange,
    placeholder,
    required = false,
    'aria-describedby': ariaDescribedBy,
    'aria-invalid': ariaInvalid,
}: CatalogItemPickerProps) {
    const listId = `${useId()}-listbox`;
    const rootRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const selectedActionRef = useRef<HTMLButtonElement>(null);
    const listboxRef = useRef<HTMLDivElement>(null);
    const activeOptionRef = useRef<HTMLButtonElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);
    const [position, setPosition] = useState<PickerPosition | null>(null);
    const [portalContainer, setPortalContainer] = useState<HTMLElement | null>(
        null,
    );
    const itemLabel = type === 'service' ? 'serviço' : 'produto';
    const selected = options.find((option) => option.id === value);
    const filteredOptions = useMemo(() => {
        const normalizedQuery = normalize(query.trim());

        if (!normalizedQuery) {
            return options;
        }

        return options.filter((option) =>
            normalize(option.name).includes(normalizedQuery),
        );
    }, [options, query]);

    const safeActiveIndex = filteredOptions.length
        ? Math.min(activeIndex, filteredOptions.length - 1)
        : 0;

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
                ? {
                      top: rect.bottom + 8 - containerTop,
                  }
                : {
                      bottom: containerBottom - rect.top + 8,
                  }),
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
                setQuery('');
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

        let animationFrame = 0;
        let settleUntil = 0;
        const schedulePositionUpdate = () => {
            settleUntil = Math.max(settleUntil, performance.now() + 700);
            cancelAnimationFrame(animationFrame);
            const settle = () => {
                updatePosition();

                if (performance.now() < settleUntil) {
                    animationFrame = requestAnimationFrame(settle);
                }
            };
            animationFrame = requestAnimationFrame(settle);
        };
        const handleViewportChange = () => schedulePositionUpdate();

        updatePosition();
        schedulePositionUpdate();
        window.addEventListener('resize', handleViewportChange);
        window.addEventListener('scroll', handleViewportChange, true);
        window.visualViewport?.addEventListener('resize', handleViewportChange);
        window.visualViewport?.addEventListener('scroll', handleViewportChange);
        portalContainer?.addEventListener(
            'transitionrun',
            handleViewportChange,
        );
        portalContainer?.addEventListener(
            'animationstart',
            handleViewportChange,
        );
        portalContainer?.addEventListener(
            'transitionend',
            handleViewportChange,
        );
        portalContainer?.addEventListener('animationend', handleViewportChange);

        const resizeObserver =
            typeof ResizeObserver !== 'undefined'
                ? new ResizeObserver(handleViewportChange)
                : null;

        if (rootRef.current) {
            resizeObserver?.observe(rootRef.current);
        }

        if (portalContainer && portalContainer !== document.body) {
            resizeObserver?.observe(portalContainer);
        }

        return () => {
            cancelAnimationFrame(animationFrame);
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
            portalContainer?.removeEventListener(
                'transitionrun',
                handleViewportChange,
            );
            portalContainer?.removeEventListener(
                'animationstart',
                handleViewportChange,
            );
            portalContainer?.removeEventListener(
                'transitionend',
                handleViewportChange,
            );
            portalContainer?.removeEventListener(
                'animationend',
                handleViewportChange,
            );
            resizeObserver?.disconnect();
        };
    }, [open, portalContainer, updatePosition]);

    useEffect(() => {
        if (open) {
            activeOptionRef.current?.scrollIntoView({ block: 'nearest' });
        }
    }, [activeIndex, filteredOptions.length, open, portalContainer, position]);

    useEffect(() => {
        inputRef.current?.setCustomValidity(
            required && !value ? `Selecione um ${itemLabel}.` : '',
        );
    }, [itemLabel, required, value]);

    const openPicker = () => {
        setPortalContainer(
            rootRef.current?.closest<HTMLElement>(
                '[data-slot="dialog-content"]',
            ) ?? document.body,
        );
        setPosition(null);
        setOpen(true);
        setActiveIndex(
            Math.max(
                filteredOptions.findIndex((option) => option.id === value),
                0,
            ),
        );
        requestAnimationFrame(() => inputRef.current?.focus());
    };

    const selectOption = (option: CatalogPickerItem) => {
        onChange(option.id);
        setQuery('');
        setOpen(false);
        setPosition(null);
        setPortalContainer(null);
        requestAnimationFrame(() => selectedActionRef.current?.focus());
    };

    const clearSelection = () => {
        onChange('');
        setQuery('');
        setPortalContainer(
            rootRef.current?.closest<HTMLElement>(
                '[data-slot="dialog-content"]',
            ) ?? document.body,
        );
        setOpen(true);
        requestAnimationFrame(() => inputRef.current?.focus());
    };

    const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            setActiveIndex((index) =>
                Math.min(index + 1, Math.max(filteredOptions.length - 1, 0)),
            );
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const option = filteredOptions[safeActiveIndex];

            if (option) {
                selectOption(option);
            }
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
            setPosition(null);
            setPortalContainer(null);
            setQuery('');
            requestAnimationFrame(() => {
                if (value) {
                    selectedActionRef.current?.focus();
                } else {
                    inputRef.current?.focus();
                }
            });
        }
    };

    return (
        <div ref={rootRef} className="relative">
            <input type="hidden" id={`${id}-value`} name={name} value={value} />

            {selected && !open ? (
                <div className="flex items-center gap-3 rounded-xl border border-primary/40 bg-primary/5 px-3 py-2.5 shadow-sm">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        {type === 'service' ? (
                            <Clock3 className="size-4" />
                        ) : (
                            <Package className="size-4" />
                        )}
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold text-foreground">
                            {selected.name}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {formatPrice(selected.price_cents)}
                            {type === 'service' &&
                            selected.duration_minutes != null
                                ? ` · ${selected.duration_minutes} min`
                                : type === 'product' &&
                                    selected.current_stock != null
                                  ? ` · Estoque: ${selected.current_stock}`
                                  : null}
                        </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-1">
                        <button
                            type="button"
                            ref={selectedActionRef}
                            onClick={openPicker}
                            className="min-h-11 rounded-lg px-2 text-xs font-semibold text-primary transition-colors hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            Trocar
                        </button>
                        <button
                            type="button"
                            onClick={clearSelection}
                            aria-label={`Limpar ${itemLabel} selecionado`}
                            className="size-11 rounded-lg p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <X className="mx-auto size-4" />
                        </button>
                    </div>
                </div>
            ) : (
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        ref={inputRef}
                        id={id}
                        value={query}
                        onFocus={() => {
                            setPortalContainer(
                                rootRef.current?.closest<HTMLElement>(
                                    '[data-slot="dialog-content"]',
                                ) ?? document.body,
                            );
                            setOpen(true);
                        }}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setOpen(true);
                            setActiveIndex(0);
                        }}
                        onKeyDown={handleKeyDown}
                        onInvalid={(event) => {
                            event.currentTarget.setCustomValidity(
                                `Selecione um ${itemLabel}.`,
                            );
                            event.currentTarget.focus();
                        }}
                        placeholder={
                            placeholder ?? `Buscar ${itemLabel} por nome...`
                        }
                        required={required}
                        aria-required={required}
                        aria-describedby={ariaDescribedBy}
                        aria-invalid={ariaInvalid}
                        role="combobox"
                        aria-autocomplete="list"
                        aria-controls={listId}
                        aria-expanded={open}
                        aria-activedescendant={
                            open && filteredOptions[safeActiveIndex]
                                ? `${id}-option-${filteredOptions[safeActiveIndex].id}`
                                : undefined
                        }
                        className="h-11 w-full rounded-xl border border-input bg-background/50 pr-10 pl-9 text-sm text-foreground shadow-sm transition outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30"
                    />
                    <ChevronDown
                        className={cn(
                            'pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground transition-transform',
                            open && 'rotate-180',
                        )}
                    />
                </div>
            )}

            {open && position && portalContainer
                ? createPortal(
                      <div
                          ref={listboxRef}
                          className="fixed z-[60] overflow-hidden rounded-xl border border-border bg-popover shadow-lg shadow-black/10 dark:shadow-black/30"
                          style={{
                              left: position.left,
                              top: position.top,
                              bottom: position.bottom,
                              width: position.width,
                          }}
                      >
                          {position.maxHeight >= 88 ? (
                              <div className="flex items-center justify-between border-b border-border/70 px-3 py-2 text-[11px] text-muted-foreground">
                                  <span>
                                      {filteredOptions.length} resultado
                                      {filteredOptions.length === 1 ? '' : 's'}
                                  </span>
                                  <span className="hidden sm:inline">
                                      ↑↓ navegar · Enter selecionar
                                  </span>
                              </div>
                          ) : null}
                          <div
                              id={listId}
                              role="listbox"
                              aria-label={`Resultados de ${itemLabel}`}
                              className="overflow-y-auto p-1.5"
                              style={{
                                  maxHeight:
                                      position.maxHeight >= 88
                                          ? position.maxHeight - 42
                                          : position.maxHeight,
                              }}
                          >
                              {filteredOptions.length === 0 ? (
                                  <div className="px-3 py-7 text-center text-sm text-muted-foreground">
                                      Nenhum {itemLabel} encontrado.
                                  </div>
                              ) : (
                                  filteredOptions.map((option, index) => (
                                      <button
                                          key={option.id}
                                          type="button"
                                          id={`${id}-option-${option.id}`}
                                          role="option"
                                          aria-selected={option.id === value}
                                          ref={
                                              index === safeActiveIndex
                                                  ? activeOptionRef
                                                  : undefined
                                          }
                                          onMouseEnter={() =>
                                              setActiveIndex(index)
                                          }
                                          onClick={() => selectOption(option)}
                                          className={cn(
                                              'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors',
                                              index === safeActiveIndex
                                                  ? 'bg-primary/10 text-foreground'
                                                  : 'hover:bg-muted/70',
                                          )}
                                      >
                                          <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                              {type === 'service' ? (
                                                  <Clock3 className="size-4" />
                                              ) : (
                                                  <Package className="size-4" />
                                              )}
                                          </span>
                                          <span className="min-w-0 flex-1">
                                              <span className="block truncate text-sm font-medium">
                                                  {option.name}
                                              </span>
                                              <span className="block text-xs text-muted-foreground">
                                                  {type === 'service' &&
                                                  option.duration_minutes !=
                                                      null
                                                      ? `${option.duration_minutes} min`
                                                      : type === 'product' &&
                                                          option.current_stock !=
                                                              null
                                                        ? `Estoque: ${option.current_stock}`
                                                        : null}
                                              </span>
                                          </span>
                                          <span className="shrink-0 text-sm font-semibold text-foreground">
                                              {formatPrice(option.price_cents)}
                                          </span>
                                          {option.id === value ? (
                                              <Check className="size-4 text-primary" />
                                          ) : null}
                                      </button>
                                  ))
                              )}
                          </div>
                      </div>,
                      portalContainer,
                  )
                : null}
        </div>
    );
}
