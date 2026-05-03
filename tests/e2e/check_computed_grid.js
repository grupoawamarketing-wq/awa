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
    
    // Use the DevTools-like way to find all matching rules for this property
    let rules = [];
    for (const sheet of Array.from(document.styleSheets)) {
      try {
        for (const rule of Array.from(sheet.cssRules || [])) {
          if (rule.selectorText && el.matches(rule.selectorText)) {
            if (rule.style && rule.style.gridTemplateColumns) {
              rules.push({ selector: rule.selectorText, val: rule.style.getPropertyValue('grid-template-columns'), important: rule.style.getPropertyPriority('grid-template-columns') });
            }
          }
        }
      } catch(e) {}
    }
    return {
      computed: window.getComputedStyle(el).gridTemplateColumns,
      rules
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
