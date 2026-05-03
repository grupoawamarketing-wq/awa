const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  
  const rules = await page.evaluate(() => {
    const input = document.querySelector('input#search');
    if (!input) return 'Not found';
    
    let result = "INPUT RULES:\n";
    for (let sheet of document.styleSheets) {
      try {
        for (let rule of sheet.cssRules) {
          if (rule.conditionText) {
            for (let sub of rule.cssRules) {
              if (sub.selectorText && sub.selectorText.includes('input#search')) {
                result += `Media ${rule.conditionText} -> ${sub.selectorText} { ${sub.style.cssText} }\n`;
              }
              if (sub.selectorText && sub.selectorText.includes('#search')) {
                result += `Media ${rule.conditionText} -> ${sub.selectorText} { ${sub.style.cssText} }\n`;
              }
            }
          } else {
             if (rule.selectorText && rule.selectorText.includes('input#search')) {
                result += `Global -> ${rule.selectorText} { ${rule.style.cssText} }\n`;
             }
             if (rule.selectorText && rule.selectorText.includes('#search')) {
                result += `Global -> ${rule.selectorText} { ${rule.style.cssText} }\n`;
             }
          }
        }
      } catch(e) {}
    }
    return result;
  });
  
  const fs = require('fs');
  fs.writeFileSync('search_input_rules.txt', rules);
  console.log('Saved to search_input_rules.txt');
  await browser.close();
})();
