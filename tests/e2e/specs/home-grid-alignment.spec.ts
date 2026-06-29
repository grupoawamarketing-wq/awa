import { test, expect } from '@playwright/test';

const HOME_URL = process.env.HOME_SMOKE_URL || 'https://awamotos.com/';

test.describe('Home grid alignment smoke', () => {
  test.describe.configure({ timeout: 90_000 });

  test.beforeEach(async ({ page }) => {
    await page.goto(`${HOME_URL}?grid-smoke=${Date.now()}`, {
      waitUntil: 'domcontentloaded',
      timeout: 90_000,
    });
    await page.waitForSelector('.awa-carousel-section', { timeout: 60_000 });
    await page.waitForTimeout(2500);
  });

  test('no horizontal overflow on mobile viewport', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const overflow = await page.evaluate(() => ({
      doc: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      body: document.body.scrollWidth - document.body.clientWidth,
    }));
    expect(overflow.doc).toBeLessThanOrEqual(1);
    expect(overflow.body).toBeLessThanOrEqual(1);
  });

  test('visible owl carousel cards have near-equal heights', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.waitForTimeout(1500);

    const heights = await page.evaluate(() => {
      const cards = Array.from(document.querySelectorAll('.awa-carousel-section .item-product.awa-carousel-card-slot'))
        .filter((el) => {
          const rect = (el as HTMLElement).getBoundingClientRect();
          return rect.width > 40 && rect.height > 40;
        })
        .slice(0, 4)
        .map((el) => Math.round((el as HTMLElement).getBoundingClientRect().height));
      return cards;
    });

    expect(heights.length).toBeGreaterThanOrEqual(2);
    const max = Math.max(...heights);
    const min = Math.min(...heights);
    expect(max - min).toBeLessThanOrEqual(10);
  });

  test('align grid terminal CSS is loaded', async ({ page }) => {
    await page.evaluate(() => {
      if (typeof (window as Window & { __awaApplyGatedCSS?: () => void }).__awaApplyGatedCSS === 'function') {
        (window as Window & { __awaApplyGatedCSS?: () => void }).__awaApplyGatedCSS!();
      }
      document.querySelectorAll('link[rel="stylesheet"][media="print"]').forEach((link) => {
        (link as HTMLLinkElement).media = 'all';
      });
    });
    await page.waitForTimeout(1500);

    const tokenApplied = await page.evaluate(() => {
      const body = document.body;
      const shellMax = getComputedStyle(body).getPropertyValue('--awa-grid-shell-max').trim();
      const hasLink = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).some((link) =>
        (link as HTMLLinkElement).href.includes('awa-align-grid-terminal-2026-06-11')
      );
      return hasLink || shellMax.includes('1440') || shellMax.includes('1280') || shellMax.includes('min(100%');
    });
    expect(tokenApplied).toBe(true);
  });
});
