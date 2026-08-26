import { test, expect } from '@playwright/test';

test.describe('Public Booking E2E Flow', () => {
    const unitSlug = 'matriz';

    test('should load public booking page and present unit info', async ({ page }) => {
        await page.goto(`/agendar/${unitSlug}`);

        // Verify title or main headings
        await expect(page).toHaveTitle(new RegExp(`Agendar`));
        await expect(page.getByRole('heading', { name: /Escolha um momento que funcione para você/i })).toBeVisible();
        await expect(page.getByText('Atendimento com hora marcada')).toBeVisible();
    });

    test('should allow selecting a service and professional, then viewing available slots', async ({ page }) => {
        await page.goto(`/agendar/${unitSlug}`);

        // Step 1: Select Service if available
        const serviceCard = page.locator('button[aria-pressed]').first();

        if (await serviceCard.isVisible()) {
            await serviceCard.click();
            await expect(serviceCard).toHaveAttribute('aria-pressed', 'true');

            // Step 2: Select Professional
            const professionalSelect = page.locator('select#professional');
            await expect(professionalSelect).toBeEnabled();

            const options = await professionalSelect.locator('option').all();

            if (options.length > 1) {
                const secondOptionValue = await options[1].getAttribute('value');

                if (secondOptionValue) {
                    await professionalSelect.selectOption(secondOptionValue);
                }
            }

            // Step 3: Select Date and render available slots
            const dateInput = page.locator('input#date');
            await expect(dateInput).toBeEnabled();

            const today = new Date().toISOString().slice(0, 10);
            await dateInput.fill(today);

            // Verify slots section or empty state message
            const slotsContainer = page.locator('section').filter({ hasText: /Horários disponíveis/i });
            await expect(slotsContainer).toBeVisible();
        } else {
            // Verify empty state when no services available
            await expect(page.getByText('Nenhum serviço está disponível para agendamento online no momento.')).toBeVisible();
        }
    });
});
