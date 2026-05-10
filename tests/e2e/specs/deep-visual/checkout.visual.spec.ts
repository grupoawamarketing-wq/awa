import { test, expect } from '@playwright/test';
import { navigateTo, checkOverflow } from '../../helpers/deep-audit.helpers';

const CHECKOUT = 'https://awamotos.com/checkout/';

test.describe('Visual — Checkout', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, CHECKOUT);
    if (!ok) test.skip();
  });

  test('01 — screenshot', async ({ page }) => {
    await expect(page).toHaveScreenshot('checkout.png', {
      maxDiffPixelRatio: 0.06, animations: 'disabled',
    });
  });

  test('02 — sem overlay bloqueando', async ({ page }) => {
    const overlay = page.locator('.loading-mask:visible, .modal-popup:visible').first();
    const vis = await overlay.isVisible({ timeout: 3000 }).catch(() => false);
    if (vis) console.warn('[P1] Overlay bloqueando checkout');
    expect(vis).toBe(false);
  });

  test('03 — sem overflow', async ({ page }) => {
    const { hasOverflow } = await checkOverflow(page);
    expect(hasOverflow).toBe(false);
  });
});