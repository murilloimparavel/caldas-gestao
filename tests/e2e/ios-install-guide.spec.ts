import { expect, test } from '@playwright/test';

for (const mode of ['browser', 'standalone', 'display-mode'] as const) {
    test(`iPhone installation guide in ${mode}`, async ({ page }) => {
        await page.addInitScript((installedMode) => {
            Object.defineProperty(navigator, 'userAgent', {
                get: () =>
                    'Mozilla/5.0 (iPhone; CPU iPhone OS 16_7 like Mac OS X)',
            });
            Object.defineProperty(navigator, 'standalone', {
                get: () => installedMode === 'standalone',
            });
            const matchMedia = window.matchMedia.bind(window);
            window.matchMedia = (query) => {
                const result = matchMedia(query);

                if (query === '(display-mode: standalone)') {
                    Object.defineProperty(result, 'matches', {
                        value: installedMode === 'display-mode',
                    });
                }

                return result;
            };
        }, mode);
        await page.goto('/login');
        await page
            .getByRole('textbox', { name: 'E-mail', exact: true })
            .fill('e2e-booking@caldas.local');
        await page.locator('input[name="password"]').fill('CaldasE2E!2026');
        await page.getByRole('button', { name: 'Entrar', exact: true }).click();
        await page.waitForURL((url) => !url.pathname.endsWith('/login'));
        await page.goto('/settings/notifications');
        await expect(
            page.getByRole('heading', { name: 'Neste aparelho' }),
        ).toBeVisible();
        const install = page.getByRole('button', {
            name: 'Ver como instalar no iPhone',
            exact: true,
        });

        if (mode === 'browser') {
            await expect(install).toBeVisible();
            await expect(
                page.getByRole('button', {
                    name: 'Ativar notificações',
                    exact: true,
                }),
            ).toHaveCount(0);
            await install.click();
            const guide = page.getByRole('dialog');
            await expect(
                guide.getByRole('list', {
                    name: 'Passos para instalar no iPhone',
                }),
            ).toBeVisible();
            await expect(guide.getByRole('listitem')).toHaveCount(3);
            await expect(
                guide.getByText('Confirme em Adicionar'),
            ).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(guide).not.toBeVisible();
        } else {
            await expect(install).toHaveCount(0);
            await expect(page.getByRole('dialog')).toHaveCount(0);
        }
    });
}

for (const permission of ['denied', 'granted'] as const) {
    test(`device state is clear with ${permission} permission`, async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.addInitScript((devicePermission) => {
            Object.defineProperty(navigator, 'userAgent', {
                get: () => 'iPhone',
            });
            Object.defineProperty(navigator, 'standalone', { get: () => true });
            Object.defineProperty(Notification, 'permission', {
                get: () => devicePermission,
            });
            Object.defineProperty(navigator.serviceWorker, 'getRegistration', {
                value: async () => ({
                    pushManager: { getSubscription: async () => null },
                }),
            });
        }, permission);
        await page.goto('/login');
        await page
            .getByRole('textbox', { name: 'E-mail', exact: true })
            .fill('e2e-booking@caldas.local');
        await page.locator('input[name="password"]').fill('CaldasE2E!2026');
        await page.getByRole('button', { name: 'Entrar', exact: true }).click();
        await page.waitForURL((url) => !url.pathname.endsWith('/login'));
        await page.route(
            /\/(settings\/notifications|dashboard)(\?.*)?$/,
            async (route) => {
                const response = await route.fetch();
                const body = (await response.text()).replace(
                    /(<script data-page="app" type="application\/json">)([\s\S]*?)(<\/script>)/,
                    (_, start, json, end) => {
                        const payload = JSON.parse(json);
                        payload.props.push = {
                            available: true,
                            publicKey: 'test-public-key',
                        };
                        payload.props.subscriptions =
                            permission === 'denied'
                                ? [
                                      {
                                          id: 'registered-device',
                                          endpoint_hash: 'old-endpoint',
                                          device_name: 'iPhone',
                                          created_at: '2026-11-03T00:00:00Z',
                                      },
                                  ]
                                : [];
                        payload.props.notificationInvitation = {
                            eligible: false,
                            dismissed: false,
                            available: true,
                            publicKey: 'test-public-key',
                            registeredEndpointHashes: [],
                        };

                        return start + JSON.stringify(payload) + end;
                    },
                );
                await route.fulfill({ response, body });
            },
        );
        await page.goto('/settings/notifications');
        const device = page.locator('section[aria-labelledby="device-title"]');
        await expect(
            device.getByText('Notificações ativadas neste aparelho.', {
                exact: false,
            }),
        ).toHaveCount(0);
        await expect(
            device.getByRole('button', { name: 'Enviar teste', exact: true }),
        ).toHaveCount(0);

        if (permission === 'denied') {
            await expect(
                device.getByText('Notificações bloqueadas.', { exact: false }),
            ).toBeVisible();
            await expect(device.getByRole('button')).toHaveCount(0);
        } else {
            await expect(
                device.getByRole('button', {
                    name: 'Reconectar neste aparelho',
                    exact: true,
                }),
            ).toBeVisible();
            const recovery = page.getByRole('complementary', {
                name: 'Reconectar notificações',
            });
            await expect(recovery).toHaveCount(0);
            await page.goto('/dashboard');
            await expect(recovery).toBeVisible();
            const bannerBox = await recovery.boundingBox();
            const navBox = await page
                .getByRole('navigation', {
                    name: 'Navegação móvel',
                    exact: true,
                })
                .boundingBox();
            expect(bannerBox).not.toBeNull();
            expect(navBox).not.toBeNull();
            expect(bannerBox!.y + bannerBox!.height).toBeLessThanOrEqual(
                navBox!.y,
            );
            await page.screenshot({
                path: '/tmp/caldas-notification-reconnect-mobile.png',
                fullPage: false,
            });
        }
    });
}
