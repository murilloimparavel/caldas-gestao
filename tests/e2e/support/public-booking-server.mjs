import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, isAbsolute, relative, resolve, sep } from 'node:path';
import { rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import process from 'node:process';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
const databasePath = process.env.PLAYWRIGHT_E2E_DB_PATH;
const temporaryDirectory = process.env.PLAYWRIGHT_E2E_TMP_DIR;
const port = process.env.PLAYWRIGHT_E2E_PORT ?? '8765';

const temporaryRelativePath = temporaryDirectory
    ? relative(resolve(tmpdir()), resolve(temporaryDirectory))
    : '';

if (
    !databasePath ||
    !temporaryDirectory ||
    !isAbsolute(databasePath) ||
    !isAbsolute(temporaryDirectory) ||
    temporaryRelativePath === '' ||
    temporaryRelativePath === '..' ||
    temporaryRelativePath.startsWith(`..${sep}`)
) {
    throw new Error('Playwright E2E requires its isolated temporary SQLite database path.');
}

const environment = {
    ...process.env,
    APP_ENV: 'testing',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: databasePath,
    DB_URL: '',
    CACHE_STORE: 'array',
    QUEUE_CONNECTION: 'sync',
    SESSION_DRIVER: 'database',
    SESSION_CONNECTION: 'sqlite',
    SESSION_COOKIE: 'caldas_public_booking_e2e',
    SESSION_SECURE_COOKIE: 'false',
};

function run(command, args) {
    return new Promise((resolvePromise, rejectPromise) => {
        const processHandle = spawn(command, args, {
            cwd: projectRoot,
            env: environment,
            stdio: 'inherit',
        });

        processHandle.once('error', rejectPromise);
        processHandle.once('exit', (code) => {
            if (code === 0) {
                resolvePromise();

                return;
            }

            rejectPromise(new Error(`${command} ${args.join(' ')} exited with ${code}.`));
        });
    });
}

await run('php', ['artisan', 'migrate:fresh', '--force']);
await run('php', ['artisan', 'db:seed', '--class=PublicBookingE2ESeeder', '--force']);

const server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${port}`], {
    cwd: projectRoot,
    env: environment,
    stdio: 'inherit',
});

const shutdown = () => {
    server.kill('SIGTERM');
};

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);

server.once('error', (error) => {
    rmSync(temporaryDirectory, { recursive: true, force: true });

    throw error;
});

server.once('exit', (code) => {
    rmSync(temporaryDirectory, { recursive: true, force: true });
    process.exitCode = code ?? 1;
});
