import { expect, test } from '@playwright/test';

const required = (
    name: 'PLAYWRIGHT_TEST_BASE_URL' | 'E2E_OWNER_EMAIL' | 'E2E_OWNER_PASSWORD',
): string => {
    const value = process.env[name]?.trim();

    if (!value) {
        throw new Error(
            `Missing required E2E environment variable ${name}; the SSR preference suite requires the authenticated E2E fixture contract.`,
        );
    }

    return value;
};

const baseUrl = new URL(required('PLAYWRIGHT_TEST_BASE_URL'));
const fixtureEmail = required('E2E_OWNER_EMAIL');
const fixturePassword = required('E2E_OWNER_PASSWORD');
const resourceViewStorageKey = 'caldas-gestao:customers-view';

test('restores a persisted resource view after SSR hydration', async ({
    page,
}) => {
    const hydrationErrors: string[] = [];

    page.on('console', (message) => {
        if (
            message.type() === 'error' &&
            /hydration|server HTML|client HTML/i.test(message.text())
        ) {
            hydrationErrors.push(message.text());
        }
    });

    page.on('pageerror', (error) => {
        if (/hydration|server HTML|client HTML/i.test(error.message)) {
            hydrationErrors.push(error.message);
        }
    });

    expect(['127.0.0.1', 'localhost', '[::1]']).toContain(baseUrl.hostname);

    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="email"]').fill(fixtureEmail);
    await page.locator('input[name="password"]').fill(fixturePassword);
    await page.locator('[data-test="login-button"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), {
        waitUntil: 'domcontentloaded',
    });

    await page.goto('/customers', { waitUntil: 'domcontentloaded' });
    await page.evaluate((storageKey) => {
        window.localStorage.setItem(storageKey, 'list');
    }, resourceViewStorageKey);
    await page.reload({ waitUntil: 'domcontentloaded' });

    await expect(
        page.getByRole('button', { name: 'Exibir em lista' }),
    ).toHaveAttribute('aria-pressed', 'true');
    expect(hydrationErrors).toEqual([]);
});
