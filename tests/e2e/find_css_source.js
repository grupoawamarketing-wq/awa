const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  
  const rules = await page.evaluate(() => {
    const inner = document.querySelector('.awa-main-header__inner');
    const search = document.querySelector('.awa-header-search-col');
    
    let result = "INNER RULES:\n";
    for (let sheet of document.styleSheets) {
      try {
        for (let rule of sheet.cssRules) {
          if (rule.conditionText) { // media query
            if (rule.conditionText.includes('max-width: 991px') || rule.conditionText.includes('max-width: 767px')) {
              for (let sub of rule.cssRules) {
                if (sub.selectorText && sub.selectorText.includes('.awa-main-header__inner')) {
                  result += `Media ${rule.conditionText} -> ${sub.selectorText} { ${sub.style.cssText} }\n`;
                }
                if (sub.selectorText && sub.selectorText.includes('.awa-header-search-col')) {
                  result += `Media ${rule.conditionText} -> ${sub.selectorText} { ${sub.style.cssText} }\n`;
                }
              }
            }
          } else {
             if (rule.selectorText && rule.selectorText.includes('.awa-main-header__inner')) {
                result += `Global -> ${rule.selectorText} { ${rule.style.cssText} }\n`;
             }
             if (rule.selectorText && rule.selectorText.includes('.awa-header-search-col')) {
                result += `Global -> ${rule.selectorText} { ${rule.style.cssText} }\n`;
             }
          }
        }
      } catch(e) {}
    }
    return result;
  });
  
  const fs = require('fs');
  fs.writeFileSync('header_css_source.txt', rules);
  console.log('Saved to header_css_source.txt');
  await browser.close();
})();
