import { test, expect } from '@playwright/test';

test('screenshot awamotos', async ({ page }) => {
  await page.goto('https://awamotos.com');
  await page.screenshot({ path: 'awamotos_desktop.png', fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({ path: 'awamotos_mobile.png', fullPage: true });
});
