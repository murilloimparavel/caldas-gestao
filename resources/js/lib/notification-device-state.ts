export function notificationDeviceState({
    ready,
    installRequired,
    permission,
    available,
    registered,
}: {
    ready: boolean;
    installRequired: boolean;
    permission: NotificationPermission | 'unsupported';
    available: boolean;
    registered: boolean;
}):
    | 'loading'
    | 'install'
    | 'blocked'
    | 'unsupported'
    | 'unavailable'
    | 'active'
    | 'reconnect'
    | 'activate' {
    if (!ready) {
        return 'loading';
    }

    if (installRequired) {
        return 'install';
    }

    if (permission === 'denied') {
        return 'blocked';
    }

    if (permission === 'unsupported') {
        return 'unsupported';
    }

    if (!available) {
        return 'unavailable';
    }

    if (permission === 'granted') {
        return registered ? 'active' : 'reconnect';
    }

    return 'activate';
}
