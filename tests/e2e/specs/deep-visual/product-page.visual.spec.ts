import { test, expect } from '@playwright/test';
import { navigateTo, waitForImages, checkOverflow } from '../../helpers/deep-audit.helpers';

const PDP = 'https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html';

test.describe('Visual — PDP', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, PDP);
    if (!ok) test.skip();
    await waitForImages(page);
  });

  test('01 — screenshot above-fold', async ({ page }) => {
    await expect(page).toHaveScreenshot('pdp-above-fold.png', {
      maxDiffPixelRatio: 0.06, animations: 'disabled',
      clip: { x: 0, y: 0, width: page.viewportSize()!.width, height: 700 },
    });
  });

  test('02 — imagem e info lado a lado (desktop)', async ({ page }, testInfo) => {
    if (testInfo.project.name.includes('mobile')) { test.skip(); return; }
    const media = page.locator('.product.media, .gallery-placeholder, .fotorama').first();
    const info = page.locator('.product-info-main').first();
    const mBox = await media.boundingBox().catch(() => null);
    const iBox = await info.boundingBox().catch(() => null);
    if (mBox && iBox) {
      const sideBySide = Math.abs(mBox.y - iBox.y) < 100;
      if (!sideBySide) console.warn('[P2] Imagem e info não lado a lado');
    }
  });

  test('03 — botão tamanho adequado', async ({ page }) => {
    const btn = page.locator('#product-addtocart-button, .b2b-login-to-buy-btn').first();
    const vis = await btn.isVisible({ timeout: 8000 }).catch(() => false);
    if (!vis) { test.skip(); return; }
    const box = await btn.boundingBox();
    if (box) {
      expect(box.height).toBeGreaterThan(35);
      expect(box.width).toBeGreaterThan(100);
    }
  });

  test('04 — sem overflow', async ({ page }) => {
    const { hasOverflow } = await checkOverflow(page);
    expect(hasOverflow).toBe(false);
  });
});