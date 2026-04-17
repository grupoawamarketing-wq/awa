import { defineConfig, devices } from '@playwright/test';
import path from 'path';

/**
 * Playwright Config — AWA Motos Header Visual Tests
 * Breakpoints: Tablet (768–1024px), Notebook (1024–1366px)
 */
export default defineConfig({
  testDir: path.join(__dirname, 'specs'),
  outputDir: path.join(__dirname, 'test-results'),
  snapshotDir: path.join(__dirname, 'snapshots'),

  /* Timeout por teste: 120s (Magento B2B com KnockoutJS + login assíncrono) */
  timeout: 120_000,
  expect: {
    timeout: 8_000,
    /* Tolerância para comparação de screenshots: 0.3% de pixels diferentes */
    toHaveScreenshot: {
      maxDiffPixelRatio: 0.003,
      animations: 'disabled',
    },
  },

  fullyParallel: false, // serial para comparações visuais consistentes
  retries: 1,
  workers: 1,

  /* Cleanup garantido ao final — evita processos Chrome órfãos no servidor */
  globalTeardown: path.join(__dirname, 'helpers/global-teardown.ts'),

  reporter: [
    ['html', { outputFolder: path.join(__dirname, 'reports/html'), open: 'never' }],
    ['json', { outputFile: path.join(__dirname, 'reports/results.json') }],
    ['list'],
  ],

  use: {
    baseURL: 'https://awamotos.com',
    /* Ignora erros TLS caso o certificado seja auto-assinado em staging */
    ignoreHTTPSErrors: true,
    /* Captura screenshots apenas em falha; os testes pedem screenshot manual */
    screenshot: 'only-on-failure',
    video: 'off',
    actionTimeout: 10_000,
    navigationTimeout: 20_000,
    /* Locale BR para renderização correta de fontes/datas */
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    launchOptions: {
      executablePath: '/home/deploy/.cache/ms-playwright/chromium_headless_shell-1208/chrome-headless-shell-linux64/chrome-headless-shell',
      args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-setuid-sandbox'],
    },
  },

  projects: [
    /* ── TABLET ────────────────────────────────────────────── */
    {
      name: 'tablet-768',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 768, height: 1024 },
      },
    },
    {
      name: 'tablet-960',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 960, height: 768 },
      },
    },
    {
      name: 'tablet-1024',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1024, height: 768 },
      },
    },

    /* ── NOTEBOOK (telas pequenas) ──────────────────────────── */
    {
      name: 'notebook-1024',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1024, height: 600 },
      },
    },
    {
      name: 'notebook-1280',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 720 },
      },
    },
    {
      name: 'notebook-1366',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1366, height: 768 },
      },
    },

    /* ── FIREFOX (cross-browser) ─────────────────────────────── */
    {
      name: 'firefox-tablet-768',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 768, height: 1024 },
      },
    },
    {
      name: 'firefox-notebook-1280',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1280, height: 720 },
      },
    },

    /* ── WEBKIT/SAFARI ──────────────────────────────────────── */
    {
      name: 'webkit-tablet-768',
      use: {
        ...devices['Desktop Safari'],
        viewport: { width: 768, height: 1024 },
      },
    },
    {
      name: 'webkit-notebook-1280',
      use: {
        ...devices['Desktop Safari'],
        viewport: { width: 1280, height: 720 },
      },
    },

    /* ── MOBILE ─────────────────────────────────────────────── */
    {
      name: 'mobile-375',
      use: {
        ...devices['iPhone SE'],
        viewport: { width: 375, height: 667 },
      },
    },
    {
      name: 'mobile-390',
      use: {
        ...devices['iPhone 14'],
        viewport: { width: 390, height: 844 },
      },
    },
  ],
});
