const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1'
  });
  const page = await context.newPage();
  
  await page.goto('https://awamotos.com/', { waitUntil: 'networkidle' });

  const info = await page.evaluate(() => {
    let el = document.querySelector('.minicart-wrapper');
    if (!el) return 'Not found';
    return el.outerHTML;
  });
  
  console.log(info);
  await browser.close();
})();
