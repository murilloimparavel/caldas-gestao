import { useEffect } from 'react';

type MetaPixelFunction = {
    (...args: unknown[]): void;
    callMethod?: (...args: unknown[]) => void;
    queue?: unknown[][];
    loaded?: boolean;
    version?: string;
};

declare global {
    interface Window {
        fbq?: MetaPixelFunction;
        _fbq?: MetaPixelFunction;
    }
}

function createEventId(eventName = 'PageView'): string {
    const randomId = globalThis.crypto?.randomUUID?.();

    return `${eventName.toLowerCase()}_${randomId || `${Date.now()}_${Math.random().toString(36).slice(2)}`}`;
}

function readCookie(name: string): string | undefined {
    return document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith(`${name}=`))
        ?.split('=')
        .slice(1)
        .join('=');
}

function sendMetaEvent(
    eventName: 'ViewContent' | 'Lead',
    extraData: Record<string, unknown> = {},
): void {
    const params = new URLSearchParams(window.location.search);
    const customData = Object.fromEntries(
        [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_content',
            'utm_term',
            'fbclid',
            'gclid',
        ]
            .map((key) => [key, params.get(key)])
            .filter(([, value]) => value),
    );
    const contentData = {
        content_name: 'Caldas Gestão',
        content_category: 'Sistema de gestão para negócios de beleza',
        content_type: 'product',
        content_ids: ['caldas-gestao'],
        num_items: 1,
        ...customData,
        ...extraData,
    };
    const eventId = createEventId(eventName);

    window.fbq?.('track', eventName, contentData, { eventID: eventId });
    void fetch('/marketing/barber/meta-events', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        keepalive: true,
        body: JSON.stringify({
            event_name: eventName,
            event_id: eventId,
            event_source_url: window.location.href,
            fbp: readCookie('_fbp'),
            fbc: readCookie('_fbc'),
            custom_data: contentData,
        }),
    }).catch(() => undefined);
}

export function trackMetaLead(): void {
    sendMetaEvent('Lead');
}

function loadMetaPixel(pixelId: string): void {
    if (window.fbq) {
        window.fbq('init', pixelId);

        return;
    }

    const fbq: MetaPixelFunction = (...args: unknown[]): void => {
        if (fbq.callMethod) {
            fbq.callMethod(...args);
        } else {
            fbq.queue?.push(args);
        }
    };
    fbq.queue = [];
    fbq.loaded = true;
    fbq.version = '2.0';
    window.fbq = fbq;
    window._fbq = fbq;
    fbq('init', pixelId);

    if (!document.querySelector('script[data-meta-pixel]')) {
        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://connect.facebook.net/en_US/fbevents.js';
        script.dataset.metaPixel = pixelId;
        document.head.appendChild(script);
    }
}

export function MetaPixel({
    pixelId,
    viewContentDelayMs = 60_000,
}: {
    pixelId?: string | null;
    viewContentDelayMs?: number;
}) {
    useEffect(() => {
        if (!pixelId) {
            return;
        }

        loadMetaPixel(pixelId);
        window.fbq?.('track', 'PageView', {}, { eventID: createEventId() });
        const viewContentTimer = window.setTimeout(
            () => sendMetaEvent('ViewContent'),
            viewContentDelayMs,
        );

        return () => window.clearTimeout(viewContentTimer);
    }, [pixelId, viewContentDelayMs]);

    return null;
}
