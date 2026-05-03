const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const el = document.querySelector('.awa-header-account-prompt__line2');
    if (!el) return null;

    // We can't get exactly which rule won without Chrome DevTools protocol,
    // but we can check if our appended CSS exists in the stylesheets.
    const stylesheets = Array.from(document.styleSheets);
    let found = [];
    for (const sheet of stylesheets) {
      try {
        const rules = Array.from(sheet.cssRules || []);
        for (const rule of rules) {
          if (rule.selectorText && rule.selectorText.includes('awa-header-account-prompt__line2')) {
             found.push({
               selector: rule.selectorText,
               cssText: rule.cssText
             });
          }
        }
      } catch(e) {}
    }

    return {
      flexDirection: window.getComputedStyle(el).flexDirection,
      foundRules: found
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
