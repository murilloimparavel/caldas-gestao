import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const fixtureEmail = 'e2e-booking@caldas.local';
const fixturePassword = 'CaldasE2E!2026';

async function login(page: Page) {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#app-loading')).toHaveCount(0, {
        timeout: 10000,
    });
    await page.locator('input[name="email"]').fill(fixtureEmail);
    await page.locator('input[name="password"]').fill(fixturePassword);
    await page.locator('[data-test="login-button"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), {
        waitUntil: 'domcontentloaded',
    });
}

test('keeps the selected payment method in the consolidated closing dialog', async ({
    page,
}) => {
    await login(page);

    await page.goto('/sales', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#app-loading')).toHaveCount(0, {
        timeout: 10000,
    });

    await page
        .locator(
            'section[aria-label="Lista de comandas"] input[type="checkbox"]',
        )
        .first()
        .check();
    await page.getByRole('button', { name: /Fechar selecionadas/ }).click();

    const dialog = page.getByRole('dialog', {
        name: 'Fechamento Consolidado',
    });
    const method = dialog.getByRole('combobox', {
        name: 'Método de pagamento 1',
    });

    await expect(method).toHaveValue('pix');
    const hiddenMethod = dialog.locator(
        'input[name="payment_allocations[0][method]"]',
    );

    await method.selectOption('debit_card');
    await dialog
        .locator('textarea[name="notes"]')
        .fill('Teste de persistência');
    await expect(method).toHaveValue('debit_card');
    await expect(hiddenMethod).toHaveValue('debit_card');

    for (const option of ['debit_card', 'credit_card', 'cash', 'permuta']) {
        await method.selectOption(option);
        await expect(method).toHaveValue(option);
        await expect(hiddenMethod).toHaveValue(option);
    }
});

test('keeps the selected payment method in an individual closing dialog', async ({
    page,
}) => {
    await login(page);

    await page.goto('/sales', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#app-loading')).toHaveCount(0, {
        timeout: 10000,
    });

    await page.getByRole('link', { name: 'Ver comanda' }).first().click();
    await page.waitForURL((url) => /\/sales\/[^/]+$/.test(url.pathname), {
        waitUntil: 'domcontentloaded',
    });
    await expect(page.locator('#app-loading')).toHaveCount(0, {
        timeout: 10000,
    });

    await page.getByRole('button', { name: 'Fechar Comanda' }).click();

    const dialog = page.getByRole('dialog', { name: 'Fechar Comanda' });
    const method = dialog.getByRole('combobox', {
        name: 'Método de pagamento 1',
    });
    const hiddenMethod = dialog.locator(
        'input[name="payment_allocations[0][method]"]',
    );

    await method.selectOption('credit_card');
    await dialog
        .locator('textarea[name="notes"]')
        .fill('Teste de persistência individual');

    await expect(method).toHaveValue('credit_card');
    await expect(hiddenMethod).toHaveValue('credit_card');
});
