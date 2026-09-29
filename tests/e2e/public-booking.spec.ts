import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const publicBookingPath =
    process.env.PLAYWRIGHT_PUBLIC_BOOKING_PATH ?? '/book/e2e-atelier';
const templatePaths = [
    '/book/e2e-atelier',
    '/book/e2e-essential',
];

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
    for (const path of templatePaths) {
        test(`loads ${path} without horizontal overflow`, async ({ page }) => {
            const response = await page.goto(path);

            expect(response, `Expected ${path} to return a document response`).not.toBeNull();
            expect(response?.status(), `Expected ${path} to load successfully`).toBe(200);
            await expect(page).toHaveTitle(/Matriz/);
            await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
            await expectNoHorizontalPageOverflow(page);
        });
    }

    test('supports category filtering, multiple services, totals, back navigation, and compatible professionals', async ({
        page,
    }) => {
        const response = await page.goto(publicBookingPath);
        expect(response?.status()).toBe(200);

        const haircut = page.getByRole('button', { name: /Corte clássico/ });
        await expect(haircut).toBeVisible();
        await haircut.click();
        await expect(haircut).toHaveAttribute('aria-pressed', 'true');

        const beardCategory = page.getByRole('button', {
            name: 'Barba',
            exact: true,
        });
        await beardCategory.click();
        await expect(haircut).toHaveCount(0);
        const beard = page.getByRole('button', { name: /Barba clássica/ });
        await expect(beard).toBeVisible();
        await beard.click();
        await expect(beard).toHaveAttribute('aria-pressed', 'true');

        await page.getByRole('button', { name: 'Todos', exact: true }).click();
        await expect(
            page.getByText(/2 serviços selecionados/i).first(),
        ).toBeVisible();
        await expect(page.getByText(/75 min/).first()).toBeVisible();
        await expect(page.getByText(/R\$\s?85,00/).first()).toBeVisible();

        await page.getByRole('button', { name: 'Escolher profissional' }).click();
        await expect(page.getByRole('heading', { name: /Com quem você/ })).toBeVisible();
        await page.getByRole('button', { name: 'Voltar' }).click();

        await expect(
            page.getByRole('button', { name: /Corte clássico/ }),
        ).toHaveAttribute('aria-pressed', 'true');
        await expect(
            page.getByRole('button', { name: /Barba clássica/ }),
        ).toHaveAttribute('aria-pressed', 'true');

        await page.getByRole('button', { name: 'Todos', exact: true }).click();
        await page.getByRole('button', { name: 'Cuidados', exact: true }).click();
        const color = page.getByRole('button', { name: /Coloração discreta/ });
        await color.click();
        await page.getByRole('button', { name: 'Todos', exact: true }).click();
        await page.getByRole('button', { name: 'Escolher profissional' }).click();

        await expect(page.getByText('Lucas Prado').first()).toBeVisible();
        await expect(page.getByText('João Costa')).toHaveCount(0);
        await expectNoHorizontalPageOverflow(page);
    });

    test('chooses the earliest real availability across eligible professionals', async ({
        page,
    }) => {
        const response = await page.goto(publicBookingPath);
        expect(response?.status()).toBe(200);

        const pageProps = await page.evaluate(() => {
            const pageElement = document.querySelector(
                'script[type="application/json"][data-page]',
            );
            const encoded = pageElement?.textContent ?? '';

            return JSON.parse(encoded).props as {
                professionals: { id: string; name: string }[];
            };
        });
        const professionalNames = new Map(
            pageProps.professionals.map(({ id, name }) => [id, name]),
        );
        const availabilityByCandidate = new Map<
            string,
            { professionalId: string; startsAt: string }[]
        >();

        page.on('response', (availabilityResponse) => {
            const url = new URL(availabilityResponse.url());

            if (!url.pathname.endsWith('/availability')) {
                return;
            }

            const date = url.searchParams.get('date');
            const professionalId = url.searchParams.get('professional_id');

            if (!date || !professionalId) {
                return;
            }

            void availabilityResponse.json().then((body) => {
                availabilityByCandidate.set(`${date}:${professionalId}`, [
                    ...(availabilityByCandidate.get(`${date}:${professionalId}`) ?? []),
                    ...body.slots.map(({ starts_at }: { starts_at: string }) => ({
                        professionalId,
                        startsAt: starts_at,
                    })),
                ]);
            });
        });

        await page.getByRole('button', { name: /Corte clássico/ }).click();
        await page.getByRole('button', { name: 'Escolher profissional' }).click();
        await expect(page.getByRole('heading', { name: /Com quem você/ })).toBeVisible();
        await page.getByRole('button', { name: /Primeiro disponível/ }).click();

        const selectedProfessionalNotice = page.getByRole('status');
        await expect(selectedProfessionalNotice).toContainText(
            /Primeiro horário encontrado para/,
        );
        const selectedProfessionalName = (await selectedProfessionalNotice.textContent())
            ?.match(/para (.+?) em /)?.[1];
        expect(selectedProfessionalName).toBeTruthy();
        await page.getByRole('button', { name: 'Escolher horário' }).click();
        await expect(page.getByRole('heading', { name: /Encontre o melhor/ })).toBeVisible();
        const dateInput = page.getByLabel(/^(Data|Escolher outra data)$/i).first();
        const selectedDate = await dateInput.inputValue();

        await expect
            .poll(() =>
                pageProps.professionals.filter((professional) =>
                    (availabilityByCandidate.get(
                        `${selectedDate}:${professional.id}`,
                    )?.length ?? 0) > 0,
                ).length,
            )
            .toBe(2);

        const earliestCandidateSlot = pageProps.professionals
            .flatMap((professional) =>
                availabilityByCandidate.get(
                    `${selectedDate}:${professional.id}`,
                ) ?? [],
            )
            .sort(
                (first, second) =>
                    Date.parse(first.startsAt) - Date.parse(second.startsAt),
            )[0];
        expect(earliestCandidateSlot).toBeDefined();
        if (!earliestCandidateSlot) {
            throw new Error('No candidate availability was returned for the selected date.');
        }
        expect(selectedProfessionalName).toBe(
            professionalNames.get(earliestCandidateSlot.professionalId),
        );

        const expectedTime = await page.evaluate(
            (startsAt) =>
                new Intl.DateTimeFormat('pt-BR', {
                    hour: '2-digit',
                    minute: '2-digit',
                    timeZone: 'America/Sao_Paulo',
                }).format(new Date(startsAt)),
            earliestCandidateSlot.startsAt,
        );
        await expect(
            page.getByText(expectedTime, { exact: true }).first(),
        ).toBeVisible();
    });
});
