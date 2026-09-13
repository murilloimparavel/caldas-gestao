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

function createEventId(): string {
    const randomId = globalThis.crypto?.randomUUID?.();

    return `pageview_${randomId || `${Date.now()}_${Math.random().toString(36).slice(2)}`}`;
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

export function MetaPixel({ pixelId }: { pixelId?: string | null }) {
    useEffect(() => {
        if (!pixelId) {
            return;
        }

        loadMetaPixel(pixelId);
        window.fbq?.('track', 'PageView', {}, { eventID: createEventId() });
    }, [pixelId]);

    return null;
}
