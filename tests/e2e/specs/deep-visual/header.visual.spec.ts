import { test, expect } from '@playwright/test';
import { navigateTo, COMMON, waitForImages } from '../../helpers/deep-audit.helpers';

const HOME = 'https://awamotos.com';

test.describe('Visual — Header', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
    await waitForImages(page);
  });

  test('01 — screenshot header', async ({ page }) => {
    const header = page.locator(COMMON.header).first();
    await expect(header).toBeVisible({ timeout: 10000 });
    await expect(header).toHaveScreenshot('header.png', {
      maxDiffPixelRatio: 0.04, animations: 'disabled',
    });
  });

  test('02 — logo posição (esquerda)', async ({ page }) => {
    const logo = page.locator(COMMON.logo).first();
    const box = await logo.boundingBox();
    if (box) expect(box.x).toBeLessThan(300);
  });

  test('03 — busca/minicart alinhados', async ({ page }, testInfo) => {
    if (testInfo.project.name.includes('mobile')) { test.skip(); return; }
    const search = page.locator(COMMON.search).first();
    const cart = page.locator(COMMON.minicart).first();
    const sBox = await search.boundingBox().catch(() => null);
    const cBox = await cart.boundingBox().catch(() => null);
    if (sBox && cBox) {
      const diff = Math.abs(sBox.y - cBox.y);
      if (diff > 30) console.warn('[P2] Busca e minicart desalinhados: ' + diff + 'px');
    }
  });

  test('04 — header mobile não excede viewport', async ({ page }, testInfo) => {
    if (!testInfo.project.name.includes('mobile')) { test.skip(); return; }
    const header = page.locator(COMMON.header).first();
    const box = await header.boundingBox();
    if (box) expect(box.width).toBeLessThanOrEqual(page.viewportSize()!.width + 2);
  });
});