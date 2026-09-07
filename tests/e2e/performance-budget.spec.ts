import { expect, test } from '@playwright/test';
import type { ConsoleMessage, Page, Request } from '@playwright/test';

const publicBookingPath =
    process.env.PLAYWRIGHT_PUBLIC_BOOKING_PATH ?? '/book/teste/matriz';

type PerformanceMetrics = {
    cls: number;
    fcp: number;
    lcp: number;
    ttfb: number;
};

const enforcePerformanceBudget =
    process.env.PLAYWRIGHT_ENFORCE_PERFORMANCE_BUDGET === 'true';

async function assertPerformanceBudget(
    page: Page,
    path: string,
): Promise<void> {
    const failures: string[] = [];
    const consoleErrors: string[] = [];

    page.on('requestfailed', (request: Request) => {
        failures.push(`${request.method()} ${request.url()}`);
    });
    page.on('console', (message: ConsoleMessage) => {
        if (message.type() === 'error') {
            consoleErrors.push(message.text());
        }
    });

    const response = await page.goto(path, {
        waitUntil: 'load',
        timeout: 30_000,
    });

    expect(response?.status()).toBe(200);
    await page.waitForTimeout(250);

    const metrics = await page.evaluate((): PerformanceMetrics => {
        const navigation = performance.getEntriesByType(
            'navigation',
        )[0] as PerformanceNavigationTiming;
        const paintEntries = performance.getEntriesByType('paint');
        const firstContentfulPaint = paintEntries.find(
            (entry) => entry.name === 'first-contentful-paint',
        );
        const largestContentfulPaint = performance
            .getEntriesByType('largest-contentful-paint')
            .at(-1);

        return {
            cls: performance
                .getEntriesByType('layout-shift')
                .reduce((total, entry) => {
                    const shift = entry as PerformanceEntry & {
                        hadRecentInput?: boolean;
                        value?: number;
                    };

                    return (
                        total + (shift.hadRecentInput ? 0 : (shift.value ?? 0))
                    );
                }, 0),
            fcp: firstContentfulPaint?.startTime ?? 0,
            lcp: largestContentfulPaint?.startTime ?? 0,
            ttfb: navigation.responseStart,
        };
    });

    await test.info().attach('performance-metrics.json', {
        body: JSON.stringify({ path, metrics }, null, 2),
        contentType: 'application/json',
    });
    test.info().annotations.push({
        type: 'performance-budget',
        description: enforcePerformanceBudget ? 'enforced' : 'reported-only',
    });

    if (enforcePerformanceBudget) {
        expect(
            metrics.ttfb,
            `TTFB exceeded budget: ${metrics.ttfb} ms`,
        ).toBeLessThan(800);
    }
    expect(
        metrics.fcp,
        `FCP was not recorded: ${metrics.fcp} ms`,
    ).toBeGreaterThan(0);
    if (enforcePerformanceBudget) {
        expect(
            metrics.lcp,
            `LCP was not recorded: ${metrics.lcp} ms`,
        ).toBeGreaterThan(0);
        expect(
            metrics.fcp,
            `FCP exceeded budget: ${metrics.fcp} ms`,
        ).toBeLessThan(2_500);
        expect(
            metrics.lcp,
            `LCP exceeded budget: ${metrics.lcp} ms`,
        ).toBeLessThan(2_500);
        expect(metrics.cls, `CLS exceeded budget: ${metrics.cls}`).toBeLessThan(
            0.1,
        );
    }
    expect(failures, `Failed requests: ${failures.join(', ')}`).toHaveLength(0);
    expect(
        consoleErrors,
        `Console errors: ${consoleErrors.join(' | ')}`,
    ).toHaveLength(0);
}

test.describe('Performance budgets', () => {
    test('login stays within the loading budget', async ({ page }) => {
        await assertPerformanceBudget(page, '/login');
    });

    test('public booking stays within the loading budget', async ({ page }) => {
        const response = await page.goto(publicBookingPath, {
            waitUntil: 'domcontentloaded',
            timeout: 30_000,
        });

        if (response?.status() !== 200) {
            test.skip(
                true,
                `No published public-booking fixture at ${publicBookingPath}`,
            );
        }

        await assertPerformanceBudget(page, publicBookingPath);
    });
});
