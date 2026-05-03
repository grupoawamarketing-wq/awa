const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const getRectAndStyle = (selector) => {
      const el = document.querySelector(selector);
      if (!el) return null;
      const rect = el.getBoundingClientRect();
      const style = window.getComputedStyle(el);
      return {
        selector,
        y: rect.y,
        height: rect.height,
        display: style.display,
        alignItems: style.alignItems,
        marginTop: style.marginTop,
        html: el.innerHTML.substring(0, 50).replace(/\s+/g, ' ')
      };
    };

    return {
      headerContent: getRectAndStyle('.header.content'),
      logo: getRectAndStyle('.logo'),
      search: getRectAndStyle('.block-search'),
      accountPrompt: getRectAndStyle('.awa-header-account-prompt'),
      minicart: getRectAndStyle('.minicart-wrapper'),
      verticalMenu: getRectAndStyle('[data-role="awa-vertical-menu-trigger"]')
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
