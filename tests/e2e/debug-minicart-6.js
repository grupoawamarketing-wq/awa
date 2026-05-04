const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'networkidle' });
  const hasMyCSS = await page.evaluate(() => {
    let found = false;
    for (let sheet of document.styleSheets) {
      try {
        for (let rule of sheet.cssRules) {
          if (rule.cssText && rule.cssText.includes('MOBILE MINICART VISIBILITY FIX')) {
            found = true;
          }
          if (rule.type === CSSRule.MEDIA_RULE) {
            for (let inner of rule.cssRules) {
                if (inner.selectorText && inner.selectorText.includes('data-awa-header-cart')) {
                    if (inner.style.display === 'inline-flex') return 'Found my rule!';
                }
            }
          }
        }
      } catch(e) {}
    }
    return found ? 'Found comment but not rule' : 'Not found anything';
  });
  console.log(hasMyCSS);
  await browser.close();
})();
