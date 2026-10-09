import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ChevronLeft, ChevronRight, Search, UsersRound } from 'lucide-react';
import type {
    Dispatch,
    FormEvent,
    ReactElement,
    ReactNode,
    SetStateAction,
} from 'react';
import {
    Children,
    cloneElement,
    isValidElement,
    useEffect,
    useRef,
    useState,
} from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import selectorOptions from '@/routes/selector-options';

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

export type RelationResource =
    | 'categories'
    | 'customers'
    | 'packages'
    | 'products'
    | 'professionals'
    | 'sale-categories'
    | 'services'
    | 'subscription-plans';

const normalizeRelationSearch = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('pt-BR')
        .trim();

export function createIdempotencyKey(
    scope: string,
    discriminator?: string | null,
): string {
    const scopedKey = discriminator ? `${scope}-${discriminator}` : scope;

    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return `${scopedKey}-${crypto.randomUUID()}`;
    }

    return `${scopedKey}-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

/**
 * Keeps one idempotency key for a mutation attempt and exposes an explicit
 * rotation point for the next logical attempt.
 */
export function useIdempotencyKey(
    scope: string,
    discriminator?: string | null,
): [string, () => void] {
    const [key, setKey] = useState(() =>
        createIdempotencyKey(scope, discriminator),
    );

    const rotate = () => {
        setKey(createIdempotencyKey(scope, discriminator));
    };

    return [key, rotate];
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

type RemoteRelationState = {
    error: boolean;
    hasMore: boolean;
    loading: boolean;
    options: RelationOption[];
    retry: () => void;
    setPage: Dispatch<SetStateAction<number>>;
};

function useRemoteRelationOptions(
    resource: RelationResource | undefined,
    query: string,
): RemoteRelationState {
    const [options, setOptions] = useState<RelationOption[]>([]);
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);
    const [retryCount, setRetryCount] = useState(0);
    const loadedQuery = useRef(query);
    const [displayQuery, setDisplayQuery] = useState(query);

    useEffect(() => {
        if (!resource) {
            return;
        }

        const controller = new AbortController();
        const requestedPage = query === loadedQuery.current ? page : 1;
        const timeout = window.setTimeout(() => {
            setLoading(true);
            setError(false);

            void fetch(
                selectorOptions.index.url(resource, {
                    query: {
                        search: query,
                        page: requestedPage,
                        per_page: 25,
                    },
                }),
                {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            )
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error('selector-options-request-failed');
                    }

                    return (await response.json()) as {
                        data?: RelationOption[];
                        meta?: { has_more?: boolean };
                    };
                })
                .then((payload) => {
                    setOptions((current) =>
                        requestedPage === 1 || query !== loadedQuery.current
                            ? (payload.data ?? [])
                            : [
                                  ...current,
                                  ...(payload.data ?? []).filter(
                                      (option) =>
                                          !current.some(
                                              (item) => item.id === option.id,
                                          ),
                                  ),
                              ],
                    );
                    setHasMore(Boolean(payload.meta?.has_more));
                    loadedQuery.current = query;
                    setDisplayQuery(query);
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
        }, 200);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [page, query, resource, retryCount]);

    return {
        error,
        hasMore: displayQuery === query && hasMore,
        loading,
        options: displayQuery === query ? options : [],
        retry: () => setRetryCount((current) => current + 1),
        setPage,
    };
}

export function RelationCheckboxes({
    name,
    options,
    selectedIds = [],
    initialSelected,
    disabled = false,
    form,
    onSelectionChange,
    resource,
}: {
    name: string;
    options: RelationOption[];
    selectedIds?: string[];
    initialSelected?: string[];
    disabled?: boolean;
    form?: string;
    onSelectionChange?: (selectedIds: string[]) => void;
    resource?: RelationResource;
}) {
    const initialSelection =
        selectedIds.length > 0 ? selectedIds : (initialSelected ?? []);
    const [internalSelectedIds, setInternalSelectedIds] =
        useState<string[]>(initialSelection);
    const selectedOptionIds = onSelectionChange
        ? selectedIds
        : internalSelectedIds;
    const [query, setQuery] = useState('');
    const remote = useRemoteRelationOptions(resource, query);
    const selectedIdsSet = new Set(selectedOptionIds);

    const availableOptions = resource
        ? [
              ...options.filter((option) => selectedIdsSet.has(option.id)),
              ...remote.options,
          ]
        : options;
    const mergedOptions = Array.from(
        new Map(availableOptions.map((option) => [option.id, option])).values(),
    );
    const normalizedQuery = normalizeRelationSearch(query);
    const visibleOptions = mergedOptions.filter((option) =>
        normalizeRelationSearch(option.name).includes(normalizedQuery),
    );
    const hiddenSelectedIds = selectedOptionIds.filter(
        (id) => !visibleOptions.some((option) => option.id === id),
    );

    if (!resource && options.length === 0) {
        return (
            <p className="rounded-lg border border-dashed border-border bg-muted/40 px-3 py-2 text-xs leading-5 text-muted-foreground">
                Nenhuma opção disponível nesta unidade ainda.
            </p>
        );
    }

    return (
        <div className="space-y-3">
            <div className="space-y-2">
                <Input
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Pesquisar por nome"
                    aria-label={`Pesquisar ${name.replaceAll('_', ' ')}`}
                    aria-controls={`${name}-options`}
                    autoComplete="off"
                />
                {resource ? (
                    <>
                        {remote.loading ? (
                            <p
                                role="status"
                                className="text-xs text-muted-foreground"
                            >
                                Carregando opções…
                            </p>
                        ) : null}
                        {remote.error ? (
                            <div
                                role="alert"
                                className="flex items-center justify-between gap-3 rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive"
                            >
                                <span>
                                    Não foi possível carregar as opções.
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={remote.retry}
                                >
                                    Tentar novamente
                                </Button>
                            </div>
                        ) : null}
                    </>
                ) : null}
            </div>
            {hiddenSelectedIds.map((id) => (
                <input
                    key={id}
                    type="hidden"
                    name={`${name}[]`}
                    value={id}
                    form={form}
                />
            ))}
            {visibleOptions.length === 0 && !remote.loading ? (
                <p className="rounded-lg border border-dashed border-border bg-muted/40 px-3 py-2 text-xs leading-5 text-muted-foreground">
                    {query
                        ? 'Nenhum resultado encontrado.'
                        : 'Nenhuma opção disponível nesta unidade ainda.'}
                </p>
            ) : (
                <div
                    id={`${name}-options`}
                    className="grid gap-2 sm:grid-cols-2"
                >
                    {visibleOptions.map((option) => {
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
                                    form={form}
                                    checked={selectedOptionIds.includes(
                                        option.id,
                                    )}
                                    onChange={(
                                        event: FormEvent<HTMLInputElement>,
                                    ) => {
                                        const nextSelectedIds = new Set(
                                            selectedOptionIds,
                                        );

                                        if (event.currentTarget.checked) {
                                            nextSelectedIds.add(option.id);
                                        } else {
                                            nextSelectedIds.delete(option.id);
                                        }

                                        const nextIds =
                                            Array.from(nextSelectedIds);

                                        if (onSelectionChange) {
                                            onSelectionChange(nextIds);
                                        } else {
                                            setInternalSelectedIds(nextIds);
                                        }
                                    }}
                                    disabled={disabled}
                                    className="size-4 rounded border-input text-primary accent-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                />
                                <span className="min-w-0 flex-1 truncate">
                                    {option.name}
                                </span>
                                {option.status === 'inactive' ? (
                                    <span className="text-2xs font-medium text-muted-foreground uppercase">
                                        Inativo
                                    </span>
                                ) : null}
                            </label>
                        );
                    })}
                </div>
            )}
            {resource && remote.hasMore ? (
                <Button
                    type="button"
                    variant="outline"
                    className="w-full"
                    disabled={remote.loading}
                    onClick={() => remote.setPage((page) => page + 1)}
                >
                    {remote.loading ? 'Carregando…' : 'Carregar mais opções'}
                </Button>
            ) : null}
        </div>
    );
}

export function RelationQuantityFields({
    options,
    initial = {},
    resource = 'services',
    form,
}: {
    options: RelationOption[];
    initial?: Record<string, number>;
    resource?: RelationResource;
    form?: string;
}) {
    const [selected, setSelected] = useState<Record<string, number>>(initial);
    const [query, setQuery] = useState('');
    const remote = useRemoteRelationOptions(resource, query);
    const selectedIds = Object.keys(selected).filter(
        (id) => (selected[id] ?? 0) > 0,
    );
    const selectedIdsSet = new Set(selectedIds);
    const mergedOptions = Array.from(
        new Map(
            [
                ...options.filter((option) => selectedIdsSet.has(option.id)),
                ...remote.options,
            ].map((option) => [option.id, option]),
        ).values(),
    );
    const normalizedQuery = normalizeRelationSearch(query);
    const visibleOptions = mergedOptions.filter((option) =>
        normalizeRelationSearch(option.name).includes(normalizedQuery),
    );
    const hiddenSelectedIds = selectedIds.filter(
        (id) => !visibleOptions.some((option) => option.id === id),
    );
    const totalSessions = selectedIds.reduce(
        (total, id) => total + (selected[id] ?? 0),
        0,
    );

    const updateQuantity = (id: string, quantity: number): void => {
        setSelected((current) => ({
            ...current,
            [id]: quantity,
        }));
    };

    return (
        <div className="space-y-3">
            <input
                type="hidden"
                name="total_sessions"
                value={totalSessions}
                form={form}
            />
            {form
                ? hiddenSelectedIds.map((id) => (
                      <div key={id}>
                          <input
                              type="hidden"
                              name="service_ids[]"
                              value={id}
                              form={form}
                          />
                          <input
                              type="hidden"
                              name={`service_quantities[${id}]`}
                              value={selected[id]}
                              form={form}
                          />
                      </div>
                  ))
                : hiddenSelectedIds.map((id) => (
                      <div key={id}>
                          <input
                              type="hidden"
                              name="service_ids[]"
                              value={id}
                          />
                          <input
                              type="hidden"
                              name={`service_quantities[${id}]`}
                              value={selected[id]}
                          />
                      </div>
                  ))}
            <div className="space-y-2">
                <Input
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Pesquisar serviço"
                    aria-label="Pesquisar serviços"
                    autoComplete="off"
                />
                {remote.loading ? (
                    <p role="status" className="text-xs text-muted-foreground">
                        Carregando serviços…
                    </p>
                ) : null}
                {remote.error ? (
                    <div
                        role="alert"
                        className="flex items-center justify-between gap-3 rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive"
                    >
                        <span>Não foi possível carregar os serviços.</span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={remote.retry}
                        >
                            Tentar novamente
                        </Button>
                    </div>
                ) : null}
            </div>
            {visibleOptions.length === 0 && !remote.loading ? (
                <p className="rounded-lg border border-dashed border-border bg-muted/40 px-3 py-2 text-xs leading-5 text-muted-foreground">
                    {query
                        ? 'Nenhum serviço encontrado.'
                        : 'Nenhum serviço disponível nesta unidade ainda.'}
                </p>
            ) : (
                <div className="grid gap-2 sm:grid-cols-2">
                    {visibleOptions.map((option) => {
                        const quantity = selected[option.id] ?? 0;
                        const inputId = `${option.id}-service-toggle`;

                        return (
                            <div
                                key={option.id}
                                className="flex min-h-11 items-center gap-3 rounded-lg border border-border bg-muted/20 p-3 transition-colors has-checked:border-primary/60 has-checked:bg-secondary/70"
                            >
                                <input
                                    id={inputId}
                                    type="checkbox"
                                    name="service_ids[]"
                                    value={option.id}
                                    form={form}
                                    checked={quantity > 0}
                                    onChange={(event) =>
                                        updateQuantity(
                                            option.id,
                                            event.currentTarget.checked
                                                ? Math.max(1, quantity || 1)
                                                : 0,
                                        )
                                    }
                                    className="size-4 rounded border-input accent-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                />
                                <label
                                    htmlFor={inputId}
                                    className="min-w-0 flex-1 truncate text-sm"
                                >
                                    {option.name}
                                </label>
                                <input
                                    type="number"
                                    name={`service_quantities[${option.id}]`}
                                    min="1"
                                    max="1000"
                                    value={quantity || ''}
                                    disabled={quantity === 0}
                                    form={form}
                                    onChange={(event) =>
                                        updateQuantity(
                                            option.id,
                                            Math.max(
                                                1,
                                                Number(event.target.value) || 1,
                                            ),
                                        )
                                    }
                                    aria-label={`Quantidade de ${option.name}`}
                                    className="h-9 w-20 rounded-md border bg-background px-2 text-center text-sm focus-visible:ring-2 focus-visible:ring-ring"
                                />
                            </div>
                        );
                    })}
                </div>
            )}
            {remote.hasMore ? (
                <Button
                    type="button"
                    variant="outline"
                    className="w-full"
                    disabled={remote.loading}
                    onClick={() => remote.setPage((page) => page + 1)}
                >
                    {remote.loading ? 'Carregando…' : 'Carregar mais serviços'}
                </Button>
            ) : null}
            <div className="rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm">
                <span className="text-muted-foreground">Total do pacote: </span>
                <strong>
                    {totalSessions} {totalSessions === 1 ? 'sessão' : 'sessões'}
                </strong>
                <p className="mt-1 text-xs text-muted-foreground">
                    O total é calculado automaticamente pela soma dos serviços.
                </p>
            </div>
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

type FormControlProps = {
    id?: string;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean | 'true' | 'false';
    children?: ReactNode;
};

type EnhancedControl = {
    node: ReactNode;
    id?: string;
    enhanced: boolean;
};

function isControlWrapper(element: ReactElement): boolean {
    return (
        typeof element.type === 'string' &&
        ['div', 'fieldset', 'section', 'span'].includes(element.type)
    );
}

function enhanceFormControl(
    node: ReactNode,
    fieldId: string,
    describedBy: string,
    hasError: boolean,
): EnhancedControl {
    if (!isValidElement(node)) {
        return { node, enhanced: false };
    }

    const props = node.props as FormControlProps;

    if (isControlWrapper(node)) {
        let controlId: string | undefined;
        let enhanced = false;
        const nestedChildren = Children.map(props.children, (child) => {
            if (enhanced) {
                return child;
            }

            const nestedControl = enhanceFormControl(
                child,
                fieldId,
                describedBy,
                hasError,
            );

            if (nestedControl.enhanced) {
                controlId = nestedControl.id;
                enhanced = true;
            }

            return nestedControl.node;
        });

        return enhanced
            ? {
                  node: cloneElement(node, undefined, nestedChildren),
                  id: controlId,
                  enhanced: true,
              }
            : { node, enhanced: false };
    }

    const controlId = props.id ?? fieldId;
    const existingDescribedBy = props['aria-describedby'];
    const mergedDescribedBy = Array.from(
        new Set(
            [existingDescribedBy, describedBy]
                .filter(Boolean)
                .flatMap((value) => value?.split(/\s+/) ?? []),
        ),
    ).join(' ');

    return {
        node: cloneElement(node as ReactElement<FormControlProps>, {
            id: controlId,
            'aria-describedby': mergedDescribedBy || undefined,
            'aria-invalid': props['aria-invalid'] ?? hasError,
        }),
        id: controlId,
        enhanced: true,
    };
}

export function FormField({
    label,
    name,
    id,
    required = false,
    description,
    error,
    action,
    children,
}: {
    label: string;
    name?: string;
    id?: string;
    required?: boolean;
    description?: ReactNode;
    error?: unknown;
    action?: ReactNode;
    children: ReactNode;
}) {
    const errorMessage = firstError(error);
    const fieldId = id ?? name ?? label.toLowerCase().replace(/\s+/g, '-');
    const errorId = `${fieldId}-error`;
    const descriptionId = description ? `${fieldId}-description` : undefined;
    const describedBy = [descriptionId, errorMessage ? errorId : undefined]
        .filter((value): value is string => Boolean(value))
        .join(' ');

    const childrenArray = Children.toArray(children);
    const firstControlIndex = childrenArray.findIndex(isValidElement);
    const firstControl = childrenArray[firstControlIndex];
    const controlId =
        (firstControl && isValidElement(firstControl)
            ? (firstControl.props as FormControlProps).id
            : undefined) ?? fieldId;

    const enhancedChildren = childrenArray.map((child, index) => {
        if (index === firstControlIndex && isValidElement(child)) {
            return enhanceFormControl(
                child,
                fieldId,
                describedBy,
                Boolean(errorMessage),
            ).node;
        }

        return child;
    });

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between">
                <label
                    htmlFor={controlId}
                    className="block text-sm font-medium text-foreground"
                >
                    {label}
                    {required ? (
                        <span className="ml-1 text-destructive">*</span>
                    ) : null}
                </label>
                {action ?? null}
            </div>
            {description ? (
                <p id={descriptionId} className="text-xs text-muted-foreground">
                    {description}
                </p>
            ) : null}
            {enhancedChildren}
            <InputError id={errorId} message={errorMessage} role="alert" />
        </div>
    );
}

export function FormActions({
    processing,
    isSubmitting,
    submitting,
    onCancel,
    label = 'Salvar cadastro',
    submitLabel,
    submittingLabel,
    submitText,
    cancelLabel = 'Cancelar',
}: {
    processing?: boolean;
    isSubmitting?: boolean;
    submitting?: boolean;
    onCancel?: () => void;
    label?: string;
    submitLabel?: string;
    submittingLabel?: string;
    submitText?: string;
    cancelLabel?: string;
}) {
    const isProcessing = processing ?? isSubmitting ?? submitting ?? false;
    const resolvedLabel = submitLabel ?? submitText ?? label;

    return (
        <div className="flex flex-col-reverse gap-2 border-t border-border pt-4 sm:flex-row sm:justify-end">
            {onCancel ? (
                <Button type="button" variant="ghost" onClick={onCancel}>
                    {cancelLabel}
                </Button>
            ) : null}
            <Button type="submit" disabled={isProcessing}>
                {isProcessing
                    ? (submittingLabel ?? 'Salvando…')
                    : resolvedLabel}
            </Button>
        </div>
    );
}

export function formatMoney(cents: number, prefix?: string): string {
    if (prefix !== undefined) {
        if (!Number.isFinite(cents) || cents < 0) {
            cents = 0;
        }

        const formatted = (cents / 100).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        if (!prefix) {
            return formatted;
        }

        const separator = prefix.endsWith(' ') ? '' : ' ';

        return `${prefix}${separator}${formatted}`;
    }

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
                'rounded-full px-2.5 py-1 text-3xs font-semibold',
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
    subtitle,
    action,
    actions,
    status,
    backHref,
    backLabel = 'Voltar',
}: {
    eyebrow?: string;
    title: string;
    description?: string;
    subtitle?: string;
    action?: ReactNode;
    actions?: ReactNode;
    status?: ReactNode;
    backHref?: string;
    backLabel?: string;
}) {
    const resolvedDescription = description ?? subtitle ?? '';
    const resolvedAction = action ?? actions;

    return (
        <header className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div className="max-w-2xl space-y-2">
                {backHref ? (
                    <Link
                        href={backHref}
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        {backLabel}
                    </Link>
                ) : null}
                {eyebrow ? (
                    <p className="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                        {eyebrow}
                    </p>
                ) : null}
                <div className="space-y-1.5">
                    <h1 className="font-display text-3xl leading-tight font-semibold tracking-[-0.035em] text-foreground sm:text-4xl">
                        {title}
                    </h1>
                    {status}
                    {resolvedDescription ? (
                        <p className="text-sm leading-6 text-muted-foreground sm:text-base">
                            {resolvedDescription}
                        </p>
                    ) : null}
                </div>
            </div>
            {resolvedAction}
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
    searchPlaceholder,
    initialSearch,
    initialStatus,
    onFilterChange,
    children,
}: {
    action?: string;
    defaultValue?: string;
    placeholder?: string;
    resultLabel?: string;
    status?: string;
    onStatusChange?: (status: 'active' | 'inactive' | 'all') => void;
    searchPlaceholder?: string;
    initialSearch?: string;
    initialStatus?: string;
    onFilterChange?: (filters: ResourceFilters) => void;
    children?: ReactNode;
}) {
    const [currentStatus, setCurrentStatus] = useState(
        initialStatus ?? status ?? 'active',
    );
    const resolvedPlaceholder = placeholder ?? searchPlaceholder ?? 'Buscar';

    const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
        if (!onFilterChange) {
            return;
        }

        event.preventDefault();
        const formData = new FormData(event.currentTarget);
        onFilterChange({
            search: String(formData.get('search') ?? ''),
            status: currentStatus,
        });
    };

    const handleStatusChange = (nextStatus: 'active' | 'inactive' | 'all') => {
        setCurrentStatus(nextStatus);
        onStatusChange?.(nextStatus);
        onFilterChange?.({
            search: initialSearch ?? defaultValue,
            status: nextStatus,
        });
    };

    return (
        <div className="surface-panel flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
            <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <form
                    action={action}
                    method="get"
                    onSubmit={handleFilterSubmit}
                    className="flex w-full items-center gap-2 sm:max-w-md"
                >
                    {status || initialStatus || onFilterChange ? (
                        <input
                            type="hidden"
                            name="status"
                            value={currentStatus}
                        />
                    ) : null}
                    <div className="relative min-w-0 flex-1">
                        <Search
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            aria-label="Buscar"
                            name="search"
                            defaultValue={initialSearch ?? defaultValue}
                            placeholder={resolvedPlaceholder}
                            className="h-11 rounded-lg pl-9"
                        />
                    </div>
                    <Button
                        type="submit"
                        variant="secondary"
                        className="shrink-0"
                    >
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
                                onClick={() => handleStatusChange(tab.value)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 transition-colors',
                                    currentStatus === tab.value
                                        ? 'bg-background font-semibold text-foreground shadow-xs'
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
                <p className="shrink-0 text-xs text-muted-foreground sm:text-right">
                    {resultLabel}
                </p>
            ) : null}
        </div>
    );
}

export function EmptyState({
    icon,
    title,
    description,
    action,
}: {
    icon?: LucideIcon;
    title: string;
    description: string;
    action?: ReactNode;
}) {
    const Icon = icon ?? UsersRound;

    return (
        <div className="surface-panel flex min-h-64 flex-col items-center justify-center gap-4 px-6 py-12 text-center">
            <div className="flex size-12 items-center justify-center rounded-2xl bg-secondary text-secondary-foreground">
                <Icon aria-hidden="true" className="size-5" />
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

export function Pagination({
    links,
    paginated,
}: {
    links?: PaginationLink[];
    paginated?: Paginated<unknown>;
}) {
    const resolvedLinks = links ?? paginated?.links ?? [];
    const visibleLinks = resolvedLinks.slice(1, -1);

    if (visibleLinks.length === 0) {
        return null;
    }

    return (
        <nav
            aria-label="Paginação"
            className="flex flex-wrap items-center justify-between gap-3"
        >
            <div className="flex items-center gap-1">
                {resolvedLinks[0]?.url ? (
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        aria-label="Página anterior"
                    >
                        <Link href={resolvedLinks[0].url} preserveScroll>
                            <ChevronLeft aria-hidden="true" />
                            <span className="sr-only">Página anterior</span>
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
                        aria-label={`Página ${link.label}`}
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
                {resolvedLinks.at(-1)?.url ? (
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        aria-label="Próxima página"
                    >
                        <Link
                            href={resolvedLinks.at(-1)?.url as string}
                            preserveScroll
                        >
                            <ChevronRight aria-hidden="true" />
                            <span className="sr-only">Próxima página</span>
                        </Link>
                    </Button>
                ) : null}
            </div>
        </nav>
    );
}

export function PageCanvas({
    children,
    className,
    breadcrumbs,
}: {
    children: ReactNode;
    className?: string;
    breadcrumbs?: Array<{ title: string; href: string }>;
}) {
    return (
        <div
            className={cn(
                'dashboard-canvas flex min-h-full flex-1 flex-col gap-6 px-4 py-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:px-6 lg:px-8 lg:py-8',
                className,
            )}
        >
            {breadcrumbs ? (
                <nav
                    aria-label="Breadcrumb"
                    className="text-sm text-muted-foreground"
                >
                    {breadcrumbs.map((breadcrumb, index) => (
                        <span key={`${breadcrumb.href}-${breadcrumb.title}`}>
                            {index > 0 ? ' / ' : null}
                            <Link
                                href={breadcrumb.href}
                                className="hover:text-foreground"
                            >
                                {breadcrumb.title}
                            </Link>
                        </span>
                    ))}
                </nav>
            ) : null}
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

export * from './masked-inputs';
export * from './denomination-shortcuts';
