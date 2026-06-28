import { test, expect } from '@playwright/test';

const BASE_URL = process.env.AWA_BASE_URL || 'https://awamotos.com';

const SNAPSHOT_PAGES = [
  { path: '/', name: 'home', viewport: { width: 390, height: 844 } },
  { path: '/bauletos.html', name: 'plp', viewport: { width: 1366, height: 768 } },
  { path: '/checkout/cart', name: 'cart', viewport: { width: 390, height: 844 } },
];

for (const pageDef of SNAPSHOT_PAGES) {
  test(`snapshot ${pageDef.name}`, async ({ page }) => {
    await page.setViewportSize(pageDef.viewport);
    await page.goto(`${BASE_URL}${pageDef.path}`, {
      waitUntil: 'domcontentloaded',
      timeout: 90000,
    });
    await page.waitForTimeout(2000);
    await expect(page).toHaveScreenshot(`${pageDef.name}.png`, {
      fullPage: false,
      maxDiffPixelRatio: 0.02,
      animations: 'disabled',
    });
  });
}
