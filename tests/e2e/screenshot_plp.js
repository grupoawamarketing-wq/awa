const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 1024 });
  await page.goto('https://awamotos.com/catalogsearch/result/?q=moto', { waitUntil: 'networkidle' });
  await page.screenshot({ path: 'plp.png', fullPage: true });
  await browser.close();
  console.log("Screenshot saved to plp.png");
})();
