const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 }
  });
  const page = await context.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  
  await page.waitForTimeout(2000);

  const rects = await page.evaluate(() => {
    const getRect = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return null;
      const rect = el.getBoundingClientRect();
      const computed = window.getComputedStyle(el);
      return {
        selector: sel,
        width: rect.width,
        height: rect.height,
        display: computed.display,
        flexDirection: computed.flexDirection,
        margin: computed.margin,
        padding: computed.padding,
        lineHeight: computed.lineHeight,
        fontSize: computed.fontSize
      };
    };

    return {
      header: getRect('.awa-site-header'),
      accountPrompt: getRect('.awa-header-account-prompt'),
      accountText: getRect('.awa-header-account-prompt__text'),
      accountAction: getRect('.awa-header-account-prompt__action'),
      nav: getRect('.navigation'),
      navUl: getRect('.navigation ul'),
      minicart: getRect('.minicart-wrapper'),
      search: getRect('.block-search')
    };
  });

  console.log(JSON.stringify(rects, null, 2));
  await browser.close();
})();
