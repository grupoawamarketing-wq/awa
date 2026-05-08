const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('https://awamotos.com', { waitUntil: 'networkidle' });
  await page.screenshot({ path: 'homepage_screenshot.png', fullPage: true });
  await browser.close();
})();
