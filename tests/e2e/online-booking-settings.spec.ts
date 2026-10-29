import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const fixtureEmail = 'e2e-booking@caldas.local';
const fixturePassword = 'CaldasE2E!2026';

async function signInAsBookingOwner(page: Page) {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(fixtureEmail);
    await page.locator('input[name="password"]').fill(fixturePassword);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
}

test.describe('Online booking settings panel', () => {
    test('saves multiple opening periods through the editor without losing identity or catalog', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name === 'mobile-chromium',
            'One shared fixture writer; mobile navigation and layout are covered separately.',
        );
        await signInAsBookingOwner(page);
        await page.goto('/online-booking');
        await expect(page.locator('input[name="whatsapp_phone"]')).toHaveValue(
            '(64) 99999-1234',
        );
        await expect(page.locator('input[name="instagram_url"]')).toHaveValue(
            'https://www.instagram.com/romawear.e2e/',
        );
        const selectedCatalog = () =>
            page
                .locator(
                    'input[name="service_ids[]"]:checked, input[name="professional_ids[]"]:checked',
                )
                .evaluateAll((inputs) =>
                    inputs
                        .map((input) => (input as HTMLInputElement).value)
                        .sort(),
                );
        const initialCatalog = await selectedCatalog();
        expect(initialCatalog.length).toBe(5);
        const navigation = page.getByRole('navigation', {
            name: 'Configuração do agendamento online',
        });
        await navigation
            .getByRole('button', { name: 'Horários', exact: true })
            .click();
        await page
            .getByLabel('Segunda-feira fim 1', { exact: true })
            .fill('12:00');
        await page
            .getByRole('button', {
                name: 'Adicionar período Segunda-feira',
                exact: true,
            })
            .click();
        await page
            .getByLabel('Segunda-feira início 2', { exact: true })
            .fill('14:00');
        await page
            .getByLabel('Segunda-feira fim 2', { exact: true })
            .fill('18:00');
        const saveDraft = async () => {
            const previousLockVersion = await page
                .locator('input[name="lock_version"]')
                .inputValue();
            const mutationResponse = page.waitForResponse(
                (response) =>
                    new URL(response.url()).pathname === '/online-booking' &&
                    ['PATCH', 'POST'].includes(response.request().method()),
                { timeout: 5000 },
            );
            const redirectedPage = page.waitForResponse(
                (response) =>
                    new URL(response.url()).pathname === '/online-booking' &&
                    response.request().method() === 'GET' &&
                    response
                        .headers()
                        ['content-type']?.includes('application/json'),
                { timeout: 5000 },
            );
            await page
                .getByRole('button', { name: 'Salvar rascunho', exact: true })
                .click();
            const mutation = await mutationResponse;
            expect(mutation.status()).toBeLessThan(400);
            const redirected = await redirectedPage;
            await redirected.finished();
            const payload = await redirected.json();
            expect(payload.props.errors).toEqual({});
            expect(payload.props.flash.error).toBeFalsy();
            expect(payload.props.flash.success).toBe(
                'Agendamento online atualizado.',
            );
            await expect(
                page.locator('input[name="lock_version"]'),
            ).not.toHaveValue(previousLockVersion);
            await expect(
                page.locator('input[name="lock_version"]'),
            ).toHaveValue(String(payload.props.unit.lock_version));
            return payload.props;
        };
        let savedSuccessfully = false;

        try {
            const saved = await saveDraft();
            savedSuccessfully = true;
            expect(saved.settings.public_hours['1'].display_periods).toEqual([
                { starts_at: '09:00', ends_at: '12:00' },
                { starts_at: '14:00', ends_at: '18:00' },
            ]);
            await page.reload();
            await expect(
                page.locator('input[name="whatsapp_phone"]'),
            ).toHaveValue('(64) 99999-1234');
            await expect(
                page.locator('input[name="instagram_url"]'),
            ).toHaveValue('https://www.instagram.com/romawear.e2e/');
            expect(await selectedCatalog()).toEqual(initialCatalog);
            await navigation
                .getByRole('button', { name: 'Horários', exact: true })
                .click();
            await expect(
                page.getByLabel('Segunda-feira início 1', { exact: true }),
            ).toHaveValue('09:00');
            await expect(
                page.getByLabel('Segunda-feira fim 1', { exact: true }),
            ).toHaveValue('12:00');
            await expect(
                page.getByLabel('Segunda-feira início 2', { exact: true }),
            ).toHaveValue('14:00');
            await expect(
                page.getByLabel('Segunda-feira fim 2', { exact: true }),
            ).toHaveValue('18:00');
        } finally {
            if (savedSuccessfully && !page.isClosed()) {
                await page.goto('/online-booking');
                await navigation
                    .getByRole('button', { name: 'Horários', exact: true })
                    .click();
                if (
                    await page
                        .getByRole('button', {
                            name: 'Remover período 2 Segunda-feira',
                            exact: true,
                        })
                        .count()
                ) {
                    await page
                        .getByRole('button', {
                            name: 'Remover período 2 Segunda-feira',
                            exact: true,
                        })
                        .click();
                }
                await page
                    .getByLabel('Segunda-feira início 1', { exact: true })
                    .fill('09:00');
                await page
                    .getByLabel('Segunda-feira fim 1', { exact: true })
                    .fill('18:00');
                await saveDraft();
            }
        }
    });

    test('opens the publication checklist from the settings navigation', async ({
        page,
    }) => {
        await signInAsBookingOwner(page);
        await page.goto('/online-booking');
        const publication = page
            .getByRole('navigation', {
                name: 'Configuração do agendamento online',
            })
            .getByRole('button', { name: 'Publicação', exact: true });

        await publication.click();
        await expect(publication).toHaveAttribute('aria-current', 'page');
        await expect(
            page.getByText('Checklist de publicação', { exact: true }),
        ).toBeVisible();
    });

    test('keeps form text readable in both themes and desktop preview at desktop width', async ({
        page,
    }) => {
        await signInAsBookingOwner(page);
        await page.goto('/online-booking');

        const identityTitle = page.getByText('Identidade pública', {
            exact: true,
        });
        await expect(identityTitle).toBeVisible();

        const identityCard = identityTitle.locator(
            'xpath=ancestor::*[@data-slot="card"][1]',
        );
        const whatsappLabel = page.getByText('WhatsApp', { exact: true });
        await expect(whatsappLabel).toBeVisible();

        for (const darkMode of [false, true]) {
            await page.locator('html').evaluate((html, enabled) => {
                html.classList.toggle('dark', enabled);
            }, darkMode);

            const readContrast = () =>
                identityCard.evaluate((card) => {
                    const label = card.querySelector(
                        'label[for="whatsapp_phone"]',
                    );
                    const input = card.querySelector(
                        'input[name="whatsapp_phone"]',
                    );

                    if (!label || !(input instanceof HTMLInputElement)) {
                        throw new Error(
                            'The WhatsApp field is missing from its card.',
                        );
                    }

                    const rgba = (
                        color: string,
                    ): {
                        channels: [number, number, number];
                        alpha: number;
                    } => {
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');

                        if (!context) {
                            throw new Error(
                                'Canvas color conversion is unavailable.',
                            );
                        }

                        context.fillStyle = color;
                        context.fillRect(0, 0, 1, 1);

                        const [red, green, blue, alpha] = context.getImageData(
                            0,
                            0,
                            1,
                            1,
                        ).data;

                        return {
                            channels: [red, green, blue],
                            alpha: alpha / 255,
                        };
                    };
                    const composite = (
                        foreground: ReturnType<typeof rgba>,
                        background: ReturnType<typeof rgba>,
                    ): ReturnType<typeof rgba> => {
                        const alpha =
                            foreground.alpha +
                            background.alpha * (1 - foreground.alpha);

                        if (alpha === 0) {
                            return { channels: [0, 0, 0], alpha: 0 };
                        }

                        const channels = foreground.channels.map(
                            (channel, index) =>
                                Math.round(
                                    (channel * foreground.alpha +
                                        background.channels[index] *
                                            background.alpha *
                                            (1 - foreground.alpha)) /
                                        alpha,
                                ),
                        ) as [number, number, number];

                        return { channels, alpha };
                    };
                    const luminance = (
                        color: ReturnType<typeof rgba>,
                    ): number => {
                        const channels = color.channels.map((channel) => {
                            const value = channel / 255;

                            return value <= 0.04045
                                ? value / 12.92
                                : ((value + 0.055) / 1.055) ** 2.4;
                        });

                        return (
                            0.2126 * channels[0] +
                            0.7152 * channels[1] +
                            0.0722 * channels[2]
                        );
                    };
                    const ratio = (
                        foreground: ReturnType<typeof rgba>,
                        background: ReturnType<typeof rgba>,
                    ): number => {
                        const foregroundLuminance = luminance(
                            composite(foreground, background),
                        );
                        const backgroundLuminance = luminance(background);
                        const lighter = Math.max(
                            foregroundLuminance,
                            backgroundLuminance,
                        );
                        const darker = Math.min(
                            foregroundLuminance,
                            backgroundLuminance,
                        );

                        return (lighter + 0.05) / (darker + 0.05);
                    };
                    const surfaceColor = getComputedStyle(card).backgroundColor;
                    const surface = rgba(surfaceColor);
                    const inputStyle = getComputedStyle(input);
                    const inputBackground = composite(
                        rgba(inputStyle.backgroundColor),
                        surface,
                    );
                    const placeholderColor = getComputedStyle(
                        input,
                        '::placeholder',
                    ).color;

                    return {
                        label: ratio(
                            rgba(getComputedStyle(label).color),
                            surface,
                        ),
                        input: ratio(rgba(inputStyle.color), inputBackground),
                        placeholder: ratio(
                            rgba(placeholderColor),
                            inputBackground,
                        ),
                        colors: {
                            surface: surfaceColor,
                            inputBackground: inputStyle.backgroundColor,
                            inputText: inputStyle.color,
                            placeholder: placeholderColor,
                            className: input.className,
                        },
                    };
                });

            await expect
                .poll(
                    async () => {
                        const contrast = await readContrast();

                        return [
                            contrast.label,
                            contrast.input,
                            contrast.placeholder,
                        ].every((ratio) => ratio >= 4.5)
                            ? 'PASS'
                            : JSON.stringify({ darkMode, ...contrast });
                    },
                    { timeout: 5000 },
                )
                .toBe('PASS');
        }

        await page
            .getByRole('button', { name: 'Prévia em computador' })
            .click();
        await expect(
            page.getByRole('dialog', { name: 'Prévia em computador' }),
        ).toBeVisible();

        const desktopPreview = page.getByTitle(
            'Prévia pública do agendamento online em computador',
        );
        await expect(desktopPreview).toBeVisible();
        await expect
            .poll(() =>
                desktopPreview.evaluate((iframe) =>
                    Math.round(iframe.getBoundingClientRect().width),
                ),
            )
            .toBe(1280);

        const desktopScrollArea = page.getByTestId(
            'desktop-preview-scroll-area',
        );
        const scrollDimensions = await desktopScrollArea.evaluate(
            (element) => ({
                clientWidth: element.clientWidth,
                scrollWidth: element.scrollWidth,
            }),
        );
        expect(scrollDimensions.scrollWidth).toBeGreaterThan(
            scrollDimensions.clientWidth,
        );
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);

        const frameViewport = await page
            .frameLocator(
                'iframe[title="Prévia pública do agendamento online em computador"]',
            )
            .locator('html')
            .evaluate(() => window.innerWidth);
        expect(frameViewport).toBeGreaterThanOrEqual(1024);
    });
});
