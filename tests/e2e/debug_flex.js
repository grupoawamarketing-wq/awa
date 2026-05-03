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
      display: window.getComputedStyle(el).display,
      flexDirection: window.getComputedStyle(el).flexDirection,
      html: el.outerHTML
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
