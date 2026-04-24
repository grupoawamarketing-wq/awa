import { test, expect } from '@playwright/test';

test('PDP Debug', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto('https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html', { waitUntil: 'networkidle' });
  await page.screenshot({ path: '/tmp/pdp_desktop_1280.png', fullPage: true });

  await page.setViewportSize({ width: 500, height: 800 });
  await page.screenshot({ path: '/tmp/pdp_mobile_500.png', fullPage: true });

  await page.setViewportSize({ width: 375, height: 812 });
  await page.screenshot({ path: '/tmp/pdp_mobile_375.png', fullPage: true });
});
