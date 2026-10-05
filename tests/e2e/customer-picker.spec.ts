import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const fixtureEmail = 'e2e-booking@caldas.local';
const fixturePassword = 'CaldasE2E!2026';

const viewports = [
    { height: 844, name: 'mobile', width: 390 },
    { height: 900, name: 'tablet', width: 768 },
    { height: 900, name: 'desktop', width: 1280 },
] as const;

async function signIn(page: Page): Promise<void> {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#app-loading')).toHaveCount(0, {
        timeout: 10000,
    });
    await page.getByLabel('Email address').fill(fixtureEmail);
    await page.locator('input[name="password"]').fill(fixturePassword);
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), {
        waitUntil: 'domcontentloaded',
    });
    await expect(page.locator('#app-loading')).toHaveCount(0, {
        timeout: 10000,
    });
}

async function openAppointmentDialog(page: Page) {
    await signIn(page);
    await page.goto('/calendar', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#app-loading')).toHaveCount(0, {
        timeout: 10000,
    });

    const createButton = page
        .locator('button:visible')
        .filter({ hasText: /^(Novo agendamento|Agendar)$/ })
        .first();
    await expect(createButton).toBeVisible({ timeout: 10000 });
    await createButton.click();

    const dialog = page.getByRole('dialog', { name: 'Novo agendamento' });
    await expect(dialog).toBeVisible({ timeout: 10000 });

    return dialog;
}

async function expectWithinViewport(
    page: Page,
    selector: string,
): Promise<void> {
    const box = await page.locator(selector).boundingBox();
    const viewport = page.viewportSize();

    expect(box).not.toBeNull();
    expect(viewport).not.toBeNull();

    if (!box || !viewport) {
        return;
    }

    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.y).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(viewport.width);
    expect(box.y + box.height).toBeLessThanOrEqual(viewport.height);
}

for (const viewport of viewports) {
    test.describe(`CustomerPicker · ${viewport.name}`, () => {
        test.use({
            viewport: { width: viewport.width, height: viewport.height },
        });

        test('searches remotely, selects and clears with keyboard, and stays in viewport', async ({
            page,
        }) => {
            await page.route(
                '**/selector-options/customers*',
                async (route) => {
                    const url = new URL(route.request().url());

                    if (url.searchParams.get('search') !== 'maria') {
                        await route.continue();

                        return;
                    }

                    await route.fulfill({
                        body: JSON.stringify({
                            data: [
                                {
                                    id: 'customer-maria',
                                    name: 'Maria da Silva',
                                    phone: '(48) 99999-0000',
                                },
                            ],
                            meta: {
                                current_page: 1,
                                has_more: false,
                                last_page: 1,
                                per_page: 25,
                                total: 1,
                            },
                        }),
                        contentType: 'application/json',
                        status: 200,
                    });
                },
            );

            const dialog = await openAppointmentDialog(page);
            const customer = dialog.getByRole('combobox', {
                name: 'Cliente',
            });

            await customer.fill('maria');
            await expect(
                page.getByRole('option', { name: /Maria da Silva/ }),
            ).toBeVisible();
            await expectWithinViewport(page, '[role="listbox"]');

            await customer.press('ArrowDown');
            await customer.press('Enter');
            await expect(
                page.getByRole('button', {
                    name: 'Limpar cliente selecionado',
                }),
            ).toBeVisible();
            await expect(dialog.getByText('Maria da Silva')).toBeVisible();

            await page
                .getByRole('button', { name: 'Limpar cliente selecionado' })
                .click();
            await expect(
                page.getByRole('button', {
                    name: 'Limpar cliente selecionado',
                }),
            ).toHaveCount(0);
            await expectWithinViewport(page, '[role="listbox"]');
            await expect(
                page
                    .locator('html')
                    .evaluate(
                        () =>
                            document.documentElement.scrollWidth <=
                            window.innerWidth,
                    ),
            ).resolves.toBe(true);
        });
    });
}
