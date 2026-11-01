import assert from 'node:assert/strict';
import { test } from 'node:test';
import { pushInvitationMode } from '../../resources/js/lib/push-invitation.ts';

const mobile = {
    eligible: true,
    dismissed: false,
    mobile: true,
    ios: false,
    installed: false,
    secure: true,
    permission: 'default',
    serviceWorker: true,
    pushManager: true,
};

test('iOS browser offers installation even when push APIs are absent', () => {
    assert.equal(
        pushInvitationMode({
            ...mobile,
            ios: true,
            permission: undefined,
            serviceWorker: false,
            pushManager: false,
        }),
        'install',
    );
});

test('installed iOS can activate only with default permission and supported APIs', () => {
    assert.equal(
        pushInvitationMode({ ...mobile, ios: true, installed: true }),
        'activate',
    );

    for (const permission of ['granted', 'denied', undefined]) {
        assert.equal(
            pushInvitationMode({
                ...mobile,
                ios: true,
                installed: true,
                permission,
            }),
            null,
        );
    }
});

test('granted and denied permissions suppress installation guidance too', () => {
    for (const permission of ['granted', 'denied']) {
        assert.equal(
            pushInvitationMode({ ...mobile, ios: true, permission }),
            null,
        );
    }
});

test('Android requires supported push APIs while desktop is excluded', () => {
    assert.equal(pushInvitationMode(mobile), 'activate');
    assert.equal(pushInvitationMode({ ...mobile, mobile: false }), null);

    for (const unsupported of [
        { serviceWorker: false },
        { pushManager: false },
        { permission: undefined },
    ]) {
        assert.equal(pushInvitationMode({ ...mobile, ...unsupported }), null);
    }
});

test('eligibility, dismissal and HTTPS guard both installation and activation', () => {
    for (const ios of [false, true]) {
        for (const blocked of [
            { eligible: false },
            { dismissed: true },
            { secure: false },
        ]) {
            assert.equal(
                pushInvitationMode({ ...mobile, ios, ...blocked }),
                null,
            );
        }
    }
});

test('reconnection is offered only for an explicitly approved unlinked device', async () => {
    const { shouldOfferPushRecovery, pushDeviceOptOutKey } =
        await import('../../resources/js/lib/push-invitation.ts');
    const context = {
        available: true,
        dismissed: false,
        permission: 'granted',
        registeredEndpointHashes: ['other-device'],
        currentEndpointHash: 'this-device',
        optedOut: false,
    };
    assert.equal(shouldOfferPushRecovery(context), true);
    assert.equal(
        shouldOfferPushRecovery({ ...context, currentEndpointHash: null }),
        true,
        'failed POST after permission granted remains recoverable',
    );

    for (const blocked of [
        { available: false },
        { dismissed: true },
        { optedOut: true },
        { permission: 'default' },
        { permission: 'denied' },
        { registeredEndpointHashes: ['this-device'] },
    ]) {
        assert.equal(
            shouldOfferPushRecovery({ ...context, ...blocked }),
            false,
        );
    }

    assert.notEqual(
        pushDeviceOptOutKey('user-a', 'tenant-a', 'device'),
        pushDeviceOptOutKey('user-b', 'tenant-a', 'device'),
    );
    assert.notEqual(
        pushDeviceOptOutKey('user-a', 'tenant-a', 'device'),
        pushDeviceOptOutKey('user-a', 'tenant-b', 'device'),
    );
});

test('device choices survive unavailable browser storage without failing activation', async () => {
    const { isPushDeviceOptedOut, setPushDeviceOptedOut, pushDeviceOptOutKey } =
        await import('../../resources/js/lib/push-invitation.ts');
    const descriptor = Object.getOwnPropertyDescriptor(
        globalThis,
        'localStorage',
    );
    Object.defineProperty(globalThis, 'localStorage', {
        configurable: true,
        get() {
            throw new DOMException('Storage blocked', 'SecurityError');
        },
    });

    try {
        const key = pushDeviceOptOutKey('blocked-user', 'tenant', 'endpoint');
        assert.equal(isPushDeviceOptedOut(key), false);
        assert.doesNotThrow(() => setPushDeviceOptedOut(key, true));
        assert.equal(isPushDeviceOptedOut(key), true);
        assert.equal(
            isPushDeviceOptedOut(
                pushDeviceOptOutKey('other', 'tenant', 'endpoint'),
            ),
            false,
        );
        assert.doesNotThrow(() => setPushDeviceOptedOut(key, false));
        assert.equal(isPushDeviceOptedOut(key), false);
    } finally {
        if (descriptor) {
            Object.defineProperty(globalThis, 'localStorage', descriptor);
        } else {
            delete globalThis.localStorage;
        }
    }
});

test('successful dismissal suppresses stale partial-page invitation and recovery props in its user and tenant', async () => {
    const {
        dismissPushInvitation,
        isPushInvitationDismissed,
        shouldOfferPushRecovery,
    } = await import('../../resources/js/lib/push-invitation.ts');
    dismissPushInvitation('dismissed-user', 'tenant-a');
    const dismissed = isPushInvitationDismissed('dismissed-user', 'tenant-a');
    assert.equal(dismissed, true);
    assert.equal(pushInvitationMode({ ...mobile, dismissed }), null);
    assert.equal(
        shouldOfferPushRecovery({
            available: true,
            dismissed,
            permission: 'granted',
            registeredEndpointHashes: [],
            currentEndpointHash: 'endpoint',
            optedOut: false,
        }),
        false,
    );
    assert.equal(
        isPushInvitationDismissed('dismissed-user', 'tenant-b'),
        false,
    );
    assert.equal(isPushInvitationDismissed('other-user', 'tenant-a'), false);
});
