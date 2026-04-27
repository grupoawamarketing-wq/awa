/**
 * Playwright Config — AWA Motos Visual Audit Tests (8 Fases)
 * Desktop (1280px) + Mobile (375px)
 */
import { defineConfig, devices } from '@playwright/test';
import path from 'path';

export default defineConfig({
  testDir: path.join(__dirname, 'specs'),
  testMatch: /(?:visual-audit-.*|layout-container-grid)\.spec\.ts/,
  outputDir: path.join(__dirname, 'test-results'),

  timeout: 120_000,
  expect: { timeout: 10_000 },

  fullyParallel: false,
  retries: 1,
  workers: 1,

  globalTeardown: path.join(__dirname, 'helpers/global-teardown.ts'),

  reporter: [
    ['list'],
    ['json', { outputFile: path.join(__dirname, 'reports/visual-audit-results.json') }],
  ],

  use: {
    baseURL: 'https://awamotos.com',
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    video: 'off',
    actionTimeout: 10_000,
    navigationTimeout: 30_000,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    launchOptions: {
      args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-setuid-sandbox', '--disable-gpu'],
    },
  },

  projects: [
    {
      name: 'desktop-1280',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 800 },
      },
    },
    {
      name: 'mobile-375',
      use: {
        ...devices['Pixel 5'],
        viewport: { width: 375, height: 667 },
      },
    },
  ],
});
