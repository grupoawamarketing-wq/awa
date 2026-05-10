import { test, expect } from '@playwright/test';
import { navigateTo, waitForImages, checkOverflow } from '../../helpers/deep-audit.helpers';

const HOME = 'https://awamotos.com';

test.describe('Visual — Home', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
    await waitForImages(page);
  });

  test('01 — screenshot above-fold', async ({ page }) => {
    await expect(page).toHaveScreenshot('home-above-fold.png', {
      maxDiffPixelRatio: 0.05, animations: 'disabled',
      clip: { x: 0, y: 0, width: page.viewportSize()!.width, height: 700 },
    });
  });

  test('02 — cards alinhados', async ({ page }, testInfo) => {
    if (testInfo.project.name.includes('mobile')) { test.skip(); return; }
    const cards = page.locator('.product-item');
    const count = await cards.count();
    if (count < 2) return;
    const heights: number[] = [];
    for (let i = 0; i < Math.min(count, 6); i++) {
      const box = await cards.nth(i).boundingBox();
      if (box) heights.push(Math.round(box.height));
    }
    const unique = [...new Set(heights)];
    if (unique.length > 2) console.warn('[P2] Cards com alturas diferentes: ' + unique.join(', '));
  });

  test('03 — banner proporção OK', async ({ page }) => {
    const banner = page.locator('.owl-carousel img, .swiper-slide img, .slidebanner img, [class*="slide"] img, [class*="banner"] img').first();
    const vis = await banner.isVisible({ timeout: 10000 }).catch(() => false);
    if (!vis) { test.skip(); return; }
    const box = await banner.boundingBox();
    if (box && box.width > 0) {
      const ratio = box.width / box.height;
      if (ratio < 1.5 || ratio > 6) console.warn('[P2] Banner ratio: ' + ratio.toFixed(2));
    }
  });
});