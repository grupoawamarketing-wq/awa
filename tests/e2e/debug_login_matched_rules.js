const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/customer/account/login/');
  const matched = await page.evaluate(() => {
    const el = document.querySelector('#b2b-email');
    const matchedRules = [];
    for(let i=0; i<document.styleSheets.length; i++) {
      try {
        const rules = document.styleSheets[i].cssRules;
        if(rules) {
          for(let j=0; j<rules.length; j++) {
            if(rules[j].selectorText && el.matches(rules[j].selectorText)) {
              matchedRules.push(rules[j].cssText);
            }
          }
        }
      } catch(e) {}
    }
    return matchedRules;
  });
  console.log(matched.filter(r => r.includes('font-size') || r.includes('height')));
  await browser.close();
})();
