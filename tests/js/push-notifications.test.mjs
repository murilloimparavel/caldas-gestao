import assert from 'node:assert/strict';
import { test } from 'node:test';
import { enablePushSubscription } from '../../resources/js/lib/push-notifications.ts';

function setGlobal(t, name, value) {
    const original = Object.getOwnPropertyDescriptor(globalThis, name);
    Object.defineProperty(globalThis, name, { configurable: true, value });
    t.after(() => {
        if (original) {
            Object.defineProperty(globalThis, name, original);
        } else {
            delete globalThis[name];
        }
    });
}

test('explicit reconnection reuses granted permission and an existing subscription without asking again', async (t) => {
    let permissionRequests = 0;
    let subscriptions = 0;
    let posts = 0;
    const subscription = {
        endpoint: 'https://push.example/device',
        toJSON: () => ({ endpoint: 'https://push.example/device', keys: {} }),
    };
    const registration = {
        pushManager: {
            getSubscription: async () => subscription,
            subscribe: async () => {
                subscriptions++;

                return subscription;
            },
        },
    };
    t.mock.method(globalThis, 'fetch', async () => {
        posts++;

        return { ok: true, status: 201, json: async () => ({ id: '1' }) };
    });
    setGlobal(t, 'Notification', {
        permission: 'granted',
        requestPermission: async () => {
            permissionRequests++;

            return 'granted';
        },
    });
    setGlobal(t, 'navigator', {
        serviceWorker: {
            register: async () => registration,
            ready: Promise.resolve(registration),
        },
    });
    setGlobal(t, 'document', { cookie: '' });
    const result = await enablePushSubscription(
        'key',
        '/push-subscriptions',
        'Device',
    );
    assert.equal(result.permission, 'granted');
    assert.ok(result.endpointHash);
    assert.equal(permissionRequests, 0);
    assert.equal(subscriptions, 0);
    assert.equal(posts, 1);
});

test('failed registration after granted permission can retry without requesting permission', async (t) => {
    let permissionRequests = 0;
    let attempts = 0;
    const subscription = {
        endpoint: 'https://push.example/device',
        toJSON: () => ({ endpoint: 'https://push.example/device', keys: {} }),
    };
    const registration = {
        pushManager: { getSubscription: async () => subscription },
    };
    t.mock.method(globalThis, 'fetch', async () => ({
        ok: ++attempts > 1,
        status: attempts > 1 ? 201 : 503,
        json: async () => ({ id: '1' }),
    }));
    setGlobal(t, 'Notification', {
        permission: 'granted',
        requestPermission: async () => {
            permissionRequests++;

            return 'granted';
        },
    });
    setGlobal(t, 'navigator', {
        serviceWorker: {
            register: async () => registration,
            ready: Promise.resolve(registration),
        },
    });
    setGlobal(t, 'document', { cookie: '' });
    await assert.rejects(
        enablePushSubscription('key', '/push-subscriptions', 'Device'),
    );
    const result = await enablePushSubscription(
        'key',
        '/push-subscriptions',
        'Device',
    );
    assert.ok(result.endpointHash);
    assert.equal(attempts, 2);
    assert.equal(permissionRequests, 0);
});
