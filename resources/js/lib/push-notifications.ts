export async function pushRequest(url: string, method: string, data?: unknown) {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice(11);
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(token ?? ''),
        },
        body: data === undefined ? undefined : JSON.stringify(data),
    });

    if (!response.ok) {
        throw new Error(
            response.status === 419
                ? 'Sua sessão expirou. Atualize a página.'
                : 'Não foi possível concluir. Tente novamente.',
        );
    }

    return response.status === 204 ? null : response.json();
}

export async function enablePushSubscription(
    publicKey: string,
    storeUrl: string,
    deviceName: string,
): Promise<{
    permission: NotificationPermission;
    endpointHash: string | null;
}> {
    const permission =
        Notification.permission === 'default'
            ? await Notification.requestPermission()
            : Notification.permission;

    if (permission !== 'granted') {
        return { permission, endpointHash: null };
    }

    let subscription: PushSubscription | null = null;
    let createdSubscription = false;

    try {
        await navigator.serviceWorker.register('/service-worker.js', {
            scope: '/',
        });
        const registration = await navigator.serviceWorker.ready;
        subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey(publicKey),
            });
            createdSubscription = true;
        }

        await pushRequest(storeUrl, 'POST', {
            ...subscription.toJSON(),
            device_name: deviceName,
        });

        return {
            permission,
            endpointHash: await endpointHash(subscription.endpoint),
        };
    } catch (error) {
        if (createdSubscription) {
            await subscription?.unsubscribe().catch(() => false);
        }

        throw error;
    }
}

export async function endpointHash(endpoint: string): Promise<string> {
    const hash = await crypto.subtle.digest(
        'SHA-256',
        new TextEncoder().encode(endpoint),
    );

    return Array.from(new Uint8Array(hash), (value) =>
        value.toString(16).padStart(2, '0'),
    ).join('');
}

export function applicationServerKey(value: string): Uint8Array<ArrayBuffer> {
    const raw = atob(
        value.replace(/-/g, '+').replace(/_/g, '/') +
            '='.repeat((4 - (value.length % 4)) % 4),
    );

    return Uint8Array.from(raw, (character) => character.charCodeAt(0));
}
