import { test, expect } from '@playwright/test';
import { navigateTo, collectConsoleErrors, filterCriticalJsErrors, checkOverflow } from '../../helpers/deep-audit.helpers';

const CHECKOUT = 'https://awamotos.com/checkout/';

test.describe('Smoke — Checkout', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, CHECKOUT);
    if (!ok) test.skip();
  });

  test('01 — sem erro 500 (P0)', async ({ page }) => {
    const res = await page.request.get(CHECKOUT).catch(() => null);
    if (res) expect(res.status()).toBeLessThan(500);
  });

  test('02 — sem tela branca (P0)', async ({ page }) => {
    const url = page.url();
    if (url.includes('/cart/')) { console.info('[INFO] Redir para carrinho vazio'); return; }
    const content = page.locator('.opc-wrapper, #checkoutSteps, .checkout-container, .cart-empty, .page-title').first();
    await expect(content).toBeVisible({ timeout: 20000 });
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