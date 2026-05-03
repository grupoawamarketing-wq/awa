import path from 'path';
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: path.join(__dirname, 'specs'),
  testMatch: /mcp-visual-ops\.spec\.ts/,
  outputDir: path.join(__dirname, 'test-results-safe'),
  globalTeardown: path.join(__dirname, 'helpers/global-teardown.ts'),
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 120_000,
  reporter: [
    ['list'],
    ['json', { outputFile: path.join(__dirname, 'reports/mcp-visual-playwright-results-safe.json') }],
  ],
  use: {
    baseURL: 'https://awamotos.com',
    ignoreHTTPSErrors: true,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    screenshot: 'only-on-failure',
    trace: 'off',
    actionTimeout: 12_000,
    navigationTimeout: 45_000,
    launchOptions: {
      args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-setuid-sandbox'],
    },
  },
  projects: [
    {
      name: 'chromium-desktop-1366',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1366, height: 768 },
      },
    },
    {
      name: 'mobile-390',
      use: {
        browserName: 'chromium',
        userAgent:
          'Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
        isMobile: true,
        hasTouch: true,
        deviceScaleFactor: 3,
        viewport: { width: 390, height: 844 },
      },
    },
  ],
});
