import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const fixtureEmail = 'e2e-booking@caldas.local';
const fixturePassword = 'CaldasE2E!2026';

async function signInAsBookingOwner(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(fixtureEmail);
    await page.locator('input[name="password"]').fill(fixturePassword);
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
}

test.describe('Online booking settings panel', () => {
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
                    ): { channels: [number, number, number]; alpha: number } => {
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

        await page.getByRole('button', { name: 'Prévia em desktop' }).click();
        await expect(
            page.getByRole('dialog', { name: 'Prévia em desktop' }),
        ).toBeVisible();

        const desktopPreview = page.getByTitle(
            'Prévia pública do agendamento online em desktop',
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
                'iframe[title="Prévia pública do agendamento online em desktop"]',
            )
            .locator('html')
            .evaluate(() => window.innerWidth);
        expect(frameViewport).toBeGreaterThanOrEqual(1024);
    });
});
