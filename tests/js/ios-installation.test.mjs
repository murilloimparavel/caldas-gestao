import assert from 'node:assert/strict';
import { test } from 'node:test';
import { needsIosInstallation } from '../../resources/js/lib/ios-installation.ts';
const iphone = {
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_7 like Mac OS X)',
    platform: 'iPhone',
    maxTouchPoints: 5,
    displayModeStandalone: false,
};
test('iPhone browser offers installation', () =>
    assert.equal(needsIosInstallation(iphone), true));
test('both installed detection methods hide installation', () => {
    assert.equal(needsIosInstallation({ ...iphone, standalone: true }), false);
    assert.equal(
        needsIosInstallation({ ...iphone, displayModeStandalone: true }),
        false,
    );
});
test('iPad desktop user agent offers installation only outside standalone', () => {
    const ipad = { ...iphone, userAgent: 'Macintosh', platform: 'MacIntel' };
    assert.equal(needsIosInstallation(ipad), true);
    assert.equal(needsIosInstallation({ ...ipad, standalone: true }), false);
});
test('Android and desktop do not show iPhone installation', () => {
    assert.equal(
        needsIosInstallation({
            ...iphone,
            userAgent: 'Android',
            platform: 'Linux',
        }),
        false,
    );
    assert.equal(
        needsIosInstallation({
            ...iphone,
            userAgent: 'Macintosh',
            platform: 'MacIntel',
            maxTouchPoints: 0,
        }),
        false,
    );
});
