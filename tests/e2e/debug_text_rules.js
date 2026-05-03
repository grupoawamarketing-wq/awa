const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const el = document.querySelector('.awa-header-account-prompt__text');
    if (!el) return null;

    let rules = [];
    for (const sheet of Array.from(document.styleSheets)) {
      try {
        for (const rule of Array.from(sheet.cssRules || [])) {
          if (rule.selectorText && el.matches(rule.selectorText)) {
            rules.push(rule.cssText);
          }
        }
      } catch(e) {}
    }
    return rules;
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
