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
        formFactor: 'mobile',
        throttlingMethod: 'simulate',
        screenEmulation: {
          mobile: true,
          width: 390,
          height: 844,
          deviceScaleFactor: 2.75,
          disabled: false,
        },
        chromeFlags: '--headless=new --no-sandbox --disable-dev-shm-usage',
      },
    },
    assert: {
      assertions: {
        'categories:performance': ['error', { minScore: Number(process.env.LHCI_MOBILE_PERFORMANCE_SCORE || '0.45') }],
        'first-contentful-paint': ['error', { maxNumericValue: Number(process.env.LHCI_MOBILE_MAX_FCP || '2500') }],
        'largest-contentful-paint': ['error', { maxNumericValue: Number(process.env.LHCI_MOBILE_MAX_LCP || '4000') }],
        'total-blocking-time': ['warn', { maxNumericValue: Number(process.env.LHCI_MOBILE_MAX_TBT || '600') }],
        'cumulative-layout-shift': ['warn', { maxNumericValue: Number(process.env.LHCI_MAX_CLS || '0.1') }],
        'speed-index': ['error', { maxNumericValue: Number(process.env.LHCI_MOBILE_MAX_SPEED_INDEX || '5000') }],
      },
    },
    upload: {
      target: 'filesystem',
      outputDir: '.lighthouseci/mobile',
      reportFilenamePattern: '%%PATHNAME%%-mobile-%%DATETIME%%-report.%%EXTENSION%%',
    },
  },
};
