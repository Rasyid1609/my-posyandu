import { defineConfig, devices } from '@playwright/test';
import { resolve } from 'node:path';

export default defineConfig({
    testDir: './tests/Browser',
    timeout: 90_000,
    expect: { timeout: 30_000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: 'list',
    outputDir: 'storage/framework/testing/browser-results',
    use: {
        actionTimeout: 15_000,
        baseURL: 'http://127.0.0.1:8013',
        locale: 'id-ID',
        timezoneId: 'Asia/Jakarta',
        screenshot: 'only-on-failure',
        trace: 'off',
        launchOptions: { channel: 'msedge' },
    },
    projects: [
        {
            name: 'desktop',
            use: { ...devices['Desktop Edge'], viewport: { width: 1440, height: 1000 } },
        },
        {
            name: 'mobile',
            use: {
                ...devices['Desktop Edge'],
                viewport: { width: 390, height: 844 },
                isMobile: true,
                hasTouch: true,
            },
        },
    ],
    webServer: {
        command: 'node tests/Browser/server.mjs',
        url: 'http://127.0.0.1:8013/login',
        reuseExistingServer: false,
        timeout: 120_000,
        env: {
            APP_ENV: 'local',
            APP_URL: 'http://127.0.0.1:8013',
            APP_LOCALE: 'id',
            BCRYPT_ROUNDS: '4',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: resolve('storage/framework/testing/browser-' + Date.now() + '.sqlite'),
            DB_URL: '',
            CACHE_STORE: 'file',
            SESSION_DRIVER: 'file',
            INERTIA_SSR_ENABLED: 'false',
            VITE_HOT_FILE: resolve('storage/framework/testing/browser-no-vite.hot'),
        },
    },
});
