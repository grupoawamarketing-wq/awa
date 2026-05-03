const { chromium } = require('@playwright/test');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1300, height: 1080 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  await page.screenshot({ path: '/tmp/screenshot_desktop.png', fullPage: true });
  await page.setViewportSize({ width: 375, height: 812 });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: '/tmp/screenshot_mobile.png', fullPage: true });
  await browser.close();
})();
