import { defineConfig, devices } from '@playwright/test';

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: 'html',
    use: {
        baseURL:
            process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://localhost:8000',
        trace: 'on-first-retry',
    },
    ...(process.env.PLAYWRIGHT_TEST_BASE_URL
        ? {}
        : {
              webServer: {
                  command: 'php artisan serve --host=127.0.0.1 --port=8000',
                  url: 'http://127.0.0.1:8000',
                  reuseExistingServer: true,
                  timeout: 120_000,
              },
          }),
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'mobile-chromium',
            use: { ...devices['Pixel 5'] },
        },
    ],
});
