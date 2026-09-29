import { defineConfig, devices } from '@playwright/test';
import { mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createServer } from 'node:net';

const e2eTemporaryDirectory = process.env.PLAYWRIGHT_TEST_BASE_URL
    ? undefined
    : (process.env.PLAYWRIGHT_E2E_TMP_DIR ??
      mkdtempSync(join(tmpdir(), 'caldas-public-booking-e2e-')));
const findAvailablePort = (): Promise<string> =>
    new Promise((resolve, reject) => {
        const server = createServer();

        server.once('error', reject);
        server.listen(0, '127.0.0.1', () => {
            const address = server.address();

            if (address === null || typeof address === 'string') {
                server.close();
                reject(new Error('Could not allocate the Playwright E2E server port.'));

                return;
            }

            server.close(() => resolve(String(address.port)));
        });
    });

const e2ePort =
    process.env.PLAYWRIGHT_E2E_PORT ?? (await findAvailablePort());

if (!process.env.PLAYWRIGHT_TEST_BASE_URL) {
    process.env.PLAYWRIGHT_E2E_TMP_DIR = e2eTemporaryDirectory;
    process.env.PLAYWRIGHT_E2E_PORT = e2ePort;
}

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
            process.env.PLAYWRIGHT_TEST_BASE_URL ||
            `http://127.0.0.1:${e2ePort}`,
        trace: 'on-first-retry',
    },
    ...(process.env.PLAYWRIGHT_TEST_BASE_URL
        ? {}
        : {
              webServer: {
                  command: 'node tests/e2e/support/public-booking-server.mjs',
                  url: `http://127.0.0.1:${e2ePort}`,
                  reuseExistingServer: false,
                  timeout: 120_000,
                  env: {
                      APP_ENV: 'testing',
                      DB_CONNECTION: 'sqlite',
                      SESSION_DRIVER: 'database',
                      SESSION_CONNECTION: 'sqlite',
                      SESSION_COOKIE: 'caldas_public_booking_e2e',
                      SESSION_SECURE_COOKIE: 'false',
                      DB_DATABASE: join(
                          e2eTemporaryDirectory ?? tmpdir(),
                          'database.sqlite',
                      ),
                      PLAYWRIGHT_E2E_DB_PATH: join(
                          e2eTemporaryDirectory ?? tmpdir(),
                          'database.sqlite',
                      ),
                      PLAYWRIGHT_E2E_TMP_DIR:
                          e2eTemporaryDirectory ?? tmpdir(),
                      PLAYWRIGHT_E2E_PORT: e2ePort,
                  },
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
