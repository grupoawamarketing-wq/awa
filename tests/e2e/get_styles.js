const { chromium } = require('@playwright/test');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/b2b/register/');
  await page.waitForTimeout(2000);
  
  const rules = await page.evaluate(() => {
    let result = [];
    for (let sheet of document.styleSheets) {
      try {
        for (let rule of sheet.cssRules || []) {
          if (rule.selectorText && (rule.selectorText.includes('.register-header') || rule.selectorText.includes('.b2b-register-container'))) {
            result.push(rule.cssText);
          }
        }
      } catch(e) {}
    }
    return result;
  });
  
  console.log(rules.join('\n'));
  await browser.close();
})();
