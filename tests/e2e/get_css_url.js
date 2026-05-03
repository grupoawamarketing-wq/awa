const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  const urls = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(l => l.href).filter(h => h.includes('awa-visual-bugfix-2026-04-30'));
  });
  console.log(urls[0]);
  await browser.close();
})();
