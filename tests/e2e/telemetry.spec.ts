import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

type Incident = {
    category: string;
    code: string;
    frames: { file: string; line: number }[];
    path: string;
};

async function openTelemetryPage(
    page: Page,
    enabled: boolean,
    probe = false,
    fail = false,
): Promise<Incident[]> {
    const incidents: Incident[] = [];
    await page.route(/\/book\/e2e-atelier(?:\?.*)?$/, async (route) => {
        const response = await route.fetch();
        const body = (await response.text())
            .replace(
                /(<meta name="telemetry-enabled" content=")[^"]*/,
                `$1${enabled ? '1' : '0'}`,
            )
            .replace(
                /(<meta name="telemetry-probe" content=")[^"]*/,
                `$1${probe ? '1' : '0'}`,
            );
        await route.fulfill({ response, body });
    });
    await page.route('**/telemetry/browser', async (route) => {
        incidents.push(route.request().postDataJSON());
        expect(route.request().headers()['x-xsrf-token']).toBeTruthy();

        if (fail) {
            await route.abort();
        } else {
            await route.fulfill({ status: 204 });
        }
    });
    await page.goto('/book/e2e-atelier?private=customer@example.com');
    await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
    await page.context().addCookies([
        {
            name: 'XSRF-TOKEN',
            value: 'fixture%20csrf',
            url: new URL(page.url()).origin,
        },
    ]);

    return incidents;
}

async function emitJavaScriptError(
    page: Page,
    external = false,
): Promise<void> {
    await page.evaluate((external) => {
        const source = Array.from(
            document.querySelectorAll<HTMLScriptElement>('script[src]'),
        )
            .map((script) => script.src)
            .find((src) => src.includes('/build/assets/'))!;
        window.dispatchEvent(
            new ErrorEvent('error', {
                error: new TypeError(
                    'private customer@example.com token=secret',
                ),
                filename: external
                    ? 'https://evil.example/private@example.com.js'
                    : `${source}?token=secret`,
                lineno: 42,
                colno: 9,
                message: 'private customer@example.com token=secret',
            }),
        );
    }, external);
}

test('does not send browser telemetry when disabled or when the page is a probe', async ({
    page,
}) => {
    const disabled = await openTelemetryPage(page, false);
    await emitJavaScriptError(page);
    await page.waitForTimeout(100);
    expect(disabled).toHaveLength(0);
    await page.unrouteAll({ behavior: 'wait' });
    const probe = await openTelemetryPage(page, true, true);
    await emitJavaScriptError(page);
    await page.waitForTimeout(100);
    expect(probe).toHaveLength(0);
});

test('collects only sanitized frames and captures invalid Inertia status 200 without cancelling the event', async ({
    page,
}) => {
    const incidents = await openTelemetryPage(page, true);
    await emitJavaScriptError(page);
    await emitJavaScriptError(page);
    await page.evaluate(() => {
        const event = new CustomEvent('inertia:httpException', {
            cancelable: true,
            detail: {
                response: {
                    status: 200,
                    data: 'PRIVATE RESPONSE CUSTOMER@EXAMPLE.COM',
                    url: '/customers/private?secret=1',
                },
            },
        });
        document.dispatchEvent(event);

        if (event.defaultPrevented) {
            throw new Error('Telemetry cancelled Inertia behavior');
        }
    });
    await expect.poll(() => incidents.length).toBe(2);
    expect(incidents[0].frames).toHaveLength(1);
    expect(incidents[0].frames[0].file).toMatch(
        /^\/build\/assets\/[A-Za-z0-9_-]+\.js$/,
    );
    expect(incidents[1].code).toBe('200');
    expect(JSON.stringify(incidents)).not.toMatch(
        /private|secret|customer@|example\.com|RESPONSE/i,
    );
    expect(
        incidents.every((incident) => incident.path === '/book/redacted'),
    ).toBe(true);
});

test('filters external frames, caps a burst at three reports and ignores reporting failures', async ({
    page,
}) => {
    const incidents = await openTelemetryPage(page, true, false, true);
    await page.clock.install();
    await emitJavaScriptError(page, true);
    await page.evaluate(() => {
        window.dispatchEvent(
            new PromiseRejectionEvent('unhandledrejection', {
                reason: 'secret@example.com',
                promise: Promise.resolve(),
            }),
        );
        document.dispatchEvent(
            new CustomEvent('inertia:networkError', {
                detail: { error: new Error('private') },
            }),
        );
        document.dispatchEvent(
            new CustomEvent('inertia:httpException', {
                detail: { response: { status: 500 } },
            }),
        );
    });
    await expect.poll(() => incidents.length).toBe(3);
    await page.waitForTimeout(200);
    expect(incidents).toHaveLength(3);
    await page.clock.fastForward(60_001);
    await emitJavaScriptError(page, true);
    await expect.poll(() => incidents.length).toBe(4);
    expect(incidents[0].frames).toEqual([]);
    expect(JSON.stringify(incidents)).not.toMatch(
        /secret|private|evil|example\.com/i,
    );
    await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
});
