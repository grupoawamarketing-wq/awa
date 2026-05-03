const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 1024 });
  await page.goto('https://awamotos.com/catalogsearch/result/?q=moto', { waitUntil: 'networkidle' });
  const data = await page.evaluate(() => {
    const cols = document.querySelector('.columns');
    const rules = window.getMatchedCSSRules ? window.getMatchedCSSRules(cols) : [];
    return {
      cssText: cols.style.cssText,
      marginLeft: getComputedStyle(cols).marginLeft,
      marginRight: getComputedStyle(cols).marginRight,
      width: getComputedStyle(cols).width,
      boxSizing: getComputedStyle(cols).boxSizing
    };
  });
  console.log(data);
  await browser.close();
})();
