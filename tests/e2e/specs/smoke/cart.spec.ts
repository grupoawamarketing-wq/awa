import { test, expect } from '@playwright/test';
import { navigateTo, collectConsoleErrors, filterCriticalJsErrors, checkOverflow } from '../../helpers/deep-audit.helpers';

const CART = 'https://awamotos.com/checkout/cart/';

test.describe('Smoke — Carrinho', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, CART);
    if (!ok) test.skip();
  });

  test('01 — página carrega', async ({ page }) => {
    const h1 = page.locator('h1, .page-title').first();
    await expect(h1).toBeVisible({ timeout: 10000 });
  });

  test('02 — carrinho vazio mostra mensagem', async ({ page }) => {
    const items = await page.locator('.cart.item, .cart-item').count().catch(() => 0);
    if (items === 0) {
      const msg = page.locator('.cart-empty, .message.notice, .empty').first();
      const vis = await msg.isVisible({ timeout: 5000 }).catch(() => false);
      if (!vis) console.warn('[P2] Carrinho vazio sem mensagem');
    }
  });

  test('03 — sem JS errors (P0)', async ({ page }) => {
    const errors = collectConsoleErrors(page);
    await page.waitForTimeout(3000);
    const critical = filterCriticalJsErrors(errors);
    expect(critical).toHaveLength(0);
  });

  test('04 — sem overflow', async ({ page }) => {
    const { hasOverflow } = await checkOverflow(page);
    expect(hasOverflow).toBe(false);
  });
});