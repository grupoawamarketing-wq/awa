import { test, expect } from '@playwright/test';

const HOME_URL = process.env.HOME_SMOKE_URL || 'https://awamotos.com/';

async function unlockDeferredCss(page: import('@playwright/test').Page): Promise<void> {
  await page.evaluate(() => {
    if (typeof (window as Window & { __awaApplyGatedCSS?: () => void }).__awaApplyGatedCSS === 'function') {
      (window as Window & { __awaApplyGatedCSS?: () => void }).__awaApplyGatedCSS!();
    }
    document.querySelectorAll('link[rel="stylesheet"][media="print"]').forEach((link) => {
      (link as HTMLLinkElement).media = 'all';
    });
  });
  await page.waitForTimeout(800);
}

test.describe('Home header layout', () => {
  test.describe.configure({ timeout: 90_000 });

  test.beforeEach(async ({ page }) => {
    await page.goto(`${HOME_URL}?header-smoke=${Date.now()}`, {
      waitUntil: 'domcontentloaded',
      timeout: 90_000,
    });
    await page.waitForSelector('.header-wrapper-sticky', { timeout: 30_000 });
    await unlockDeferredCss(page);
  });

  test('mobile — busca contida no sticky e hero abaixo do header', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForSelector('.wrapper_slider.visible-xs', { timeout: 30_000 }).catch(() => {});

    const metrics = await page.evaluate(() => {
      const sticky = document.querySelector('.header-wrapper-sticky')?.getBoundingClientRect();
      const form = document.querySelector('#search_mini_form')?.getBoundingClientRect();
      const hero = document.querySelector('.wrapper_slider.visible-xs')?.getBoundingClientRect();
      const main = document.querySelector('.header-main');
      const mainCs = main ? getComputedStyle(main) : null;

      return {
        stickyBottom: sticky?.bottom ?? 0,
        formBottom: form?.bottom ?? 0,
        heroTop: hero?.top ?? 0,
        mainHeight: main?.getBoundingClientRect().height ?? 0,
        mainPadTop: mainCs?.paddingTop ?? '',
        promoWidth: document.querySelector('.top-header')?.getBoundingClientRect().width ?? 0,
        viewportWidth: window.innerWidth,
      };
    });

    expect(metrics.mainPadTop).toBe('0px');
    expect(metrics.mainHeight).toBeLessThanOrEqual(104);
    expect(metrics.formBottom).toBeLessThanOrEqual(metrics.stickyBottom + 1);
    expect(metrics.heroTop).toBeGreaterThanOrEqual(metrics.stickyBottom - 1);
    expect(metrics.promoWidth).toBeGreaterThanOrEqual(metrics.viewportWidth - 2);
  });

  test('desktop — promo bar full-width e nav sem clip', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 900 });
    await page.waitForSelector('.wrapper_slider.hidden-xs .banner_item_bg', { timeout: 30_000 }).catch(() => {});

    const metrics = await page.evaluate(() => {
      const promo = document.querySelector('.top-header')?.getBoundingClientRect();
      const headerContent = document.querySelector('.header-content')?.getBoundingClientRect();
      const nav = document.querySelector('.header-control.header-nav');
      const vmenu = document.querySelector('.our_categories, .awa-vmenu-trigger')?.getBoundingClientRect();
      const siteHeader = document.querySelector('.awa-site-header')?.getBoundingClientRect();
      const hero = document.querySelector('.wrapper_slider.hidden-xs')?.getBoundingClientRect();

      return {
        promoWidth: promo?.width ?? 0,
        headerContentWidth: headerContent?.width ?? 0,
        navHeight: nav?.getBoundingClientRect().height ?? 0,
        navOverflow: nav ? getComputedStyle(nav).overflow : '',
        vmenuHeight: vmenu?.height ?? 0,
        siteHeaderBottom: siteHeader?.bottom ?? 0,
        heroTop: hero?.top ?? 0,
      };
    });

    expect(metrics.promoWidth).toBeGreaterThanOrEqual(metrics.headerContentWidth * 0.95);
    expect(metrics.navOverflow).toBe('visible');
    expect(metrics.navHeight).toBeGreaterThanOrEqual(44);
    expect(metrics.vmenuHeight).toBeGreaterThanOrEqual(40);
    if (metrics.heroTop > 0) {
      expect(metrics.heroTop).toBeGreaterThanOrEqual(metrics.siteHeaderBottom - 4);
    }
  });

  test('align-grid e density-grid carregam versões canônicas atuais', async ({ page }) => {
    const hrefs = await page.evaluate(() =>
      [...document.querySelectorAll('link[rel="stylesheet"]')]
        .map((l) => (l as HTMLLinkElement).href)
        .filter((h) => /align-grid-terminal|home-density-grid/.test(h)),
    );

    expect(hrefs.some((h) => h.includes('awa-align-grid-terminal-2026-06-11.min.css') && h.includes('20260619-search-impeccable-v9-25'))).toBe(true);
    expect(hrefs.some((h) => h.includes('awa-home-density-grid-20260611.min.css') && h.includes('20260618-corp-grid'))).toBe(true);
  });
});
