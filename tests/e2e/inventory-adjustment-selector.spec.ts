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

for (const viewport of viewports) {
    test.describe(`Inventory adjustment product selector · ${viewport.name}`, () => {
        test.use({
            viewport: { width: viewport.width, height: viewport.height },
        });

        test('chooses the searched product before opening the adjustment form', async ({
            page,
        }) => {
            await page.route(
                '**/selector-options/inventory-products*',
                async (route) => {
                    const url = new URL(route.request().url());

                    if (url.searchParams.get('search') !== 'shampoo') {
                        await route.continue();

                        return;
                    }

                    expect(url.searchParams.get('per_page')).toBe('25');
                    await route.fulfill({
                        body: JSON.stringify({
                            data: [
                                {
                                    id: 'e2e-inventory-product',
                                    name: 'Shampoo profissional',
                                    current_stock: 8,
                                    min_stock: 2,
                                    unit_of_measure: 'un',
                                    lock_version: 3,
                                    cost_price_cents: 1450,
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

            await signIn(page);
            await page.goto('/inventory', { waitUntil: 'domcontentloaded' });
            await expect(page.locator('#app-loading')).toHaveCount(0, {
                timeout: 10000,
            });
            await page
                .getByRole('button', { name: 'Lançar movimentação' })
                .click();

            const chooseProductDialog = page.getByRole('dialog', {
                name: 'Selecionar produto',
            });
            await expect(chooseProductDialog).toBeVisible();

            const productPicker = chooseProductDialog.getByRole('combobox', {
                name: 'Produto',
            });
            await productPicker.fill('shampoo');
            await chooseProductDialog
                .getByRole('option', { name: 'Shampoo profissional' })
                .click();

            const adjustmentDialog = page.getByRole('dialog', {
                name: 'Ajustar Estoque',
            });
            await expect(adjustmentDialog).toBeVisible();
            await expect(adjustmentDialog).toContainText(
                'Shampoo profissional',
            );
            await expect(adjustmentDialog).toContainText('8 un');
            await expect(
                adjustmentDialog.locator('input[name="product_id"]'),
            ).toHaveValue('e2e-inventory-product');

            const dialogBox = await adjustmentDialog.boundingBox();
            expect(dialogBox).not.toBeNull();

            if (dialogBox === null) {
                throw new Error(
                    'Adjustment dialog has no visible bounding box.',
                );
            }

            expect(dialogBox.x).toBeGreaterThanOrEqual(0);
            expect(dialogBox.x + dialogBox.width).toBeLessThanOrEqual(
                viewport.width,
            );
        });
    });
}
