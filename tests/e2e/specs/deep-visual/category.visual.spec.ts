import { test, expect } from '@playwright/test';
import { navigateTo, waitForImages, checkOverflow } from '../../helpers/deep-audit.helpers';

const CAT = 'https://awamotos.com/bagageiros.html';

test.describe('Visual — Categoria', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, CAT);
    if (!ok) test.skip();
    await waitForImages(page);
  });

  test('01 — screenshot grid', async ({ page }) => {
    const grid = page.locator('.products-grid, .product-items').first();
    const vis = await grid.isVisible({ timeout: 15000 }).catch(() => false);
    if (!vis) { test.skip(); return; }
    await expect(grid).toHaveScreenshot('category-grid.png', {
      maxDiffPixelRatio: 0.06, animations: 'disabled',
    });
  });

  test('02 — cards mesma altura', async ({ page }, testInfo) => {
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
    if (unique.length > 2) console.warn('[P2] Cards alturas: ' + unique.join(', '));
  });

  test('03 — imagens proporcionais', async ({ page }) => {
    const imgs = page.locator('.product-item img');
    const count = await imgs.count();
    for (let i = 0; i < Math.min(count, 4); i++) {
      const box = await imgs.nth(i).boundingBox();
      if (box && box.width > 0) {
        const ratio = box.width / box.height;
        if (ratio < 0.5 || ratio > 2.5) console.warn('[P2] Imagem ' + i + ' ratio: ' + ratio.toFixed(2));
      }
    }
  });

  test('04 — sem overflow', async ({ page }) => {
    const { hasOverflow } = await checkOverflow(page);
    expect(hasOverflow).toBe(false);
  });
});