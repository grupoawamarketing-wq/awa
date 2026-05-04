const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'networkidle' });
  const info = await page.evaluate(() => {
    let el = document.querySelector('.awa-header-minicart');
    if (!el) return 'Not found';
    return {
        display: window.getComputedStyle(el).display,
        inlineStyle: el.getAttribute('style'),
        className: el.className
    };
  });
  console.log(JSON.stringify(info));
  await browser.close();
})();
