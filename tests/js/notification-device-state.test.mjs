import assert from 'node:assert/strict';
import { test } from 'node:test';
import { notificationDeviceState } from '../../resources/js/lib/notification-device-state.ts';
const device = {
    ready: true,
    installRequired: false,
    permission: 'default',
    available: true,
    registered: false,
};
test('installation is the only next step before activation, even without iOS browser APIs', () => {
    assert.equal(
        notificationDeviceState({
            ...device,
            installRequired: true,
            permission: 'unsupported',
        }),
        'install',
    );
});
test('a server registration cannot claim active when permission is blocked or unavailable', () => {
    for (const registered of [true, false]) {
        assert.equal(
            notificationDeviceState({
                ...device,
                registered,
                permission: 'denied',
            }),
            'blocked',
        );
        assert.equal(
            notificationDeviceState({
                ...device,
                registered,
                permission: 'unsupported',
            }),
            'unsupported',
        );
        assert.equal(
            notificationDeviceState({
                ...device,
                registered,
                available: false,
            }),
            'unavailable',
        );
    }
});
test('active requires both granted permission and a registration for this device', () => {
    assert.equal(
        notificationDeviceState({
            ...device,
            permission: 'granted',
            registered: true,
        }),
        'active',
    );
    assert.equal(
        notificationDeviceState({ ...device, permission: 'granted' }),
        'reconnect',
    );
    assert.equal(notificationDeviceState(device), 'activate');
    assert.equal(
        notificationDeviceState({ ...device, registered: true }),
        'activate',
    );
});
test('the device is checked before showing activation actions', () => {
    assert.equal(
        notificationDeviceState({ ...device, ready: false }),
        'loading',
    );
});
