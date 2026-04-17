const { defineConfig, devices } = require('@playwright/test');
module.exports = defineConfig({
  testDir: './specs',
  timeout: 120000,
  fullyParallel: false,
  retries: 0,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL: 'https://awamotos.com',
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    video: 'off',
    actionTimeout: 15000,
    navigationTimeout: 60000,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    launchOptions: {
      executablePath: '/home/deploy/.cache/ms-playwright/chromium_headless_shell-1208/chrome-headless-shell-linux64/chrome-headless-shell',
      args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
    },
  },
  projects: [{ name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } }],
});
