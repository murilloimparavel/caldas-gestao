import { expect, test } from '@playwright/test';

test('mounts login and the authenticated app layout within the Inertia page context', async ({
    page,
}) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await expect(page.getByRole('textbox', { name: 'E-mail', exact: true })).toBeVisible();
    await page.getByRole('textbox', { name: 'E-mail', exact: true }).fill('e2e-booking@caldas.local');
    await page.locator('input[name="password"]').fill('CaldasE2E!2026');
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    await page.goto('/settings/notifications');
    await expect(
        page.getByRole('heading', { name: 'Neste aparelho' }),
    ).toBeVisible();
    expect(
        errors.filter((message) =>
            /usePage|Inertia component|React error/i.test(message),
        ),
    ).toEqual([]);
});
