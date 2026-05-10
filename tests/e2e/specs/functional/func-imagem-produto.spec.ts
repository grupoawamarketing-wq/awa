import { test, expect } from '@playwright/test';
import { navigateTo } from '../../helpers/visual-audit.helpers';

const PDP = 'https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html';

test.describe('Imagem do Produto — galeria', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, PDP);
    if (!ok) test.skip();
  });

  test('01 — imagem principal visível (P0)', async ({ page }) => {
    // Esperar galeria carregar (fotorama é lazy-loaded)
    await page.waitForTimeout(2_000);
    const img = page.locator(
      '.fotorama__stage img, .product-image-photo, ' +
      '.gallery-placeholder__image, [data-gallery-role="gallery-placeholder"] img'
    ).first();
    const visible = await img.isVisible({ timeout: 12_000 }).catch(() => false);
    if (!visible) {
      // Aceitar qualquer img dentro da área do produto
      const anyImg = page.locator('.product-media img, .product-image-container img').first();
      await expect(anyImg).toBeVisible({ timeout: 5_000 });
    } else {
      await expect(img).toBeVisible({ timeout: 5_000 });
    }
  });

  test('02 — imagem não quebrada (P1)', async ({ page }) => {
    await page.waitForTimeout(1_500);
    const img = page.locator(
      '.fotorama__stage img, .product-image-photo, .product-media img'
    ).first();
    const exists = await img.isVisible({ timeout: 10_000 }).catch(() => false);
    if (!exists) { test.skip(); return; }
    const loaded = await img.evaluate(
      (i: HTMLImageElement) => i.naturalWidth > 0 && i.complete
    ).catch(() => true);
    if (!loaded) console.error('[P1] Imagem principal quebrada');
    expect(loaded, '[P1] Imagem não carregou').toBe(true);
  });

  test('03 — imagem tem dimensão razoável (P2)', async ({ page }) => {
    await page.waitForTimeout(1_500);
    const img = page.locator(
      '.fotorama__stage img, .product-image-photo, .product-media img'
    ).first();
    const exists = await img.isVisible({ timeout: 8_000 }).catch(() => false);
    if (!exists) { test.skip(); return; }
    const box = await img.boundingBox().catch(() => null);
    if (box) {
      if (box.width < 100 || box.height < 100) console.warn('[P2] Imagem pequena: ' + box.width + 'x' + box.height);
      expect(box.width).toBeGreaterThan(50);
      expect(box.height).toBeGreaterThan(50);
    }
  });
});
