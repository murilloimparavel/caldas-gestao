import * as React from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * Remove any non-numeric characters from a string or number.
 */
export function sanitizeDigits(value: string | number | null | undefined): string {
    if (value === null || value === undefined) return '';
    return String(value).replace(/\D/g, '');
}

export const sanitizePhone = sanitizeDigits;
export const sanitizeDocument = sanitizeDigits;

/**
 * Format an amount in cents to BRL currency string.
 * Example: 13000 -> "130,00" (or "R$ 130,00" if prefix is passed).
 */
export function formatMoney(cents: number, prefix: string = ''): string {
    if (!Number.isFinite(cents) || cents < 0) {
        cents = 0;
    }
    const formatted = (cents / 100).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    return prefix ? `${prefix}${formatted}` : formatted;
}

/**
 * Format raw digits into Brazilian Phone mask:
 * - 10 digits: (99) 9999-9999 (landline)
 * - 11 digits: (99) 99999-9999 (mobile)
 */
export function formatPhone(value: string | number | null | undefined): string {
    const digits = sanitizeDigits(value).slice(0, 11);
    if (!digits) return '';

    if (digits.length <= 2) {
        return `(${digits}`;
    }
    if (digits.length <= 6) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    }
    if (digits.length <= 10) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    }
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

/**
 * Format raw digits into Brazilian Document mask:
 * - <= 11 digits: 999.999.999-99 (CPF)
 * - > 11 digits: 99.999.999/9999-99 (CNPJ)
 */
export function formatDocument(
    value: string | number | null | undefined,
    mode: 'auto' | 'cpf' | 'cnpj' = 'auto',
): string {
    const maxDigits = mode === 'cpf' ? 11 : 14;
    const digits = sanitizeDigits(value).slice(0, maxDigits);
    if (!digits) return '';

    if (mode === 'cnpj' || (mode === 'auto' && digits.length > 11)) {
        if (digits.length <= 2) return digits;
        if (digits.length <= 5) return `${digits.slice(0, 2)}.${digits.slice(2)}`;
        if (digits.length <= 8) {
            return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5)}`;
        }
        if (digits.length <= 12) {
            return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8)}`;
        }
        return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
    }

    // CPF
    if (digits.length <= 3) return digits;
    if (digits.length <= 6) return `${digits.slice(0, 3)}.${digits.slice(3)}`;
    if (digits.length <= 9) {
        return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
    }
    return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
}

export interface MoneyInputProps
    extends Omit<React.ComponentProps<typeof Input>, 'value' | 'defaultValue' | 'onChange'> {
    value?: string | number;
    defaultValue?: string | number;
    cents?: number;
    prefix?: string;
    onValueChange?: (cents: number, formatted: string) => void;
    onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
}

export const MoneyInput = React.forwardRef<HTMLInputElement, MoneyInputProps>(
    function MoneyInput(
        {
            className,
            cents,
            value,
            defaultValue,
            prefix = '',
            onValueChange,
            onChange,
            inputMode = 'numeric',
            placeholder = '0,00',
            ...props
        },
        ref,
    ) {
        const localRef = React.useRef<HTMLInputElement | null>(null);

        // Helper to get formatted string from incoming props
        const resolveFormatted = React.useCallback(
            (c?: number, v?: string | number, d?: string | number): string => {
                if (c !== undefined && Number.isFinite(c)) {
                    return c > 0 || c === 0 ? formatMoney(c, prefix) : '';
                }
                const target = v !== undefined ? v : d;
                if (target !== undefined && target !== '') {
                    const digits = sanitizeDigits(target);
                    if (digits) {
                        return formatMoney(Number.parseInt(digits, 10), prefix);
                    }
                }
                return '';
            },
            [prefix],
        );

        const [displayValue, setDisplayValue] = React.useState<string>(() =>
            resolveFormatted(cents, value, defaultValue),
        );

        // Synchronize when controlled props change externally
        React.useEffect(() => {
            if (cents !== undefined || value !== undefined) {
                setDisplayValue(resolveFormatted(cents, value, undefined));
            }
        }, [cents, value, resolveFormatted]);

        const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
            const rawDigits = e.target.value.replace(/\D/g, '').slice(0, 11);

            let newCents = 0;
            let formatted = '';

            if (rawDigits !== '') {
                newCents = Number.parseInt(rawDigits, 10);
                formatted = formatMoney(newCents, prefix);
            }

            setDisplayValue(formatted);
            onValueChange?.(newCents, formatted);

            e.target.value = formatted;
            onChange?.(e);

            // Keep cursor at the end for continuous right-to-left typing
            requestAnimationFrame(() => {
                const element = localRef.current;
                if (element) {
                    const len = element.value.length;
                    element.setSelectionRange(len, len);
                }
            });
        };

        const setMergedRef = React.useCallback(
            (node: HTMLInputElement | null) => {
                localRef.current = node;
                if (typeof ref === 'function') {
                    ref(node);
                } else if (ref && 'current' in ref) {
                    (ref as React.MutableRefObject<HTMLInputElement | null>).current = node;
                }
            },
            [ref],
        );

        return (
            <Input
                ref={setMergedRef}
                type="text"
                inputMode={inputMode}
                className={cn('tabular-nums', className)}
                value={displayValue}
                placeholder={placeholder}
                onChange={handleChange}
                {...props}
            />
        );
    },
);

export interface PhoneInputProps
    extends Omit<React.ComponentProps<typeof Input>, 'value' | 'defaultValue' | 'onChange'> {
    value?: string;
    defaultValue?: string;
    onValueChange?: (raw: string, formatted: string) => void;
    onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
}

export const PhoneInput = React.forwardRef<HTMLInputElement, PhoneInputProps>(
    function PhoneInput(
        {
            className,
            value,
            defaultValue,
            onValueChange,
            onChange,
            inputMode = 'tel',
            placeholder = '(11) 99999-9999',
            ...props
        },
        ref,
    ) {
        const [displayValue, setDisplayValue] = React.useState<string>(() =>
            formatPhone(value !== undefined ? value : defaultValue),
        );

        React.useEffect(() => {
            if (value !== undefined) {
                setDisplayValue(formatPhone(value));
            }
        }, [value]);

        const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
            const raw = sanitizeDigits(e.target.value).slice(0, 11);
            const formatted = formatPhone(raw);

            setDisplayValue(formatted);
            onValueChange?.(raw, formatted);

            e.target.value = formatted;
            onChange?.(e);
        };

        return (
            <Input
                ref={ref}
                type="tel"
                inputMode={inputMode}
                className={cn('tabular-nums', className)}
                value={displayValue}
                placeholder={placeholder}
                onChange={handleChange}
                {...props}
            />
        );
    },
);

export interface DocumentInputProps
    extends Omit<React.ComponentProps<typeof Input>, 'value' | 'defaultValue' | 'onChange'> {
    value?: string;
    defaultValue?: string;
    mode?: 'auto' | 'cpf' | 'cnpj';
    onValueChange?: (raw: string, formatted: string) => void;
    onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
}

export const DocumentInput = React.forwardRef<HTMLInputElement, DocumentInputProps>(
    function DocumentInput(
        {
            className,
            value,
            defaultValue,
            mode = 'auto',
            onValueChange,
            onChange,
            inputMode = 'numeric',
            placeholder = mode === 'cnpj'
                ? '00.000.000/0000-00'
                : mode === 'cpf'
                  ? '000.000.000-00'
                  : '000.000.000-00 ou 00.000.000/0000-00',
            ...props
        },
        ref,
    ) {
        const [displayValue, setDisplayValue] = React.useState<string>(() =>
            formatDocument(value !== undefined ? value : defaultValue, mode),
        );

        React.useEffect(() => {
            if (value !== undefined) {
                setDisplayValue(formatDocument(value, mode));
            }
        }, [value, mode]);

        const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
            const maxDigits = mode === 'cpf' ? 11 : 14;
            const raw = sanitizeDigits(e.target.value).slice(0, maxDigits);
            const formatted = formatDocument(raw, mode);

            setDisplayValue(formatted);
            onValueChange?.(raw, formatted);

            e.target.value = formatted;
            onChange?.(e);
        };

        return (
            <Input
                ref={ref}
                type="text"
                inputMode={inputMode}
                className={cn('tabular-nums', className)}
                value={displayValue}
                placeholder={placeholder}
                onChange={handleChange}
                {...props}
            />
        );
    },
);
