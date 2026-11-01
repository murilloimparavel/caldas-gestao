/* No fetch handler: authenticated pages and API responses are never cached. */
function notificationPath(value) {
    try {
        const destination = new URL(typeof value === 'string' ? value : '/notifications', self.location.origin);
        if (destination.origin === self.location.origin && /^\/notifications(?:\/|$)/.test(destination.pathname)) {
            return destination.pathname + destination.search;
        }
    } catch {
        // Invalid or foreign destinations fall back to the authorized inbox.
    }
    return '/notifications';
}
self.addEventListener('push', (event) => {
    let payload = {};
    try {
        const parsed = event.data?.json();
        payload = parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
        payload = {};
    }
    event.waitUntil(self.registration.showNotification('Caldas Gestão', {
        body: 'Você tem uma atualização na sua agenda.',
        icon: '/apple-touch-icon.png',
        badge: '/apple-touch-icon.png',
        tag: typeof payload.tag === 'string' ? payload.tag : undefined,
        data: { url: notificationPath(payload.url) },
    }));
});
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const destination = new URL(notificationPath(event.notification.data?.url), self.location.origin);
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async (windows) => {
        for (const window of windows) {
            if (new URL(window.url).origin === self.location.origin) {
                await window.navigate(destination.href);
                return window.focus();
            }
        }
        return self.clients.openWindow(destination.href);
    }));
});
