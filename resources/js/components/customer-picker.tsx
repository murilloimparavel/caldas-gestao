import { Check, Search, UserRound, X } from 'lucide-react';
import type { KeyboardEvent, ReactNode } from 'react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';

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
    value: string;
};

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
    value,
}: CustomerPickerProps) {
    const generatedId = useId();
    const inputId = providedId ?? `${generatedId}-input`;
    const listId = `${inputId}-listbox`;
    const labelId = `${inputId}-label`;
    const helperId = `${inputId}-helper`;
    const errorId = `${inputId}-error`;
    const rootRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);

    const selected = useMemo(
        () => options.find((option) => option.id === value),
        [options, value],
    );
    const normalizedQuery = normalizeSearch(query);
    const phoneQuery = query.replace(/\D/g, '');
    const filteredOptions = useMemo(() => {
        const filtered = options.filter((option) => {
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
    }, [normalizedQuery, options, phoneQuery]);
    const pickerOptions =
        selected || anonymousSelected ? options : filteredOptions;
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
        [helper ? helperId : undefined, errorMessage ? errorId : undefined]
            .filter(Boolean)
            .join(' ') || undefined;
    const visibleActiveIndex = Math.min(
        activeIndex,
        Math.max(optionCount - 1, 0),
    );

    useEffect(() => {
        const handlePointerDown = (event: PointerEvent) => {
            if (!rootRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', handlePointerDown);

        return () =>
            document.removeEventListener('pointerdown', handlePointerDown);
    }, []);

    const openPicker = () => {
        if (disabled) {
            return;
        }

        setOpen(true);
        const selectedIndex = anonymousSelected
            ? anonymousIndex
            : selected
              ? pickerOptions.findIndex((option) => option.id === selected.id) +
                optionOffset
              : 0;

        setActiveIndex(
            anonymousSelected
                ? anonymousIndex
                : selected
                  ? Math.max(selectedIndex, optionOffset)
                  : 0,
        );
    };

    const clearSelection = () => {
        onChange('');
        onAnonymousChange?.(false);
        setQuery('');
        setOpen(true);
        requestAnimationFrame(() => inputRef.current?.focus());
    };

    const selectAnonymous = () => {
        if (!allowAnonymous) {
            return;
        }

        onChange('');
        onAnonymousChange?.(true);
        setQuery('');
        setOpen(false);
    };

    const selectOption = (option: CustomerPickerOption) => {
        onChange(option.id);
        onAnonymousChange?.(false);
        setQuery('');
        setOpen(false);
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
                {selected || anonymousSelected ? (
                    <div className="relative">
                        <button
                            id={inputId}
                            type="button"
                            className={cn(
                                'flex min-h-11 w-full items-center gap-3 rounded-xl border border-primary/35 bg-primary/[0.045] px-3 py-2 pr-12 text-left shadow-xs transition-colors',
                                disabled && 'cursor-not-allowed opacity-70',
                            )}
                            role="combobox"
                            aria-controls={listId}
                            aria-expanded={open}
                            aria-haspopup="listbox"
                            aria-labelledby={label ? labelId : undefined}
                            aria-required={required}
                            aria-activedescendant={
                                open && optionCount > 0
                                    ? `${listId}-option-${visibleActiveIndex}`
                                    : undefined
                            }
                            disabled={disabled}
                            onClick={openPicker}
                            onKeyDown={handleKeyDown}
                        >
                            <CustomerAvatar
                                name={selected?.name ?? anonymousLabel}
                                anonymous={!selected}
                            />
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-semibold text-foreground">
                                    {selected?.name ?? anonymousLabel}
                                </span>
                                <span className="block truncate text-xs text-muted-foreground">
                                    {selected?.phone ??
                                        (selected
                                            ? 'Cliente cadastrado'
                                            : 'Atendimento sem identificação')}
                                </span>
                            </span>
                        </button>
                        <button
                            type="button"
                            className="absolute top-1/2 right-2 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                            aria-label="Trocar cliente"
                            onClick={() => {
                                clearSelection();
                            }}
                            disabled={disabled}
                        >
                            <X className="size-4" aria-hidden="true" />
                        </button>
                    </div>
                ) : (
                    <div className="relative">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <input
                            ref={inputRef}
                            id={inputId}
                            type="text"
                            role="combobox"
                            value={query}
                            onChange={(event) => {
                                const nextQuery = event.target.value;

                                event.currentTarget.setCustomValidity(
                                    required && nextQuery.trim()
                                        ? 'Selecione um cliente da lista.'
                                        : '',
                                );
                                setQuery(nextQuery);
                                setOpen(true);
                                setActiveIndex(allowAnonymous ? 0 : 0);
                            }}
                            onFocus={openPicker}
                            onKeyDown={handleKeyDown}
                            placeholder={placeholder}
                            autoComplete="off"
                            disabled={disabled}
                            required={
                                required && !selected && !anonymousSelected
                            }
                            aria-autocomplete="list"
                            aria-controls={open ? listId : undefined}
                            aria-expanded={open}
                            aria-haspopup="listbox"
                            aria-labelledby={label ? labelId : undefined}
                            aria-describedby={describedBy}
                            aria-activedescendant={
                                open && optionCount > 0
                                    ? `${listId}-option-${visibleActiveIndex}`
                                    : undefined
                            }
                            aria-invalid={Boolean(error)}
                            aria-required={required}
                            className="h-11 w-full rounded-xl border border-input bg-background pr-3 pl-10 text-sm shadow-xs transition-[border-color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-70"
                        />
                    </div>
                )}

                <input type="hidden" name={name} value={value} />

                {open && !disabled ? (
                    <div
                        id={listId}
                        role="listbox"
                        aria-label="Resultados de clientes"
                        className="absolute z-50 mt-2 max-h-80 w-full overflow-y-auto rounded-2xl border border-border bg-popover p-1.5 shadow-xl shadow-black/10 outline-none dark:shadow-black/30"
                    >
                        {allowAnonymous ? (
                            <button
                                id={`${listId}-option-${anonymousIndex}`}
                                type="button"
                                role="option"
                                aria-selected={anonymousSelected}
                                onMouseDown={(event) => event.preventDefault()}
                                onMouseEnter={() =>
                                    setActiveIndex(anonymousIndex)
                                }
                                onClick={selectAnonymous}
                                className={cn(
                                    'flex min-h-12 w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition-colors',
                                    visibleActiveIndex === anonymousIndex
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
                                        Abrir sem vincular a um cadastro
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
                            const isActive = visibleActiveIndex === optionIndex;
                            const isSelected = value === option.id;

                            return (
                                <button
                                    key={option.id}
                                    id={`${listId}-option-${optionIndex}`}
                                    type="button"
                                    role="option"
                                    aria-selected={isSelected}
                                    onMouseDown={(event) =>
                                        event.preventDefault()
                                    }
                                    onMouseEnter={() =>
                                        setActiveIndex(optionIndex)
                                    }
                                    onClick={() => selectOption(option)}
                                    className={cn(
                                        'flex min-h-12 w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition-colors',
                                        isActive
                                            ? 'bg-primary/10'
                                            : 'hover:bg-muted/70',
                                    )}
                                >
                                    <CustomerAvatar name={option.name} />
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

                        {pickerOptions.length === 0
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
                                          Tente outro nome ou telefone.
                                      </p>
                                  </div>
                              ))
                            : null}
                    </div>
                ) : null}
            </div>
            {errorMessage ? (
                <InputError id={errorId} message={errorMessage} role="alert" />
            ) : null}
        </div>
    );
}
