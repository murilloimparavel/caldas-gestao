type PushInvitationContext = {
    eligible: boolean;
    dismissed: boolean;
    mobile: boolean;
    ios: boolean;
    installed: boolean;
    secure: boolean;
    permission: NotificationPermission | undefined;
    serviceWorker: boolean;
    pushManager: boolean;
};

export function pushInvitationMode({
    eligible,
    dismissed,
    mobile,
    ios,
    installed,
    secure,
    permission,
    serviceWorker,
    pushManager,
}: PushInvitationContext): 'install' | 'activate' | null {
    if (
        !eligible ||
        dismissed ||
        !mobile ||
        !secure ||
        permission === 'granted' ||
        permission === 'denied'
    ) {
        return null;
    }

    if (ios && !installed) {
        return 'install';
    }

    return permission === 'default' && serviceWorker && pushManager
        ? 'activate'
        : null;
}

export function pushDeviceOptOutKey(
    userId: string,
    tenantId: string,
    hash: string,
): string {
    return `caldas:push-opt-out:${userId}:${tenantId}:${hash}`;
}

export function shouldOfferPushRecovery({
    available,
    dismissed,
    permission,
    registeredEndpointHashes,
    currentEndpointHash,
    optedOut,
}: {
    available: boolean;
    dismissed: boolean;
    permission: NotificationPermission | undefined;
    registeredEndpointHashes: string[];
    currentEndpointHash: string | null;
    optedOut: boolean;
}): boolean {
    return (
        available &&
        !dismissed &&
        permission === 'granted' &&
        !optedOut &&
        (currentEndpointHash === null ||
            !registeredEndpointHashes.includes(currentEndpointHash))
    );
}

const deviceOptOuts = new Map<string, boolean>();

export function isPushDeviceOptedOut(key: string): boolean {
    if (deviceOptOuts.has(key)) {
        return deviceOptOuts.get(key) === true;
    }

    try {
        return localStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}

export function setPushDeviceOptedOut(key: string, optedOut: boolean): void {
    deviceOptOuts.set(key, optedOut);

    try {
        if (optedOut) {
            localStorage.setItem(key, '1');
        } else {
            localStorage.removeItem(key);
        }
    } catch {
        // The in-memory choice still applies when browser storage is blocked.
    }
}

const dismissedInvitations = new Set<string>();

export function dismissPushInvitation(userId: string, tenantId: string): void {
    dismissedInvitations.add(JSON.stringify([userId, tenantId]));
}

export function isPushInvitationDismissed(
    userId: string,
    tenantId: string,
): boolean {
    return dismissedInvitations.has(JSON.stringify([userId, tenantId]));
}
