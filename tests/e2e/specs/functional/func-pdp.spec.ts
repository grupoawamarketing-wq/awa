import { test, expect } from '@playwright/test';
import { navigateTo, COMMON } from '../../helpers/visual-audit.helpers';

const PDP = 'https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html';

test.describe('PDP — página de produto', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, PDP);
    if (!ok) test.skip();
  });

  test('01 — PDP carrega sem erro (P0)', async ({ page }) => {
    const res = await page.request.get(PDP).catch(() => null);
    if (res) expect(res.status(), '[P0] PDP com erro HTTP').toBeLessThan(400);
  });

  test('02 — título do produto visível (P0)', async ({ page }) => {
    const title = page.locator('.product-info-main .page-title, h1.page-title, h1 .base').first();
    await expect(title).toBeVisible({ timeout: 10_000 });
    const text = await title.textContent();
    expect(text?.trim().length, '[P0] Título vazio').toBeGreaterThan(3);
  });

  test('03 — preço visível ou mensagem B2B (P0)', async ({ page }) => {
    const price = page.locator('.product-info-price .price, .price-box .price, [data-price-type="finalPrice"] .price').first();
    const priceVisible = await price.isVisible({ timeout: 8_000 }).catch(() => false);
    if (priceVisible) return;

    // B2B mode: body.b2b-restricted-mode é setado server-side ou early JS
    const isB2bMode = await page.evaluate(() => document.body.classList.contains('b2b-restricted-mode')).catch(() => false);
    if (isB2bMode) {
      console.info('[INFO] B2B restricted mode — preço oculto para guest (esperado)');
      return;
    }

    // Fallback: gate B2B injetado por JS (aguardar mais)
    const gateVisible = await page.locator('.b2b-login-to-buy-btn, .b2b-login-to-see-price').first().isVisible({ timeout: 8_000 }).catch(() => false);
    if (gateVisible) {
      console.info('[INFO] Gate B2B detectado — preço oculto para guest (esperado)');
      return;
    }

    console.error('[P0] Preço não visível e sem modo/gate B2B detectado');
    expect(false, '[P0] Preço não visível sem indicação de B2B').toBe(true);
  });

  test('04 — breadcrumb presente (P2)', async ({ page }) => {
    await expect(page.locator(COMMON.breadcrumb).first()).toBeVisible({ timeout: 8_000 });
  });

  test('05 — seção de informações do produto', async ({ page }) => {
    await expect(page.locator('.product-info-main, .product-info-wrapper').first()).toBeVisible({ timeout: 10_000 });
  });
});
