'use strict';

const baseUrl = (process.env.LHCI_BASE_URL || process.env.BASE_URL || 'https://awamotos.com').replace(/\/+$/, '');
const runs = Number.parseInt(process.env.LHCI_RUNS || '3', 10);

module.exports = {
  ci: {
    collect: {
      url: [
        `${baseUrl}/`,
        `${baseUrl}/bagageiros.html`,
        `${baseUrl}/ret-biz-100-cr-redondo-universal-2220.html`,
      ],
      numberOfRuns: Number.isFinite(runs) && runs > 0 ? runs : 3,
      settings: {
        formFactor: 'desktop',
        throttlingMethod: 'simulate',
        screenEmulation: {
          mobile: false,
          width: 1366,
          height: 768,
          deviceScaleFactor: 1,
          disabled: false,
        },
        chromeFlags: '--headless=new --no-sandbox --disable-dev-shm-usage',
      },
    },
    assert: {
      assertions: {
        'categories:performance': ['warn', { minScore: Number(process.env.LHCI_DESKTOP_PERFORMANCE_SCORE || '0.65') }],
        'first-contentful-paint': ['error', { maxNumericValue: Number(process.env.LHCI_DESKTOP_MAX_FCP || '1800') }],
        'largest-contentful-paint': ['warn', { maxNumericValue: Number(process.env.LHCI_DESKTOP_MAX_LCP || '3000') }],
        'total-blocking-time': ['warn', { maxNumericValue: Number(process.env.LHCI_DESKTOP_MAX_TBT || '350') }],
        'cumulative-layout-shift': ['warn', { maxNumericValue: Number(process.env.LHCI_MAX_CLS || '0.1') }],
        'speed-index': ['warn', { maxNumericValue: Number(process.env.LHCI_DESKTOP_MAX_SPEED_INDEX || '3500') }],
      },
    },
    upload: {
      target: 'filesystem',
      outputDir: '.lighthouseci/desktop',
      reportFilenamePattern: '%%PATHNAME%%-desktop-%%DATETIME%%-report.%%EXTENSION%%',
    },
  },
};
