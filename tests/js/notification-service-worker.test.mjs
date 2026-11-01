import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function worker() {
    const events = {};
    const notices = [];
    const opened = [];
    const context = { URL, self: { location: { origin: 'https://caldas.test' }, addEventListener: (name, callback) => events[name] = callback, registration: { showNotification: async (title, options) => notices.push({ title, ...options }) }, clients: { matchAll: async () => [], openWindow: async (url) => opened.push(url) } } };
    vm.runInNewContext(readFileSync(new URL('../../public/service-worker.js', import.meta.url), 'utf8'), context);

    return { events, notices, opened };
}
async function dispatch(handler, fields) {
    let completed;
    handler({ ...fields, waitUntil: (promise) => completed = promise });
    await completed;
}
test('push remains private, only routes to authorized notifications, and never caches', async () => {
    const { events, notices } = worker();
    assert.equal(events.fetch, undefined);

    for (const url of ['https://evil.test/notifications/1/open', '/admin/users', 'http://[', '/notifications/1/open']) {
        await dispatch(events.push, { data: { json: () => ({ url, title: 'Private client name', body: 'Private service' }) } });
    }

    assert.equal(notices[0].data.url, '/notifications');
    assert.equal(notices[1].data.url, '/notifications');
    assert.equal(notices[2].data.url, '/notifications');
    assert.equal(notices[3].data.url, '/notifications/1/open');
    assert.ok(notices.every((notice) => !JSON.stringify(notice).includes('Private')));
});
test('malformed payload still creates a generic notification', async () => {
    const { events, notices } = worker();
    await dispatch(events.push, { data: { json: () => {
 throw new Error('invalid');
} } });
    await dispatch(events.push, { data: { json: () => null } });
    assert.equal(notices.length, 2);
    assert.equal(notices[0].data.url, '/notifications');
});
test('click falls back for malformed and foreign URLs and opens valid notification URLs', async () => {
    const { events, opened } = worker();

    for (const url of ['http://[', 'https://evil.test/notifications', '/notifications/42/open']) {
        await dispatch(events.notificationclick, { notification: { close() {}, data: { url } } });
    }

    assert.deepEqual(opened, ['https://caldas.test/notifications', 'https://caldas.test/notifications', 'https://caldas.test/notifications/42/open']);
});
