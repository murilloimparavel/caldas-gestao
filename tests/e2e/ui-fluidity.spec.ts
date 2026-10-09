import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const fixtureEmail = 'e2e-booking@caldas.local';
const fixturePassword = 'CaldasE2E!2026';

async function signIn(page: Page): Promise<void> {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="email"]').fill(fixtureEmail);
    await page.locator('input[name="password"]').fill(fixturePassword);
    await page.locator('[data-test="login-button"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), {
        waitUntil: 'domcontentloaded',
    });
}

test('shows accessible feedback while an authenticated layout is loading', async ({
    page,
}) => {
    await page.route('**/assets/app-layout-*.js', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 1500));
        await route.continue();
    });

    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="email"]').fill(fixtureEmail);
    await page.locator('input[name="password"]').fill(fixturePassword);
    await page.locator('[data-test="login-button"]').click();

    await expect(
        page.getByRole('status', { name: 'Carregando a página' }),
    ).toBeVisible();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), {
        waitUntil: 'domcontentloaded',
    });
    await expect(
        page.getByRole('status', { name: 'Carregando a página' }),
    ).toBeHidden();
    await expect(page).toHaveTitle(/Painel operacional/);
});

test('uses a fresh idempotency key for each category update on the same page', async ({
    isMobile,
    page,
}) => {
    test.skip(
        isMobile,
        'This seeded category is intentionally mutated once per run.',
    );

    await signIn(page);
    await page.goto('/categories');

    const seededCategory = page.locator('article').filter({
        has: page.getByRole('heading', { name: 'Cabelo', exact: true }),
    });
    await seededCategory.getByRole('link', { name: 'Ver cadastro' }).click();
    await expect(
        page.getByRole('heading', { name: 'Cabelo', exact: true }),
    ).toBeVisible();

    const submitUpdate = async (name: string): Promise<string> => {
        await page.getByLabel('Nome da categoria').fill(name);
        const requestPromise = page.waitForRequest(
            (request) =>
                request.url().includes('/categories/') &&
                request.method() !== 'GET',
        );
        await page.getByRole('button', { name: 'Salvar alterações' }).click();
        const request = await requestPromise;
        const idempotencyKey = request.headers()['x-idempotency-key'];

        await expect(
            page.getByRole('heading', { name, exact: true }),
        ).toBeVisible();

        return idempotencyKey ?? '';
    };

    const firstKey = await submitUpdate('Cabelo E2E primeira alteração');
    const secondKey = await submitUpdate('Cabelo E2E segunda alteração');

    expect(firstKey).not.toBe('');
    expect(secondKey).not.toBe('');
    expect(secondKey).not.toBe(firstKey);
});

test('uses a fresh idempotency key for each package update on the same page', async ({
    page,
}) => {
    await signIn(page);
    await page.goto('/packages');

    await page.getByRole('button', { name: 'Novo Pacote' }).click();
    const createDialog = page.getByRole('dialog', {
        name: 'Criar Pacote de Serviços',
    });
    await createDialog.getByLabel('Nome do Pacote').fill('Pacote E2E inicial');
    await createDialog.getByLabel('Preço Total').fill('100,00');
    await createDialog.getByRole('checkbox').first().check();
    await createDialog.getByRole('button', { name: 'Criar Pacote' }).click();
    await page.waitForURL(/\/packages\/[0-9a-f-]+$/);
    await expect(
        page.getByRole('heading', { name: 'Pacote E2E inicial', exact: true }),
    ).toBeVisible();

    const updatePackage = async (name: string): Promise<string> => {
        await page.getByRole('button', { name: 'Editar Pacote' }).click();
        const dialog = page.getByRole('dialog', {
            name: 'Editar Pacote de Serviços',
        });
        await dialog.getByLabel('Nome do Pacote').fill(name);
        const requestPromise = page.waitForRequest(
            (request) =>
                /\/packages\/[0-9a-f-]+$/.test(
                    new URL(request.url()).pathname,
                ) && request.method() !== 'GET',
        );
        await dialog.getByRole('button', { name: 'Salvar Alterações' }).click();
        const request = await requestPromise;
        const idempotencyKey = request.headers()['x-idempotency-key'];

        await expect(dialog).toBeHidden();
        await expect(
            page.getByRole('heading', { name, exact: true }),
        ).toBeVisible();

        return idempotencyKey ?? '';
    };

    const firstKey = await updatePackage('Pacote E2E primeira edição');
    const secondKey = await updatePackage('Pacote E2E segunda edição');

    expect(firstKey).not.toBe('');
    expect(secondKey).not.toBe('');
    expect(secondKey).not.toBe(firstKey);
});

test('closes the mobile drawer after navigating to another page', async ({
    isMobile,
    page,
}) => {
    test.skip(
        !isMobile,
        'The drawer interaction is specific to mobile viewports.',
    );

    await signIn(page);

    const openNavigationButton = page.getByRole('button', {
        name: 'Mais — abrir navegação principal',
    });
    await expect(openNavigationButton).toBeVisible();
    await openNavigationButton.click();

    const drawer = page.getByRole('dialog', { name: 'Barra lateral' });
    await expect(drawer).toBeVisible();
    await drawer.getByRole('button', { name: 'Cadastros' }).click();
    await drawer.getByRole('link', { name: 'Clientes' }).click();

    await page.waitForURL('**/customers');
    await expect(drawer).toBeHidden();
    await expect(page.getByRole('heading', { name: 'Clientes' })).toBeVisible();
});

test('persists a gallery reorder and restores the order after a failed update', async ({
    isMobile,
    page,
}) => {
    test.skip(
        isMobile,
        'The seeded gallery order is intentionally mutated once per run.',
    );

    await signIn(page);
    await page.goto('/online-booking');
    await page.getByRole('button', { name: 'Galeria' }).click();

    const imageCards = page.locator('div.relative.aspect-square');
    const altTextInputs = page.locator(
        'input[aria-label^="Texto alternativo da imagem"]',
    );
    await expect(altTextInputs).toHaveCount(2);
    await expect(altTextInputs.nth(0)).toHaveValue('Foto de teste 1');
    await expect(altTextInputs.nth(1)).toHaveValue('Foto de teste 2');

    const successfulReorder = page.waitForResponse((response) =>
        new URL(response.url()).pathname.endsWith(
            '/online-booking/gallery/reorder',
        ),
    );
    await imageCards
        .nth(1)
        .getByRole('button', { name: 'Mover imagem para cima' })
        .click();
    expect((await successfulReorder).status()).toBe(200);
    await expect(altTextInputs.nth(0)).toHaveValue('Foto de teste 2');
    await expect(altTextInputs.nth(1)).toHaveValue('Foto de teste 1');

    await page.reload();
    await page.getByRole('button', { name: 'Galeria' }).click();
    await expect(altTextInputs.nth(0)).toHaveValue('Foto de teste 2');
    await expect(altTextInputs.nth(1)).toHaveValue('Foto de teste 1');

    await page.route('**/online-booking/gallery/**', async (route) => {
        if (route.request().method() === 'PATCH') {
            await route.fulfill({
                status: 500,
                contentType: 'application/json',
                body: JSON.stringify({
                    message: 'Falha de teste ao salvar o texto alternativo.',
                }),
            });

            return;
        }

        await route.continue();
    });
    const firstAltText = altTextInputs.nth(0);
    await firstAltText.fill('Texto alternativo não persistido');
    const failedAltUpdate = page.waitForResponse(
        (response) =>
            response.request().method() === 'PATCH' &&
            new URL(response.url()).pathname.includes(
                '/online-booking/gallery/',
            ),
    );
    await firstAltText.blur();
    expect((await failedAltUpdate).status()).toBe(500);
    await expect(firstAltText).toHaveValue('Foto de teste 2');
    await expect(
        page.getByText(
            'O servidor não conseguiu salvar o texto alternativo. Tente novamente.',
        ),
    ).toBeVisible();

    await page.route('**/online-booking/gallery/reorder', (route) =>
        route.fulfill({
            status: 500,
            contentType: 'application/json',
            body: JSON.stringify({
                message: 'Falha de teste ao salvar a ordem.',
            }),
        }),
    );
    const failedReorder = page.waitForResponse((response) =>
        new URL(response.url()).pathname.endsWith(
            '/online-booking/gallery/reorder',
        ),
    );
    await imageCards
        .nth(0)
        .getByRole('button', { name: 'Mover imagem para baixo' })
        .click();
    expect((await failedReorder).status()).toBe(500);
    await expect(altTextInputs.nth(0)).toHaveValue('Foto de teste 2');
    await expect(altTextInputs.nth(1)).toHaveValue('Foto de teste 1');

    await page.reload();
    await page.getByRole('button', { name: 'Galeria' }).click();
    await expect(altTextInputs.nth(0)).toHaveValue('Foto de teste 2');
    await expect(altTextInputs.nth(1)).toHaveValue('Foto de teste 1');

    let galleryOnlyReloadRequested = false;
    await page.route('**/online-booking/gallery', async (route) => {
        if (route.request().method() === 'POST') {
            await route.fulfill({
                status: 201,
                contentType: 'application/json',
                body: JSON.stringify({
                    gallery: {
                        id: 'e2e-uploaded-image',
                        path: 'e2e-uploaded-image.webp',
                        alt_text: null,
                        position: 3,
                    },
                }),
            });

            return;
        }

        await route.continue();
    });
    await page.route('**/online-booking', async (route) => {
        const request = route.request();
        const partialData = request.headers()['x-inertia-partial-data'];

        if (
            request.method() === 'GET' &&
            partialData?.split(',').includes('gallery')
        ) {
            galleryOnlyReloadRequested = partialData === 'gallery';
            const response = await route.fetch();
            const payload = await response.json();
            payload.props.gallery.push({
                id: 'e2e-uploaded-image',
                path: 'e2e-uploaded-image.webp',
                alt_text: null,
                position: 3,
            });

            await route.fulfill({ response, json: payload });

            return;
        }

        await route.continue();
    });
    const uploadResponse = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            new URL(response.url()).pathname.endsWith(
                '/online-booking/gallery',
            ),
    );
    await page
        .getByText('Galeria de fotos', { exact: true })
        .locator('xpath=ancestor::*[@data-slot="card"][1]')
        .locator('input[type="file"]')
        .setInputFiles({
            name: 'foto-e2e.png',
            mimeType: 'image/png',
            buffer: Buffer.from(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/oZsAAAAASUVORK5CYII=',
                'base64',
            ),
        });
    expect((await uploadResponse).status()).toBe(201);
    await expect(altTextInputs).toHaveCount(3);
    await expect(altTextInputs.nth(2)).toHaveValue('');
    expect(galleryOnlyReloadRequested).toBe(true);
});

test('syncs normalized appearance props after saving and reloading the draft', async ({
    isMobile,
    page,
}) => {
    test.skip(
        isMobile,
        'The seeded appearance draft is intentionally mutated once per run.',
    );

    await signIn(page);
    await page.goto('/online-booking');

    const primaryColor = page.locator('#appearance-primary_color');
    await expect(primaryColor).toBeVisible();
    await primaryColor.fill('#a1b2c3');

    const draftUpdate = page.waitForResponse(
        (response) =>
            response.request().method() === 'PATCH' &&
            new URL(response.url()).pathname.endsWith('/online-booking/draft'),
    );
    await page.getByRole('button', { name: 'Salvar identidade' }).click();
    expect((await draftUpdate).ok()).toBeTruthy();

    await expect(page.getByText('Aparência salva no rascunho.')).toBeVisible();
    await expect(page.getByText('#A1B2C3', { exact: true })).toBeVisible();

    await page.reload();
    await expect(primaryColor).toHaveValue('#a1b2c3');
    await expect(page.getByText('#A1B2C3', { exact: true })).toBeVisible();
});
