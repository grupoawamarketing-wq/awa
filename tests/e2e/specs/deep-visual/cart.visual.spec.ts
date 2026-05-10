import { test, expect } from '@playwright/test';
import { navigateTo, checkOverflow } from '../../helpers/deep-audit.helpers';

const CART = 'https://awamotos.com/checkout/cart/';

test.describe('Visual — Carrinho', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, CART);
    if (!ok) test.skip();
  });

  test('01 — screenshot', async ({ page }) => {
    await expect(page).toHaveScreenshot('cart.png', {
      maxDiffPixelRatio: 0.06, animations: 'disabled',
    });
  });

  test('02 — sem overflow', async ({ page }) => {
    const { hasOverflow } = await checkOverflow(page);
    expect(hasOverflow).toBe(false);
  });
});