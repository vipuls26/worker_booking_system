import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',

    fullyParallel: false,

    workers: 1,

    retries: 0,

    reporter: 'list',

    globalSetup: './tests/e2e/global.setup.ts',

    timeout: 60 * 1000,

    expect: {
        timeout: 10 * 1000,
    },

    use: {
        baseURL: 'http://127.0.0.1:8000',

        headless: false,

        screenshot: 'only-on-failure',

        trace: 'retain-on-failure',

        video: 'retain-on-failure',

        viewport: {
            width: 1440,
            height: 960,
        },

        // launchOptions: {
        //     slowMo: 1000,
        // },
    },

    webServer: [
        {
            command:
                'APP_ENV=testing APP_URL=http://127.0.0.1:8000 php artisan serve --host=127.0.0.1 --port=8000',

            url: 'http://127.0.0.1:8000',

            reuseExistingServer: !process.env.CI,

            timeout: 120 * 1000,
        },

        {
            command:
                'APP_ENV=testing APP_URL=http://127.0.0.1:8000 php artisan reverb:start --host=127.0.0.1 --port=8080 --no-interaction',

            port: 8080,

            reuseExistingServer: !process.env.CI,

            timeout: 120 * 1000,
        },
    ],
});
