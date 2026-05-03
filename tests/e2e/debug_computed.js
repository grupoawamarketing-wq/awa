const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const el = document.querySelector('.awa-header-account-prompt__line2');
    if (!el) return null;
    
    return {
      flexDir: window.getComputedStyle(el).flexDirection,
      flexWrap: window.getComputedStyle(el).flexWrap
    };
  });

  console.log(data);
  await browser.close();
})();
