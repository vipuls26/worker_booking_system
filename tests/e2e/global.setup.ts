import { execSync, spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import net from 'node:net';
import type { FullConfig } from '@playwright/test';

type EnvironmentValues = Record<string, string>;

const defaultApplicationUrl = 'http://127.0.0.1:8000';
const defaultMailpitUrl = process.env.PLAYWRIGHT_MAILPIT_URL || 'http://localhost:8025';
const databaseReadyTimeoutInMilliseconds = 30_000;
const mailpitReadyTimeoutInMilliseconds = 15_000;

/**
 * Read .env values with a small parser so Playwright setup can reuse the same local ports.
 */
function readEnvironmentFile(filePath = '.env'): EnvironmentValues {
    const fileContents = readFileSync(filePath, 'utf8');
    const values: EnvironmentValues = {};

    for (const line of fileContents.split('\n')) {
        const trimmedLine = line.trim();

        if (! trimmedLine || trimmedLine.startsWith('#')) {
            continue;
        }

        const separatorIndex = trimmedLine.indexOf('=');

        if (separatorIndex === -1) {
            continue;
        }

        const key = trimmedLine.slice(0, separatorIndex).trim();
        const rawValue = trimmedLine.slice(separatorIndex + 1).trim();

        values[key] = rawValue.replace(/^['"]|['"]$/g, '');
    }

    return values;
}

/**
 * Try to open one TCP connection so service startup can be verified without assuming framework internals.
 */
function isTcpPortOpen(host: string, port: number): Promise<boolean> {
    return new Promise((resolve) => {
        const socket = new net.Socket();

        socket.setTimeout(2_000);
        socket.once('connect', () => {
            socket.destroy();
            resolve(true);
        });
        socket.once('timeout', () => {
            socket.destroy();
            resolve(false);
        });
        socket.once('error', () => {
            socket.destroy();
            resolve(false);
        });

        socket.connect(port, host);
    });
}

/**
 * Poll a TCP endpoint until the process behind it is ready for Playwright setup.
 */
async function waitForTcpPort(host: string, port: number, timeoutInMilliseconds: number): Promise<void> {
    const startedAt = Date.now();

    while (Date.now() - startedAt < timeoutInMilliseconds) {
        if (await isTcpPortOpen(host, port)) {
            return;
        }

        await new Promise((resolve) => setTimeout(resolve, 500));
    }

    throw new Error(`Timed out waiting for ${host}:${port} to accept TCP connections.`);
}

/**
 * Start a background service only when the caller explicitly provides a startup command.
 */
function startDetachedProcess(command: string): void {
    const child = spawn(command, {
        detached: true,
        shell: true,
        stdio: 'ignore',
        env: process.env,
    });

    child.unref();
}

/**
 * Make sure the local database port is reachable before Artisan tries migrations.
 */
async function ensureDatabaseReady(environmentValues: EnvironmentValues): Promise<void> {
    const databaseConnection = process.env.DB_CONNECTION || environmentValues.DB_CONNECTION || 'mysql';

    if (databaseConnection === 'sqlite') {
        return;
    }

    const databaseHost = process.env.DB_HOST || environmentValues.DB_HOST || '127.0.0.1';
    const databasePort = Number(process.env.DB_PORT || environmentValues.DB_PORT || '3306');

    if (await isTcpPortOpen(databaseHost, databasePort)) {
        return;
    }

    const databaseStartCommand = process.env.PLAYWRIGHT_DB_START_COMMAND;

    if (databaseStartCommand) {
        startDetachedProcess(databaseStartCommand);
        await waitForTcpPort(databaseHost, databasePort, databaseReadyTimeoutInMilliseconds);

        return;
    }

    throw new Error(
        `Database is not reachable at ${databaseHost}:${databasePort}. ` +
        'Start MySQL manually, or set PLAYWRIGHT_DB_START_COMMAND so Playwright can launch it automatically.',
    );
}

/**
 * Make sure Mailpit is reachable before tests that read emails begin polling its API.
 */
async function ensureMailpitReady(): Promise<void> {
    const mailpitUrl = new URL(defaultMailpitUrl);
    const mailpitHost = mailpitUrl.hostname;
    const mailpitPort = Number(mailpitUrl.port || (mailpitUrl.protocol === 'https:' ? '443' : '80'));

    if (await isTcpPortOpen(mailpitHost, mailpitPort)) {
        return;
    }

    const mailpitStartCommand = process.env.PLAYWRIGHT_MAILPIT_START_COMMAND;

    if (mailpitStartCommand) {
        startDetachedProcess(mailpitStartCommand);
        await waitForTcpPort(mailpitHost, mailpitPort, mailpitReadyTimeoutInMilliseconds);

        return;
    }

    throw new Error(
        `Mailpit is not reachable at ${defaultMailpitUrl}. ` +
        'Start Mailpit manually, or set PLAYWRIGHT_MAILPIT_START_COMMAND so Playwright can launch it automatically.',
    );
}

/**
 * Prepare the Laravel app with stable E2E data before Playwright starts.
 */
function runArtisanCommand(command: string): void {
    execSync(command, {
        stdio: 'inherit',
        env: {
            ...process.env,
            APP_ENV: 'testing',
            APP_URL: process.env.APP_URL || defaultApplicationUrl,
        },
    });
}

export default async function globalSetup(_config: FullConfig): Promise<void> {
    const environmentValues = readEnvironmentFile();

    await ensureMailpitReady();
    await ensureDatabaseReady(environmentValues);

    // Clear only config cache so this setup does not depend on the database cache store.
    runArtisanCommand('php artisan config:clear');

    // Build the frontend once so Playwright can load the SPA without a separate Vite server.
    runArtisanCommand('npm run build');

    // Persistent browser profile runs can skip database reset so saved auth sessions stay valid.
    if (process.env.PLAYWRIGHT_SKIP_DATABASE_RESET === '1') {
        return;
    }

    // Reset the testing database so every Playwright run starts from the same business state.
    runArtisanCommand('php artisan migrate:fresh --force');

    // Seed stable customer, worker, admin, notification, and workflow records for E2E tests.
    runArtisanCommand('php artisan db:seed --class=Database\\\\Seeders\\\\E2ETestSeeder --force');
}
