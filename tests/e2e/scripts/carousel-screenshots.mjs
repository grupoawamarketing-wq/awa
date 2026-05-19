import { chromium } from '@playwright/test';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
await page.goto('https://awamotos.com/?v=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(5000);
const cookie = page.locator('#awa-cookie-accept, .awa-cookie-banner__btn--accept').first();
if (await cookie.isVisible({ timeout: 2000 }).catch(() => false)) {
  await cookie.click({ force: true });
}
await page.evaluate(() => {
  document.querySelectorAll('link[rel="stylesheet"]').forEach((l) => { l.media = 'all'; });
});
await page.waitForTimeout(3000);
await page.locator('.top-home-content--category-carousel').scrollIntoViewIfNeeded();
await page.waitForTimeout(1000);
await page.screenshot({ path: '/tmp/carousel-categories.png' });
await page.locator('.rokan-bestseller').scrollIntoViewIfNeeded();
await page.waitForTimeout(2000);
await page.screenshot({ path: '/tmp/carousel-products.png' });
console.log('screenshots ok');
await browser.close();
