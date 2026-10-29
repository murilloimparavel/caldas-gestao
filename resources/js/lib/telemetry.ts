import { router } from '@inertiajs/react';
import { browser } from '@/routes/telemetry';

type Category =
    | 'javascript_error'
    | 'unhandled_rejection'
    | 'inertia_http_exception'
    | 'network_error';
type Frame = { file: string; line: number; column?: number };
let initialized = false;
const errorNames = new Set([
    'Error',
    'TypeError',
    'ReferenceError',
    'RangeError',
    'SyntaxError',
    'URIError',
    'EvalError',
    'AggregateError',
    'NetworkError',
    'AbortError',
]);
const staticSegments = new Set([
    'online-booking',
    'editor',
    'publications',
    'preview',
    'campaign-links',
    'links',
    'cover',
    'logo',
    'gallery',
    'book',
    'login',
    'register',
    'settings',
    'profile',
    'password',
    'appearance',
    'calendar',
    'dashboard',
    'services',
    'professionals',
    'customers',
    'sales',
    'billing',
    'inventory',
    'packages',
]);

function frame(file: string, line: number, column?: number): Frame | null {
    try {
        const url = new URL(file, window.location.origin);

        if (
            url.origin !== window.location.origin ||
            !/^\/build\/assets\/[A-Za-z0-9_-]+-[A-Za-z0-9_-]{6,}\.js$/.test(
                url.pathname,
            ) ||
            !Number.isInteger(line) ||
            line < 1
        ) {
            return null;
        }

        return {
            file: url.pathname,
            line: Math.min(line, 1_000_000),
            ...(Number.isInteger(column) && column! > 0
                ? { column: Math.min(column!, 1_000_000) }
                : {}),
        };
    } catch {
        return null;
    }
}

function readErrorFrames(value: unknown): Frame[] {
    if (!(value instanceof Error) || typeof value.stack !== 'string') {
        return [];
    }

    const frames: Frame[] = [];

    for (const line of value.stack.slice(0, 8192).split('\n').slice(1, 25)) {
        const match = line.match(
            /(https?:\/\/[^\s()]+|\/build\/assets\/[^\s()]+):(\d+):(\d+)\)?$/,
        );

        if (match) {
            const safe = frame(match[1], Number(match[2]), Number(match[3]));

            if (safe) {
                frames.push(safe);
            }
        }

        if (frames.length === 8) {
            break;
        }
    }

    return frames;
}

function errorFrames(value: unknown): Frame[] {
    try {
        return readErrorFrames(value);
    } catch {
        return [];
    }
}

function code(value: unknown, fallback: string): string {
    try {
        return value instanceof Error && errorNames.has(value.name)
            ? value.name
            : fallback;
    } catch {
        return fallback;
    }
}

export function initializeTelemetry(): void {
    if (
        initialized ||
        typeof document === 'undefined' ||
        document
            .querySelector('meta[name="telemetry-enabled"]')
            ?.getAttribute('content') !== '1' ||
        document
            .querySelector('meta[name="telemetry-probe"]')
            ?.getAttribute('content') === '1'
    ) {
        return;
    }

    initialized = true;
    let started = Date.now();
    let sent = 0;
    const fingerprints = new Set<string>();
    const report = (
        category: Category,
        code: string,
        frames: Frame[] = [],
    ): void => {
        try {
            if (Date.now() - started >= 60_000) {
                started = Date.now();
                sent = 0;
                fingerprints.clear();
            }

            const fingerprint = JSON.stringify([category, code, frames]);

            if (sent >= 3 || fingerprints.has(fingerprint)) {
                return;
            }

            const csrf = document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='));

            if (!csrf) {
                return;
            }

            const token = decodeURIComponent(csrf.slice('XSRF-TOKEN='.length));
            const path =
                '/' +
                window.location.pathname
                    .split('/')
                    .filter(Boolean)
                    .map((segment) =>
                        staticSegments.has(segment) ? segment : 'redacted',
                    )
                    .join('/');
            fingerprints.add(fingerprint);
            sent++;
            void fetch(browser.url(), {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': token,
                },
                body: JSON.stringify({ category, code, frames, path }),
            }).catch(() => undefined);
        } catch {
            // Reporting must never interrupt the application or report its own failures.
        }
    };
    window.addEventListener('error', (event) => {
        const location = frame(event.filename, event.lineno, event.colno);
        report(
            'javascript_error',
            code(event.error, 'Error'),
            location ? [location] : errorFrames(event.error),
        );
    });
    window.addEventListener('unhandledrejection', (event) => {
        report(
            'unhandled_rejection',
            code(event.reason, 'UnhandledRejection'),
            errorFrames(event.reason),
        );
    });
    router.on('httpException', (event) => {
        const status = event.detail?.response?.status;
        report(
            'inertia_http_exception',
            Number.isInteger(status) && status >= 100 && status <= 599
                ? String(status)
                : 'invalid_response',
        );
    });
    router.on('networkError', () => report('network_error', 'network_error'));
}
