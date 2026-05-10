import { defineConfig, devices } from '@playwright/test';
import path from 'path';

export default defineConfig({
  testDir: path.join(__dirname, 'specs'),
  testMatch: /(visual-deep-audit\.spec\.ts|\/(smoke|deep-visual)\/.*\.spec\.ts$)/,
  outputDir: path.join(__dirname, 'test-results/deep-audit'),

  timeout: 240_000,
  expect: { timeout: 15_000 },

  fullyParallel: false,
  retries: 1,
  workers: 1,

  reporter: [
    ['list'],
    ['html', { outputFolder: path.join(__dirname, 'reports/deep-audit-html'), open: 'never' }],
    ['json', { outputFile: path.join(__dirname, 'reports/deep-audit-results.json') }],
  ],

  use: {
    baseURL: 'https://awamotos.com',
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    video: 'off',
    trace: 'retain-on-failure',
    actionTimeout: 15_000,
    navigationTimeout: 90_000,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
  },

  projects: [
    {
      name: 'firefox-desktop-1366',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1366, height: 900 },
      },
    },
    {
      name: 'firefox-desktop-1280',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1280, height: 800 },
      },
    },
    {
      name: 'firefox-mobile-390',
      use: {
        browserName: 'firefox',
        viewport: { width: 390, height: 844 },
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
        trace: 'off',
        video: 'off',
        screenshot: 'only-on-failure',
      },
    },
  ],
});
