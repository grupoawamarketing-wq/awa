const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const el = document.querySelector('.awa-header-account-prompt__text');
    if (!el) return null;

    const computed = window.getComputedStyle(el);
    return {
      height: computed.height,
      alignSelf: computed.alignSelf,
      flexGrow: computed.flexGrow,
      flexBasis: computed.flexBasis,
      flexShrink: computed.flexShrink,
      margin: computed.margin
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
