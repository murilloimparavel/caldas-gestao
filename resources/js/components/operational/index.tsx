import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Search, UsersRound } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type ResourceStatus = 'active' | 'inactive';

export type PaginationLink = {
    active: boolean;
    label: string;
    url: string | null;
};

export type Paginated<T> = {
    current_page: number;
    data: T[];
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    per_page: number;
    to: number | null;
    total: number;
};

export type ResourceFilters = {
    search?: string;
    status?: string;
};

export type RelationOption = {
    id: string;
    name: string;
    status?: ResourceStatus;
};

export function createIdempotencyKey(scope: string): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return `${scope}-${crypto.randomUUID()}`;
    }

    return `${scope}-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export function parseBrazilianCurrency(value: string): number {
    const normalizedInput = value
        .trim()
        .replace(/\s/g, '')
        .replace(/[^\d,.-]/g, '');

    if (normalizedInput === '') {
        return 0;
    }

    let normalized = normalizedInput;

    if (normalized.includes(',')) {
        normalized = normalized.replace(/\./g, '').replace(',', '.');
    } else if (/^\d{1,3}(?:\.\d{3})+$/.test(normalized)) {
        normalized = normalized.replace(/\./g, '');
    }

    const parsed = Number.parseFloat(normalized);

    return Number.isFinite(parsed) ? Math.max(0, Math.round(parsed * 100)) : 0;
}

export function RelationCheckboxes({
    name,
    options,
    selectedIds = [],
    disabled = false,
}: {
    name: string;
    options: RelationOption[];
    selectedIds?: string[];
    disabled?: boolean;
}) {
    if (options.length === 0) {
        return (
            <p className="rounded-lg border border-dashed border-border bg-muted/40 px-3 py-2 text-xs leading-5 text-muted-foreground">
                Nenhuma opção disponível nesta unidade ainda.
            </p>
        );
    }

    return (
        <div className="grid gap-2 sm:grid-cols-2">
            {options.map((option) => {
                const inputId = `${name}-${option.id}`;

                return (
                    <label
                        key={option.id}
                        htmlFor={inputId}
                        className="flex min-h-11 items-center gap-3 rounded-lg border border-border px-3 py-2 text-sm transition-colors has-checked:border-primary/60 has-checked:bg-secondary/70"
                    >
                        <input
                            id={inputId}
                            type="checkbox"
                            name={`${name}[]`}
                            value={option.id}
                            defaultChecked={selectedIds.includes(option.id)}
                            disabled={disabled}
                            className="size-4 rounded border-input text-primary accent-primary focus-visible:ring-2 focus-visible:ring-ring"
                        />
                        <span className="min-w-0 flex-1 truncate">
                            {option.name}
                        </span>
                        {option.status === 'inactive' ? (
                            <span className="text-[10px] font-medium text-muted-foreground uppercase">
                                Inativo
                            </span>
                        ) : null}
                    </label>
                );
            })}
        </div>
    );
}

export function firstError(error: unknown): string | undefined {
    if (typeof error === 'string') {
        return error;
    }

    if (Array.isArray(error)) {
        const first = error.find((item) => typeof item === 'string');

        return typeof first === 'string' ? first : undefined;
    }

    return undefined;
}

export function FormErrorSummary({
    errors,
}: {
    errors: Record<string, unknown>;
}) {
    const messages = Object.values(errors)
        .map(firstError)
        .filter((message): message is string => Boolean(message));

    return messages.length > 0 ? (
        <div
            role="alert"
            className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
            {Array.from(new Set(messages)).map((message) => (
                <p key={message}>{message}</p>
            ))}
        </div>
    ) : null;
}

export function FormField({
    label,
    name,
    error,
    children,
}: {
    label: string;
    name: string;
    error?: unknown;
    children: ReactNode;
}) {
    const errorMessage = firstError(error);

    return (
        <div className="space-y-2">
            <label
                htmlFor={name}
                className="text-sm font-medium text-foreground"
            >
                {label}
            </label>
            {children}
            <InputError
                id={`${name}-error`}
                message={errorMessage}
                role="alert"
            />
        </div>
    );
}

export function FormActions({
    processing,
    onCancel,
    label = 'Salvar cadastro',
}: {
    processing: boolean;
    onCancel?: () => void;
    label?: string;
}) {
    return (
        <div className="flex flex-col-reverse gap-2 border-t border-border pt-4 sm:flex-row sm:justify-end">
            {onCancel ? (
                <Button type="button" variant="ghost" onClick={onCancel}>
                    Cancelar
                </Button>
            ) : null}
            <Button type="submit" disabled={processing}>
                {processing ? 'Salvando…' : label}
            </Button>
        </div>
    );
}

export function formatMoney(cents: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(cents / 100);
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return 'Não informado';
    }

    const parsed = new Date(`${value.slice(0, 10)}T12:00:00`);

    return Number.isNaN(parsed.getTime())
        ? value
        : new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(
              parsed,
          );
}

export function StatusBadge({ status }: { status: ResourceStatus }) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'rounded-full px-2.5 py-1 text-[11px] font-semibold',
                status === 'active'
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                    : 'border-border bg-muted text-muted-foreground',
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    'size-1.5 rounded-full',
                    status === 'active'
                        ? 'bg-emerald-500'
                        : 'bg-muted-foreground',
                )}
            />
            {status === 'active' ? 'Ativo' : 'Inativo'}
        </Badge>
    );
}

export function ResourceHeader({
    eyebrow,
    title,
    description,
    action,
}: {
    eyebrow: string;
    title: string;
    description: string;
    action?: ReactNode;
}) {
    return (
        <header className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div className="max-w-2xl space-y-2">
                <p className="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                    {eyebrow}
                </p>
                <div className="space-y-1.5">
                    <h1 className="font-display text-3xl leading-tight font-semibold tracking-[-0.035em] text-foreground sm:text-4xl">
                        {title}
                    </h1>
                    <p className="text-sm leading-6 text-muted-foreground sm:text-base">
                        {description}
                    </p>
                </div>
            </div>
            {action}
        </header>
    );
}

export function SearchToolbar({
    action,
    defaultValue = '',
    placeholder,
    resultLabel,
    status,
    onStatusChange,
    children,
}: {
    action: string;
    defaultValue?: string;
    placeholder: string;
    resultLabel?: string;
    status?: string;
    onStatusChange?: (status: 'active' | 'inactive' | 'all') => void;
    children?: ReactNode;
}) {
    const currentStatus = status ?? 'active';

    return (
        <div className="surface-panel flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center flex-1">
                <form
                    action={action}
                    method="get"
                    className="flex w-full items-center gap-2 sm:max-w-md"
                >
                    {status ? (
                        <input type="hidden" name="status" value={currentStatus} />
                    ) : null}
                    <div className="relative min-w-0 flex-1">
                        <Search
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            aria-label="Buscar"
                            name="search"
                            defaultValue={defaultValue}
                            placeholder={placeholder}
                            className="h-11 rounded-lg pl-9"
                        />
                    </div>
                    <Button type="submit" variant="secondary" className="shrink-0">
                        Buscar
                    </Button>
                </form>

                {onStatusChange ? (
                    <div className="flex rounded-lg border border-border bg-muted/60 p-1 text-xs font-medium">
                        {(
                            [
                                { label: 'Ativos', value: 'active' },
                                { label: 'Inativos', value: 'inactive' },
                                { label: 'Todos', value: 'all' },
                            ] as const
                        ).map((tab) => (
                            <button
                                key={tab.value}
                                type="button"
                                onClick={() => onStatusChange(tab.value)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 transition-colors',
                                    currentStatus === tab.value
                                        ? 'bg-background text-foreground shadow-xs font-semibold'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                ) : null}

                {children}
            </div>

            {resultLabel ? (
                <p className="text-xs text-muted-foreground sm:text-right shrink-0">
                    {resultLabel}
                </p>
            ) : null}
        </div>
    );
}

export function EmptyState({
    title,
    description,
    action,
}: {
    title: string;
    description: string;
    action?: ReactNode;
}) {
    return (
        <div className="surface-panel flex min-h-64 flex-col items-center justify-center gap-4 px-6 py-12 text-center">
            <div className="flex size-12 items-center justify-center rounded-2xl bg-secondary text-secondary-foreground">
                <UsersRound aria-hidden="true" className="size-5" />
            </div>
            <div className="max-w-sm space-y-1.5">
                <h2 className="text-base font-semibold text-foreground">
                    {title}
                </h2>
                <p className="text-sm leading-6 text-muted-foreground">
                    {description}
                </p>
            </div>
            {action}
        </div>
    );
}

export function Pagination({ links }: { links: PaginationLink[] }) {
    const visibleLinks = links.slice(1, -1);

    if (visibleLinks.length === 0) {
        return null;
    }

    return (
        <nav
            aria-label="Paginação"
            className="flex flex-wrap items-center justify-between gap-3"
        >
            <div className="flex items-center gap-1">
                {links[0]?.url ? (
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        aria-label="Página anterior"
                    >
                        <Link href={links[0].url} preserveScroll>
                            <ChevronLeft aria-hidden="true" />
                        </Link>
                    </Button>
                ) : null}
                {visibleLinks.map((link, index) => (
                    <Button
                        key={`${link.label}-${index}`}
                        asChild={Boolean(link.url)}
                        variant={link.active ? 'default' : 'outline'}
                        size="icon"
                        aria-current={link.active ? 'page' : undefined}
                        disabled={!link.url}
                    >
                        {link.url ? (
                            <Link href={link.url} preserveScroll>
                                {link.label}
                            </Link>
                        ) : (
                            <span>{link.label}</span>
                        )}
                    </Button>
                ))}
                {links.at(-1)?.url ? (
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        aria-label="Próxima página"
                    >
                        <Link href={links.at(-1)?.url as string} preserveScroll>
                            <ChevronRight aria-hidden="true" />
                        </Link>
                    </Button>
                ) : null}
            </div>
        </nav>
    );
}

export function PageCanvas({ children }: { children: ReactNode }) {
    return (
        <div className="dashboard-canvas flex min-h-full flex-1 flex-col gap-6 px-4 py-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:px-6 lg:px-8 lg:py-8">
            {children}
        </div>
    );
}

export function RelationList({
    items,
    emptyLabel = 'Nenhum vínculo cadastrado',
}: {
    items: Array<{ id: string; name: string }>;
    emptyLabel?: string;
}) {
    return items.length > 0 ? (
        <div className="flex flex-wrap gap-2">
            {items.map((item) => (
                <Badge
                    key={item.id}
                    variant="secondary"
                    className="rounded-full px-2.5 py-1"
                >
                    {item.name}
                </Badge>
            ))}
        </div>
    ) : (
        <span className="text-sm text-muted-foreground">{emptyLabel}</span>
    );
}
