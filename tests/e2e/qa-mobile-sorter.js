const { chromium } = require('@playwright/test');

(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const page = await b.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/bagageiros.html', { waitUntil: 'domcontentloaded', timeout: 40000 });
  await page.waitForTimeout(2000);
  
  const d = await page.evaluate(() => {
    const s = document.querySelector('.sorter select, .sorter-options');
    if (!s) return { found: false };
    const cs = window.getComputedStyle(s);
    // Get all matching rules with height/minHeight
    const rules = [];
    for (const sheet of document.styleSheets) {
      try {
        for (const rule of sheet.cssRules || []) {
          // Check media rules
          if (rule.cssRules) {
            for (const inner of rule.cssRules) {
              if (inner.selectorText && inner.style &&
                  (inner.style.height || inner.style.minHeight) &&
                  s.matches(inner.selectorText)) {
                rules.push({
                  media: rule.conditionText || 'media',
                  selector: inner.selectorText.slice(0, 80),
                  height: inner.style.height,
                  minHeight: inner.style.minHeight,
                  ph: inner.style.getPropertyPriority('height'),
                  pmh: inner.style.getPropertyPriority('min-height'),
                  sheet: sheet.href ? sheet.href.split('/').pop().slice(0, 40) : 'inline',
                });
              }
            }
          }
        }
      } catch(e) {}
    }
    return {
      computedH: cs.height,
      computedMH: cs.minHeight,
      mediaRules: rules,
    };
  });
  
  console.log('Computed height:', d.computedH, 'minHeight:', d.computedMH);
  console.log('Media-scoped rules affecting sorter:');
  d.mediaRules.forEach(r => console.log(JSON.stringify(r)));
  await b.close();
})().catch(e => console.error(e.message));
