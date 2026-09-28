import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

/** The local seed provisions this path; CI may override it with a published slug. */
const publicBookingPath =
    process.env.PLAYWRIGHT_PUBLIC_BOOKING_PATH ?? '/book/teste/matriz';

async function expectNoHorizontalPageOverflow(page: Page): Promise<void> {
    await expect
        .poll(() =>
            page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        )
        .toBe(true);
}

test.describe('Public Booking E2E Flow', () => {
    test('loads the public booking page without horizontal overflow', async ({
        page,
    }) => {
        const response = await page.goto(publicBookingPath);

        if (response?.status() !== 200) {
            test.skip(
                true,
                `No published public-booking fixture at ${publicBookingPath}`,
            );
        }

        await expect(page).toHaveTitle(/Agendar/);
        await expect(
            page.getByRole('heading', { level: 1 }).first(),
        ).toBeVisible();
        await expectNoHorizontalPageOverflow(page);
    });

    test('supports service, professional, and schedule selection without submitting', async ({
        page,
    }) => {
        const response = await page.goto(publicBookingPath);

        if (response?.status() !== 200) {
            test.skip(
                true,
                `No published public-booking fixture at ${publicBookingPath}`,
            );
        }

        const servicesTab = page.getByRole('tab', {
            name: 'Serviços',
            exact: true,
        });

        if (await servicesTab.count()) {
            await servicesTab.click();
        }

        const serviceButtons = page.getByRole('button', {
            name: /\b\d+\s*min\b/i,
        });
        const serviceButton = serviceButtons.first();
        const atelierProgress = page.getByText(/Etapa 1 de 4/);
        const isAtelier = (await atelierProgress.count()) > 0;

        await expect(serviceButton).toBeVisible();
        await serviceButton.click();
        await expect(serviceButton).toHaveAttribute('aria-pressed', 'true');

        if (isAtelier) {
            const secondServiceButton = serviceButtons.nth(1);
            await expect(secondServiceButton).toBeVisible();
            await secondServiceButton.click();
            await expect(secondServiceButton).toHaveAttribute(
                'aria-pressed',
                'true',
            );

            await expect(
                page.getByText(/2 serviços selecionados/i),
            ).toBeVisible();
            await expect(page.getByText(/·\s*\d+\s*min/)).toBeVisible();
        }

        const chooseProfessionalButton = page.getByRole('button', {
            name: /Escolher profissional/i,
        });

        if (await chooseProfessionalButton.count()) {
            await chooseProfessionalButton.click();
        }

        const professionalHeading = page
            .getByRole('heading', {
                name: /Com quem você prefere|Escolha o profissional/i,
            })
            .first();
        await expect(professionalHeading).toBeVisible();

        const professionalButton = professionalHeading
            .locator('xpath=..')
            .locator('button[aria-pressed]')
            .first();
        await expect(professionalButton).toBeVisible();
        await professionalButton.click();
        await expect(professionalButton).toHaveAttribute(
            'aria-pressed',
            'true',
        );

        const chooseScheduleButton = page.getByRole('button', {
            name: /Escolher horário/i,
        });

        if (await chooseScheduleButton.count()) {
            await chooseScheduleButton.first().click();
        }

        await expect(
            page.getByRole('heading', {
                name: /Encontre o melhor|Data e horário/i,
            }),
        ).toBeVisible();

        const dateInput = page
            .getByLabel(/^(Data|Escolher outra data)$/i)
            .first();
        await expect(dateInput).toBeEnabled();
        const appointmentDate = new Date();
        appointmentDate.setDate(appointmentDate.getDate() + 2);
        const appointmentDateValue = appointmentDate.toISOString().slice(0, 10);

        await dateInput.fill(appointmentDateValue);
        await expect(dateInput).toHaveValue(appointmentDateValue);
        await expectNoHorizontalPageOverflow(page);
        await expect(
            page.getByRole('heading', { name: 'Até breve.' }),
        ).toHaveCount(0);
    });
});
