import type { CalendarView } from '@/types/calendar';

export type ZonedParts = {
    day: number;
    hour: number;
    minute: number;
    month: number;
    year: number;
};

export function asInstant(value: string): Date {
    if (!value || typeof value !== 'string') {
        return new Date(0);
    }

    const trimmed = value.trim();

    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
        return new Date(`${trimmed}T12:00:00Z`);
    }

    const normalized = trimmed.replace(
        /^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?)/,
        '$1T$2',
    );

    const hasTimezone = /(?:Z|[+-]\d{2}(?::?\d{2})?)$/i.test(normalized);

    let parsed = new Date(normalized);

    if (
        Number.isNaN(parsed.getTime()) ||
        (!hasTimezone && !normalized.includes('Z'))
    ) {
        const utcParsed = new Date(`${normalized}Z`);

        if (!Number.isNaN(utcParsed.getTime())) {
            parsed = utcParsed;
        }
    }

    return Number.isNaN(parsed.getTime()) ? new Date(0) : parsed;
}

export function partsFor(value: string, timeZone = 'UTC'): ZonedParts {
    const parts = new Intl.DateTimeFormat('en-US', {
        day: '2-digit',
        hour: '2-digit',
        hour12: false,
        minute: '2-digit',
        month: '2-digit',
        timeZone,
        year: 'numeric',
    }).formatToParts(asInstant(value));
    const values = Object.fromEntries(
        parts.map(({ type, value: partValue }) => [type, partValue]),
    );

    return {
        day: Number(values.day),
        hour: Number(values.hour) % 24,
        minute: Number(values.minute),
        month: Number(values.month),
        year: Number(values.year),
    };
}

export function dateOnlyParts(
    value: string,
): Pick<ZonedParts, 'day' | 'month' | 'year'> {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        const parts = partsFor(value);

        return { day: parts.day, month: parts.month, year: parts.year };
    }

    return {
        day: Number(match[3]),
        month: Number(match[2]),
        year: Number(match[1]),
    };
}

export function dateKey(value: string, timeZone = 'UTC'): string {
    const hasTime = value.includes('T') || /\s\d{1,2}:\d{2}/.test(value);
    const parts = hasTime ? partsFor(value, timeZone) : dateOnlyParts(value);

    return `${parts.year}-${String(parts.month).padStart(2, '0')}-${String(parts.day).padStart(2, '0')}`;
}

export function dateTimeValue(value: string, timeZone = 'UTC'): string {
    const parts = partsFor(value, timeZone);

    return `${parts.year}-${String(parts.month).padStart(2, '0')}-${String(parts.day).padStart(2, '0')}T${String(parts.hour).padStart(2, '0')}:${String(parts.minute).padStart(2, '0')}`;
}

export function zonedTimeParts(
    value: string,
    timeZone = 'UTC',
): Pick<ZonedParts, 'hour' | 'minute'> {
    const { hour, minute } = partsFor(value, timeZone);

    return { hour, minute };
}

export function formatTime(value: string, timeZone = 'UTC'): string {
    return new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone,
    }).format(asInstant(value));
}

export function formatDay(value: string, timeZone = 'UTC'): string {
    const hasTime = value.includes('T') || /\s\d{1,2}:\d{2}/.test(value);
    const displayTimeZone = hasTime ? timeZone : 'UTC';

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: 'short',
        timeZone: displayTimeZone,
        weekday: 'short',
    })
        .format(asInstant(value))
        .replace('.', '');
}

export function addDays(value: string, amount: number): string {
    const { day, month, year } = dateOnlyParts(value);
    const date = new Date(Date.UTC(year, month - 1, day + amount));

    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(date.getUTCDate()).padStart(2, '0')}`;
}

export function addMonths(value: string, amount: number): string {
    const { day, month, year } = dateOnlyParts(value);
    const date = new Date(Date.UTC(year, month - 1 + amount, 1));
    const lastDay = new Date(
        Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0),
    ).getUTCDate();

    date.setUTCDate(Math.min(day, lastDay));

    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(date.getUTCDate()).padStart(2, '0')}`;
}

export function dayDates(start: string, view: CalendarView): string[] {
    const count = view === 'week' ? 7 : 1;

    return Array.from({ length: count }, (_, index) => addDays(start, index));
}

export function formatMinutes(totalMinutes: number): string {
    const hours = Math.floor(totalMinutes / 60);
    const mins = totalMinutes % 60;

    return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
}

export function formatDuration(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;

    if (h > 0 && m > 0) {
        return `${h}h ${m}m`;
    }

    if (h > 0) {
        return `${h}h`;
    }

    return `${m}m`;
}
