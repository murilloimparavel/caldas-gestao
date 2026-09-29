import { Check, Package, Search, Scissors, X } from 'lucide-react';
import type { KeyboardEvent, ReactNode } from 'react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { formatMoney } from '@/components/operational';
import { cn } from '@/lib/utils';

export type CatalogPickerOption = {
    id: string;
    name: string;
    price_cents: number;
    duration_minutes?: number;
    current_stock?: number;
};

type CatalogPickerProps = {
    action?: ReactNode;
    error?: unknown;
    id?: string;
    kind: 'service' | 'product';
    label?: string;
    name: string;
    onChange: (value: string) => void;
    options: CatalogPickerOption[];
    required?: boolean;
    value: string;
};

function normalize(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('pt-BR');
}

function errorMessage(error: unknown): string | undefined {
    if (typeof error === 'string') {
        return error;
    }

    if (Array.isArray(error)) {
        return error.find(
            (message): message is string => typeof message === 'string',
        );
    }

    return undefined;
}

export function CatalogPicker({
    action,
    error,
    id: providedId,
    kind,
    label,
    name,
    onChange,
    options,
    required = false,
    value,
}: CatalogPickerProps) {
    const generatedId = useId();
    const inputId = providedId ?? `${generatedId}-input`;
    const listId = `${inputId}-listbox`;
    const rootRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);
    const selected = useMemo(
        () => options.find((option) => option.id === value),
        [options, value],
    );
    const filteredOptions = useMemo(() => {
        const normalizedQuery = normalize(query.trim());

        if (!normalizedQuery) {
            return options;
        }

        return options.filter((option) =>
            normalize(option.name).includes(normalizedQuery),
        );
    }, [options, query]);
    const message = errorMessage(error);
    const Icon = kind === 'service' ? Scissors : Package;
    const displayLabel = label ?? (kind === 'service' ? 'serviço' : 'produto');

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
        setOpen(true);
        setActiveIndex(
            Math.max(
                0,
                selected
                    ? filteredOptions.findIndex(
                          (option) => option.id === selected.id,
                      )
                    : 0,
            ),
        );
    };

    const selectOption = (option: CatalogPickerOption) => {
        onChange(option.id);
        setQuery('');
        setOpen(false);
    };

    const clearSelection = () => {
        onChange('');
        setQuery('');
        setOpen(true);
        requestAnimationFrame(() => inputRef.current?.focus());
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLElement>) => {
        if (!open && ['Enter', ' ', 'ArrowDown'].includes(event.key)) {
            event.preventDefault();
            openPicker();

            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            setActiveIndex((index) =>
                Math.min(index + 1, Math.max(filteredOptions.length - 1, 0)),
            );
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter' && open) {
            event.preventDefault();
            const option = filteredOptions[activeIndex];

            if (option) {
                selectOption(option);
            }
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
        }
    };

    return (
        <div ref={rootRef} className="min-w-0 space-y-1.5">
            {label || action ? (
                <div className="flex items-center justify-between gap-3">
                    {label ? (
                        <label
                            htmlFor={inputId}
                            className="block text-sm font-medium text-foreground"
                        >
                            {label}
                            {required ? (
                                <span className="ml-1 text-destructive">*</span>
                            ) : null}
                        </label>
                    ) : (
                        <span />
                    )}
                    {action}
                </div>
            ) : null}
            <div className="relative">
                {selected ? (
                    <div className="flex min-h-11 items-center gap-3 rounded-xl border border-primary/35 bg-primary/[0.045] px-3 py-2">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Icon className="size-4" aria-hidden="true" />
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-semibold text-foreground">
                                {selected.name}
                            </span>
                            <span className="block truncate text-xs text-muted-foreground">
                                {formatMoney(selected.price_cents)}
                                {kind === 'service' &&
                                selected.duration_minutes != null
                                    ? ` · ${selected.duration_minutes} min`
                                    : ` · Estoque: ${selected.current_stock ?? 0}`}
                            </span>
                        </span>
                        <button
                            type="button"
                            onClick={clearSelection}
                            className="flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                            aria-label={`Trocar ${displayLabel.toLocaleLowerCase('pt-BR')}`}
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
                                setQuery(event.target.value);
                                setOpen(true);
                                setActiveIndex(0);
                            }}
                            onFocus={openPicker}
                            onKeyDown={handleKeyDown}
                            placeholder={`Buscar ${displayLabel.toLocaleLowerCase('pt-BR')} por nome...`}
                            autoComplete="off"
                            required={required}
                            aria-autocomplete="list"
                            aria-controls={listId}
                            aria-expanded={open}
                            aria-haspopup="listbox"
                            aria-activedescendant={
                                open && filteredOptions.length > 0
                                    ? `${listId}-option-${activeIndex}`
                                    : undefined
                            }
                            aria-invalid={Boolean(message)}
                            className="h-11 w-full rounded-xl border border-input bg-background pr-3 pl-10 text-sm shadow-xs transition-[border-color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                        />
                    </div>
                )}
                <input type="hidden" name={name} value={value} />
                {open ? (
                    <div
                        id={listId}
                        role="listbox"
                        aria-label={`Resultados de ${displayLabel.toLocaleLowerCase('pt-BR')}`}
                        className="absolute z-50 mt-2 max-h-72 w-full overflow-y-auto rounded-2xl border border-border bg-popover p-1.5 shadow-xl shadow-black/10"
                    >
                        <div className="px-3 py-2 text-xs text-muted-foreground">
                            {filteredOptions.length}{' '}
                            {filteredOptions.length === 1
                                ? 'resultado encontrado'
                                : 'resultados encontrados'}
                        </div>
                        {filteredOptions.map((option, index) => (
                            <button
                                key={option.id}
                                id={`${listId}-option-${index}`}
                                type="button"
                                role="option"
                                aria-selected={value === option.id}
                                onMouseDown={(event) => event.preventDefault()}
                                onMouseEnter={() => setActiveIndex(index)}
                                onClick={() => selectOption(option)}
                                className={cn(
                                    'flex min-h-12 w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition-colors',
                                    activeIndex === index
                                        ? 'bg-primary/10'
                                        : 'hover:bg-muted/70',
                                )}
                            >
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                    <Icon
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-semibold text-foreground">
                                        {option.name}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {formatMoney(option.price_cents)}
                                        {kind === 'service' &&
                                        option.duration_minutes != null
                                            ? ` · ${option.duration_minutes} min`
                                            : ` · Estoque: ${option.current_stock ?? 0}`}
                                    </span>
                                </span>
                                {value === option.id ? (
                                    <Check
                                        className="size-4 text-primary"
                                        aria-hidden="true"
                                    />
                                ) : null}
                            </button>
                        ))}
                        {filteredOptions.length === 0 ? (
                            <div
                                role="status"
                                className="px-3 py-5 text-center text-sm text-muted-foreground"
                            >
                                Nenhum {displayLabel.toLocaleLowerCase('pt-BR')}{' '}
                                encontrado
                            </div>
                        ) : null}
                    </div>
                ) : null}
            </div>
            {message ? (
                <p className="text-sm text-destructive" role="alert">
                    {message}
                </p>
            ) : null}
        </div>
    );
}
