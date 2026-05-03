const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const getRect = (selector) => {
      const el = document.querySelector(selector);
      if (!el) return null;
      const rect = el.getBoundingClientRect();
      return {
        selector,
        x: rect.x,
        width: rect.width,
        right: rect.right
      };
    };

    const header = document.querySelector('.header.content') || document.querySelector('.awa-site-header');
    
    return {
      header: { x: header.getBoundingClientRect().x, width: header.getBoundingClientRect().width },
      logo: getRect('.logo'),
      search: getRect('.block-search'),
      accountPrompt: getRect('.awa-header-account-prompt'),
      minicart: getRect('.minicart-wrapper')
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
