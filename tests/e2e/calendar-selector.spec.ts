import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const fixtureEmail = 'e2e-booking@caldas.local';
const fixturePassword = 'CaldasE2E!2026';

const viewports = [
    { height: 844, name: 'mobile', width: 390 },
    { height: 900, name: 'small-tablet', width: 700 },
    { height: 900, name: 'tablet', width: 768 },
    { height: 900, name: 'desktop', width: 1280 },
] as const;

async function signInAsBookingOwner(page: Page): Promise<void> {
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
}

async function openAppointmentDialog(
    page: Page,
): Promise<ReturnType<Page['getByRole']>> {
    await signInAsBookingOwner(page);
    await page.goto('/calendar', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#app-loading')).toHaveCount(0, {
        timeout: 10000,
    });

    const createButton = page
        .locator('button:visible')
        .filter({ hasText: /^(Novo agendamento|Agendar)$/ })
        .first();
    await expect(createButton).toBeVisible({ timeout: 10000 });
    await expect(createButton).toBeEnabled();
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
    test.describe(`Calendar remote selectors · ${viewport.name}`, () => {
        test.use({
            viewport: { width: viewport.width, height: viewport.height },
        });

        test('searches services and professionals, supports keyboard selection and clear, and stays in the viewport', async ({
            page,
        }) => {
            const dialog = await openAppointmentDialog(page);
            const dialogBox = await dialog.boundingBox();

            expect(dialogBox).not.toBeNull();

            if (dialogBox) {
                expect(dialogBox.x).toBeGreaterThanOrEqual(16);
                expect(
                    page.viewportSize()!.width - dialogBox.x - dialogBox.width,
                ).toBeGreaterThanOrEqual(16);
                expect(dialogBox.width).toBeLessThanOrEqual(768);
            }

            const service = dialog.getByRole('combobox', {
                name: 'Selecione um serviço',
            });
            const professional = dialog.getByRole('combobox', {
                name: 'Selecione um profissional',
            });

            await service.click();
            const serviceRequest = page.waitForRequest((request) => {
                const url = new URL(request.url());

                return (
                    url.pathname.endsWith('/selector-options/services') &&
                    url.searchParams.get('search') === 'corte'
                );
            });
            await service.fill('corte');
            await serviceRequest;
            await expect(
                page.getByRole('option', { name: 'Corte clássico' }),
            ).toBeVisible();
            await service.press('Enter');
            await expect(service).toHaveValue('Corte clássico');
            await expect(
                page.getByRole('option', { name: 'Corte clássico' }),
            ).toHaveCount(0);

            await service.click();
            await expect(
                page.getByRole('option', { name: 'Selecione um serviço' }),
            ).toBeVisible();
            await page
                .getByRole('option', { name: 'Selecione um serviço' })
                .click();
            await expect(service).toHaveValue('');

            await professional.click();
            await professional.fill('joao');
            await expect(
                page.getByRole('option', { name: 'João Costa' }),
            ).toBeVisible();
            await professional.press('ArrowDown');
            await professional.press('Enter');
            await expect(professional).toHaveValue('João Costa');

            await professional.click();
            await expect(
                page.getByRole('option', { name: 'Selecione um profissional' }),
            ).toBeVisible();
            await page
                .getByRole('option', { name: 'Selecione um profissional' })
                .click();
            await expect(professional).toHaveValue('');

            await service.fill('servico-que-nao-existe');
            await expect(
                page.getByText('Nenhum resultado encontrado.'),
            ).toBeVisible();
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

        test('retries a failed remote professional search', async ({
            page,
        }) => {
            let failed = false;

            await page.route(
                '**/selector-options/professionals*',
                async (route) => {
                    const url = new URL(route.request().url());

                    if (url.searchParams.get('search') !== 'retry') {
                        await route.continue();

                        return;
                    }

                    if (!failed) {
                        failed = true;
                        await route.fulfill({
                            body: JSON.stringify({
                                message: 'temporary failure',
                            }),
                            contentType: 'application/json',
                            status: 503,
                        });

                        return;
                    }

                    await route.fulfill({
                        body: JSON.stringify({
                            data: [
                                {
                                    id: 'retry-professional',
                                    name: 'Retry Profissional',
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
            const professional = dialog.getByRole('combobox', {
                name: 'Selecione um profissional',
            });

            await professional.fill('retry');
            await expect(
                page.getByRole('button', {
                    name: 'Não foi possível carregar. Tentar novamente',
                }),
            ).toBeVisible();
            await expectWithinViewport(page, '[role="listbox"]');

            await page
                .getByRole('button', {
                    name: 'Não foi possível carregar. Tentar novamente',
                })
                .click();
            await expect(
                page.getByRole('option', { name: 'Retry Profissional' }),
            ).toBeVisible();
        });

        test('keeps the active option visible while navigating a long list', async ({
            page,
        }) => {
            await page.route('**/selector-options/services*', async (route) => {
                const url = new URL(route.request().url());

                if (url.searchParams.get('search') !== 'long') {
                    await route.continue();

                    return;
                }

                const data = Array.from({ length: 60 }, (_, index) => ({
                    id: `long-service-${index + 1}`,
                    name: `Serviço longo ${index + 1}`,
                }));

                await route.fulfill({
                    body: JSON.stringify({
                        data,
                        meta: {
                            current_page: 1,
                            has_more: false,
                            last_page: 1,
                            per_page: 60,
                            total: data.length,
                        },
                    }),
                    contentType: 'application/json',
                    status: 200,
                });
            });

            const dialog = await openAppointmentDialog(page);
            const service = dialog.getByRole('combobox', {
                name: 'Selecione um serviço',
            });

            await service.fill('long');
            await expect(
                page.getByRole('option', { name: 'Serviço longo 60' }),
            ).toBeVisible();

            for (let index = 0; index < 45; index += 1) {
                await service.press('ArrowDown');
            }

            await expect
                .poll(async () =>
                    service.evaluate((element) => {
                        const listbox = document.getElementById(
                            element.getAttribute('aria-controls') ?? '',
                        );
                        const activeId = element.getAttribute(
                            'aria-activedescendant',
                        );
                        const active = activeId
                            ? document.getElementById(activeId)
                            : null;

                        if (!listbox || !active) {
                            return false;
                        }

                        const listboxRect = listbox.getBoundingClientRect();
                        const activeRect = active.getBoundingClientRect();

                        return (
                            activeRect.top >= listboxRect.top &&
                            activeRect.bottom <= listboxRect.bottom
                        );
                    }),
                )
                .toBe(true);
        });
    });
}
