import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './specs',
  testMatch: /qa-full-audit\.spec\.ts/,
  timeout: 180_000,
  expect: { timeout: 15_000 },
  retries: 1,
  workers: 1,
  reporter: [
    ['list'],
    ['json', { outputFile: 'reports/qa-audit-results.json' }],
  ],
  use: {
    baseURL: 'https://awamotos.com',
    navigationTimeout: 60_000,
    actionTimeout: 15_000,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'firefox-1280',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1280, height: 800 },
      },
    },
  ],
});
