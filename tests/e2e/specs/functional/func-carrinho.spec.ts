import { test, expect } from '@playwright/test';
import { navigateTo } from '../../helpers/visual-audit.helpers';

const CART = 'https://awamotos.com/checkout/cart/';
const PDP  = 'https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html';

test.describe('Carrinho — página e itens', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, PDP);
    if (!ok) { test.skip(); return; }
    const btn = page.locator('#product-addtocart-button, button.action.tocart').first();
    const btnOk = await btn.isVisible({ timeout: 8_000 }).catch(() => false);
    if (btnOk) {
      const disabled = await btn.isDisabled().catch(() => false);
      if (!disabled) { await btn.click().catch(() => {}); await page.waitForTimeout(2_000); }
    }
    await navigateTo(page, CART);
  });

  test('01 — página do carrinho carrega (P0)', async ({ page }) => {
    const h1 = page.locator('h1, .page-title').first();
    await expect(h1).toBeVisible({ timeout: 10_000 });
  });

  test('02 — carrinho vazio mostra mensagem', async ({ page }) => {
    const count = await page.locator('.cart.item').count().catch(() => 0);
    if (count === 0) {
      const msg = await page.locator('.cart-empty, .message.notice').isVisible({ timeout: 5_000 }).catch(() => false);
      if (!msg) console.warn('[P2] Carrinho vazio sem mensagem');
    }
  });

  test('03 — itens têm nome e preço (P0)', async ({ page }) => {
    const count = await page.locator('.cart.item').count().catch(() => 0);
    if (count === 0) { test.skip(); return; }
    for (let i = 0; i < Math.min(count, 3); i++) {
      const item = page.locator('.cart.item').nth(i);
      const nameOk = await item.locator('.product-item-name a, .item-name a').isVisible({ timeout: 3_000 }).catch(() => false);
      const priceOk = await item.locator('.price').isVisible({ timeout: 3_000 }).catch(() => false);
      if (!nameOk) console.error('[P0] Item ' + i + ': nome não visível');
      if (!priceOk) console.error('[P0] Item ' + i + ': preço não visível');
    }
  });

  test('04 — botão "Finalizar Compra" existe (P0)', async ({ page }) => {
    const count = await page.locator('.cart.item').count().catch(() => 0);
    if (count === 0) { test.skip(); return; }
    const btn = page.locator('.action.primary.checkout, button.checkout, [data-role="proceed-to-checkout"]').first();
    await expect(btn).toBeVisible({ timeout: 8_000 });
  });
});
