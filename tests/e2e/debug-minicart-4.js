const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1'
  });
  const page = await context.newPage();
  
  await page.goto('https://awamotos.com/', { waitUntil: 'networkidle' });

  const info = await page.evaluate(() => {
    let el = document.querySelector('.minicart-wrapper');
    if (!el) return 'Not found minicart-wrapper';

    let hiddenEl = null;
    let curr = el;
    while(curr) {
        if(window.getComputedStyle(curr).display === 'none') {
            hiddenEl = curr;
            break;
        }
        curr = curr.parentElement;
    }
    
    if (!hiddenEl) return 'Not hidden by display:none';

    let matchedRules = [];
    for (let sheet of document.styleSheets) {
      try {
        for (let rule of sheet.cssRules) {
          if (rule.type === CSSRule.STYLE_RULE) {
            if (hiddenEl.matches(rule.selectorText)) {
              if (rule.style.display === 'none' || rule.style.display.includes('none')) {
                matchedRules.push(rule.cssText);
              }
            }
          } else if (rule.type === CSSRule.MEDIA_RULE) {
            if (window.matchMedia(rule.conditionText).matches) {
              for (let inner of rule.cssRules) {
                if (inner.type === CSSRule.STYLE_RULE && hiddenEl.matches(inner.selectorText)) {
                  if (inner.style.display === 'none' || inner.style.display.includes('none')) {
                    matchedRules.push(`@media ${rule.conditionText} { ${inner.cssText} }`);
                  }
                }
              }
            }
          }
        }
      } catch(e) {}
    }
    return {
        tag: hiddenEl.tagName,
        className: hiddenEl.className,
        rules: matchedRules
    };
  });
  
  console.log("Matched hiding rules:", JSON.stringify(info, null, 2));
  await browser.close();
})();
