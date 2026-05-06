import path from 'path';
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: path.join(__dirname, 'specs'),
  testMatch: /mcp-visual-ops\.spec\.ts/,
  outputDir: path.join(__dirname, 'test-results'),
  globalTeardown: path.join(__dirname, 'helpers/global-teardown.ts'),
  fullyParallel: false,
  workers: 1,
  retries: 1,
  timeout: 180_000,
  reporter: [
    ['list'],
    ['json', { outputFile: path.join(__dirname, 'reports/mcp-visual-playwright-results.json') }],
    ['html', { outputFolder: path.join(__dirname, 'reports/mcp-visual-html'), open: 'never' }],
  ],
  use: {
    baseURL: 'https://awamotos.com',
    ignoreHTTPSErrors: true,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    actionTimeout: 15_000,
    navigationTimeout: 60_000,
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
      name: 'firefox-desktop-1366',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1366, height: 768 },
      },
    },
    /* WebKit removed — system missing required libs (libgtk-4, libgraphene, etc.) */
    {
      name: 'mobile-390',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
        userAgent: 'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Mobile Safari/537.36',
      },
    },
  ],
});
