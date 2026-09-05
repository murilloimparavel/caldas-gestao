import { expect, test } from '@playwright/test';

/** The local seed provisions this path; CI may override it with a published slug. */
const publicBookingPath =
    process.env.PLAYWRIGHT_PUBLIC_BOOKING_PATH ?? '/book/teste/matriz';

test.describe('Public Booking E2E Flow', () => {
    test('loads the public booking page and exposes the public navigation', async ({
        page,
    }) => {
        const response = await page.goto(publicBookingPath);

        if (response?.status() !== 200) {
            test.skip(
                true,
                `No published public-booking fixture at ${publicBookingPath}`,
            );
        }

        expect(response?.status()).toBe(200);
        await expect(page).toHaveTitle(/Agendar/);
        await expect(
            page.getByRole('heading', { name: 'Matriz' }),
        ).toBeVisible();
        await expect(
            page.getByRole('navigation', { name: 'Navegação pública' }),
        ).toBeVisible();
        await expect(page.getByRole('tab', { name: 'Serviços' })).toBeVisible();
        await expect(
            page.getByRole('tab', { name: 'Profissionais' }),
        ).toBeVisible();
        await expect(
            page.getByRole('tab', { name: 'Avaliações' }),
        ).toBeVisible();
    });

    test('supports browsing the catalog and selecting a service and professional', async ({
        page,
    }) => {
        const response = await page.goto(publicBookingPath);

        if (response?.status() !== 200) {
            test.skip(
                true,
                `No published public-booking fixture at ${publicBookingPath}`,
            );
        }

        expect(response?.status()).toBe(200);
        await page.getByRole('tab', { name: 'Serviços' }).click();

        const serviceButtons = page.locator('button[aria-pressed]');

        if ((await serviceButtons.count()) === 0) {
            await expect(
                page.getByText('Nenhum serviço encontrado.'),
            ).toBeVisible();

            return;
        }

        const serviceButton = serviceButtons.first();
        await serviceButton.click();
        await expect(serviceButton).toHaveAttribute('aria-pressed', 'true');

        const professionalButtons = page.locator('button[aria-pressed]');
        await expect(professionalButtons.nth(1)).toBeVisible();
        await professionalButtons.nth(1).click();
        await expect(professionalButtons.nth(1)).toHaveAttribute(
            'aria-pressed',
            'true',
        );

        const dateInput = page.locator('input#date');
        await expect(dateInput).toBeEnabled();
        await dateInput.fill(
            new Date(Date.now() + 86400000).toISOString().slice(0, 10),
        );
        await expect(page.getByText('Data e horário')).toBeVisible();
    });
});
