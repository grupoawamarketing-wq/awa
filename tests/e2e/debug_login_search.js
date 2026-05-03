const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/customer/account/login/');
  const matches = await page.evaluate(() => {
    let results = [];
    for(let i=0; i<document.styleSheets.length; i++) {
      try {
        const rules = document.styleSheets[i].cssRules;
        if(rules) {
          for(let j=0; j<rules.length; j++) {
             if(rules[j].cssText && (rules[j].cssText.includes('11.37') || rules[j].cssText.includes('0.8125') || rules[j].cssText.includes('11px'))) {
                 results.push(rules[j].cssText);
             }
          }
        }
      } catch(e) {}
    }
    return results;
  });
  console.log(matches.filter(m => m.includes('input') || m.includes('email') || m.includes('b2b')));
  await browser.close();
})();
