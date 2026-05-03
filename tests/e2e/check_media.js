const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const el = document.querySelector('.awa-main-header__inner.wp-header');
    if (!el) return null;
    
    let results = [];
    const walkRules = (rules, mediaText) => {
      for (const rule of rules) {
        if (rule.type === 4) { // Media
          walkRules(rule.cssRules, rule.media.mediaText);
        } else if (rule.selectorText && el.matches(rule.selectorText)) {
          if (rule.style && rule.style.gridTemplateColumns) {
            results.push({ media: mediaText, selector: rule.selectorText, val: rule.style.getPropertyValue('grid-template-columns') });
          }
        }
      }
    };

    for (const sheet of Array.from(document.styleSheets)) {
      try {
        walkRules(Array.from(sheet.cssRules || []), 'none');
      } catch(e) {}
    }
    return results;
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
