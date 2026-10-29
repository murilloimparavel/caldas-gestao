import { expect, test } from '@playwright/test';

const atelierPath = '/book/e2e-atelier';

test.describe('Public booking establishment details', () => {
    test('keeps service content and booking actions inside narrow and tablet viewports', async ({
        page,
    }) => {
        await page.route(`**${atelierPath}`, async (route) => {
            const response = await route.fetch();
            const html = await response.text();
            const body = html.replace(
                /(<script\b[^>]*\bdata-page\b[^>]*>)([\s\S]*?)(<\/script>)/,
                (_match, opening: string, encoded: string, closing: string) => {
                    const payload = JSON.parse(encoded);
                    payload.props.services[0].name = 'Depilação Nariz + Orelha';
                    payload.props.services[0].price_cents = 123456;

                    return `${opening}${JSON.stringify(payload).replaceAll('<', '\\u003c')}${closing}`;
                },
            );

            await route.fulfill({ response, body });
        });

        for (const width of [320, 390, 600, 768, 820]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto(atelierPath);
            const main = page.locator('main');
            await expect(
                page.getByRole('heading', { level: 1 }).first(),
            ).toBeVisible();
            const mainBounds = await main.boundingBox();
            expect(mainBounds!.x).toBe(0);
            expect(mainBounds!.width).toBe(width);
            const cards = page.locator('[data-booking-service-card]');
            expect(await cards.count()).toBeGreaterThan(0);

            for (const card of await cards.all()) {
                const cardBounds = await card.boundingBox();
                expect(cardBounds!.x).toBeGreaterThanOrEqual(
                    width >= 768 ? 40 : 16,
                );
                expect(cardBounds!.x + cardBounds!.width).toBeLessThanOrEqual(
                    width - (width >= 768 ? 40 : 16),
                );

                for (const selector of [
                    '[data-booking-service-name]',
                    '[data-booking-service-price]',
                    '[data-booking-service-toggle]',
                ]) {
                    const content = card.locator(selector);
                    const bounds = await content.boundingBox();
                    expect(bounds!.width).toBeGreaterThan(0);
                    expect(bounds!.x).toBeGreaterThanOrEqual(cardBounds!.x);
                    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(
                        cardBounds!.x + cardBounds!.width,
                    );
                    expect(
                        await content.evaluate(
                            (element) =>
                                element.scrollWidth <= element.clientWidth,
                        ),
                    ).toBe(true);
                }
            }

            const summaryBounds = await page
                .locator('[data-booking-selection-summary]')
                .boundingBox();
            const buttonBounds = await page
                .getByRole('button', { name: 'Escolher profissional' })
                .boundingBox();
            expect(summaryBounds!.x).toBe(0);
            expect(summaryBounds!.width).toBe(width);
            expect(buttonBounds!.x).toBe(0);
            expect(buttonBounds!.width).toBe(width);
            expect(
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth <=
                        window.innerWidth,
                ),
            ).toBe(true);
        }
    });

    test('preserves the desktop booking columns and header geometry', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(atelierPath);
        const mainBounds = await page.locator('main').boundingBox();
        const formBounds = await page.locator('main form').boundingBox();
        const servicesBounds = await page
            .locator('main form > section')
            .first()
            .boundingBox();
        expect(mainBounds!.x).toBe(144);
        expect(mainBounds!.width).toBe(1152);
        expect(formBounds!.x).toBe(208);
        expect(formBounds!.width).toBe(1024);
        expect(servicesBounds!.x).toBe(208);
        expect(servicesBounds!.width).toBe(672);
        const ctaBounds = await page
            .getByRole('button', { name: 'Escolher profissional' })
            .boundingBox();
        expect(ctaBounds!.x).toBe(937);
        expect(ctaBounds!.width).toBe(270);
        expect(ctaBounds!.height).toBe(48);
    });

    test('extends the header canvas across desktop and mobile while keeping its content aligned', async ({
        page,
    }) => {
        for (const viewport of [
            { width: 1440, height: 900 },
            { width: 390, height: 844 },
        ]) {
            await page.setViewportSize(viewport);
            await page.goto(atelierPath);
            const header = page.locator('main header');
            await expect(header).toBeVisible();
            const headerBounds = await header.boundingBox();
            const contentBounds = await header
                .locator(':scope > div')
                .boundingBox();
            const mainBounds = await page.locator('main').boundingBox();
            expect(headerBounds).not.toBeNull();
            expect(contentBounds).not.toBeNull();
            expect(mainBounds).not.toBeNull();
            expect(headerBounds!.x).toBe(0);
            expect(headerBounds!.width).toBe(viewport.width);
            expect(headerBounds!.height).toBe(64);
            expect(contentBounds!.x).toBe(mainBounds!.x);
            expect(contentBounds!.width).toBe(mainBounds!.width);
            expect(
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth <=
                        window.innerWidth,
                ),
            ).toBe(true);
        }
    });

    test('preserves selected services and totals when visiting establishment details', async ({
        page,
    }) => {
        await page.goto(atelierPath);
        await expect(page.locator('head meta[name="description"]')).toHaveCount(
            1,
        );
        await expect(
            page.locator('head meta[name="description"]'),
        ).toHaveAttribute(
            'content',
            'Cuidado e estilo no centro de Caldas Novas.',
        );
        const canonicalUrl = await page.evaluate(() => {
            const encodedPage = document.querySelector(
                'script[type="application/json"][data-page]',
            )?.textContent;

            return JSON.parse(encodedPage ?? '{}').props.unit
                .canonical_url as string;
        });
        expect(canonicalUrl).toBeTruthy();
        await expect(page.locator('head link[rel="canonical"]')).toHaveCount(1);
        await expect(
            page.locator('head link[rel="canonical"]'),
        ).toHaveAttribute('href', canonicalUrl);
        const businessSchema = page.locator(
            'head script[type="application/ld+json"]',
        );
        await expect(businessSchema).toHaveCount(1);
        const structuredData = JSON.parse(
            (await businessSchema.textContent()) ?? '{}',
        );
        expect(structuredData['@type']).toBe('BeautySalon');
        expect(structuredData.url).toBe(canonicalUrl);
        await page.getByRole('button', { name: /Corte clássico/ }).click();
        await page.getByRole('button', { name: /Barba clássica/ }).click();

        const detailsButton = page.getByRole('button', {
            name: 'Sobre o estabelecimento',
            exact: true,
        });
        await detailsButton.click();
        await expect(detailsButton).toHaveAttribute('aria-pressed', 'true');
        await expect(
            page.getByRole('heading', { name: 'Contato e redes sociais' }),
        ).toBeVisible();
        await page
            .getByRole('button', { name: 'Voltar ao agendamento' })
            .click();

        await expect(
            page.getByRole('button', { name: 'Agendar', exact: true }),
        ).toHaveAttribute('aria-pressed', 'true');
        await expect(
            page.getByRole('button', { name: /Corte clássico/ }),
        ).toHaveAttribute('aria-pressed', 'true');
        await expect(
            page.getByRole('button', { name: /Barba clássica/ }),
        ).toHaveAttribute('aria-pressed', 'true');
        await expect(
            page.getByText(/2 serviços selecionados/i).first(),
        ).toBeVisible();
        await expect(page.getByText(/75 min/).first()).toBeVisible();
        await expect(page.getByText(/R\$\s?85,00/).first()).toBeVisible();
        await page
            .getByRole('button', { name: 'Escolher profissional' })
            .click();
        await expect(
            page.getByRole('heading', { name: /Com quem você/ }),
        ).toBeVisible();
    });

    test('shows usable contact, address and social links and distinguishes closed days', async ({
        page,
    }) => {
        // Change only the displayed hours; keep availability fixtures usable on every weekday.
        await page.route(`**${atelierPath}`, async (route) => {
            const response = await route.fetch();
            const html = await response.text();
            const body = html.replace(
                /(<script\b[^>]*\bdata-page\b[^>]*>)([\s\S]*?)(<\/script>)/,
                (_match, opening: string, encoded: string, closing: string) => {
                    const payload = JSON.parse(encoded);
                    payload.props.unit.public_hours['0'].enabled = false;
                    payload.props.unit.public_hours['0'].display_periods = [
                        { starts_at: '08:00', ends_at: '12:00' },
                    ];
                    payload.props.unit.public_hours['1'].display_periods = [
                        { starts_at: '09:00', ends_at: '12:00' },
                        { starts_at: '14:00', ends_at: '18:00' },
                        { starts_at: '25:00', ends_at: '26:00' },
                    ];

                    return `${opening}${JSON.stringify(payload).replaceAll('<', '\\u003c')}${closing}`;
                },
            );

            await route.fulfill({ response, body });
        });
        await page.goto(atelierPath);
        await page
            .getByRole('button', {
                name: 'Sobre o estabelecimento',
                exact: true,
            })
            .click();

        await expect(
            page.getByText('Cuidado e estilo no centro de Caldas Novas.'),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: '(64) 3453-1234', exact: true }),
        ).toHaveAttribute('href', 'tel:+556434531234');
        await expect(
            page.getByRole('link', { name: 'WhatsApp', exact: true }),
        ).toHaveAttribute('href', /^https:\/\/wa.me\/5564999991234\?text=/);
        const whatsappUrl = new URL(
            (await page
                .getByRole('link', { name: 'WhatsApp', exact: true })
                .getAttribute('href'))!,
        );
        expect(whatsappUrl.searchParams.get('text')).toContain(
            'Vim do agendamento online de',
        );
        expect(whatsappUrl.searchParams.get('text')).toContain(
            'Poderia me ajudar',
        );

        for (const [label, href] of [
            ['Instagram', 'https://www.instagram.com/romawear.e2e/'],
            ['Facebook', 'https://www.facebook.com/romawear.e2e/'],
            ['Site', 'https://example.com/romawear'],
        ]) {
            const link = page.getByRole('link', { name: label, exact: true });

            await expect(link).toHaveAttribute('href', href);
            await expect(link).toHaveAttribute('target', '_blank');
            await expect(link).toHaveAttribute('rel', /noopener/);
            await expect(link).toHaveAttribute('rel', /noreferrer/);
        }

        const directions = page.getByRole('link', {
            name: 'Como chegar',
            exact: true,
        });
        await expect(directions).toHaveAttribute(
            'href',
            /^https:\/\/www\.google\.com\/maps\/search\/\?api=1&query=/,
        );
        const mapsUrl = new URL((await directions.getAttribute('href'))!);
        expect(mapsUrl.searchParams.get('query')).toContain('Rua das Flores');
        expect(mapsUrl.searchParams.get('query')).toContain('Caldas Novas');
        await expect(
            page.getByText('Domingo', { exact: true }).locator('..'),
        ).toContainText('Fechado');
        await expect(
            page.getByText('Segunda-feira', { exact: true }).locator('..'),
        ).toContainText('09:00 às 12:00 · 14:00 às 18:00');
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);
    });

    test('uses the atelier canvas color without changing the essential template', async ({
        page,
    }) => {
        await page.goto(atelierPath);
        await expect(page.locator('html')).toHaveCSS(
            'background-color',
            'rgb(14, 14, 14)',
        );
        await expect(page.locator('body')).toHaveCSS(
            'background-color',
            'rgb(14, 14, 14)',
        );
        await page.goto('/book/e2e-essential');
        await expect(
            page.getByRole('heading', { level: 1 }).first(),
        ).toBeVisible();
        await expect(page.locator('html')).not.toHaveClass(/atelier-booking/);
        await expect(page.locator('body')).not.toHaveCSS(
            'background-color',
            'rgb(14, 14, 14)',
        );
    });
});
