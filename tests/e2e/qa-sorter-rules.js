const { chromium } = require('@playwright/test');

(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const page = await b.newPage();
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto('https://awamotos.com/bagageiros.html', { waitUntil: 'domcontentloaded', timeout: 40000 });
  await page.waitForTimeout(2000);
  
  const rules = await page.evaluate(() => {
    const s = document.querySelector('.sorter-options');
    const matched = [];
    for (const sheet of document.styleSheets) {
      try {
        for (const rule of sheet.cssRules) {
          if (rule.selectorText && rule.style &&
              (rule.style.height || rule.style.minHeight) &&
              s.matches(rule.selectorText)) {
            matched.push({
              selector: rule.selectorText.slice(0, 80),
              height: rule.style.height,
              minHeight: rule.style.minHeight,
              priority_h: rule.style.getPropertyPriority('height'),
              priority_mh: rule.style.getPropertyPriority('min-height'),
              sheet: sheet.href ? sheet.href.split('/').pop().slice(0, 50) : 'inline',
            });
          }
        }
      } catch(e) {}
    }
    return matched;
  });
  
  rules.forEach(r => console.log(JSON.stringify(r)));
  await b.close();
})().catch(e => console.error(e.message));
