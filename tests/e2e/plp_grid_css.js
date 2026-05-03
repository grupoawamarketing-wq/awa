const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/catalogsearch/result/?q=moto', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const grid = document.querySelector('.product-grid');
    if (!grid) return { error: "No grid found" };
    
    return {
      cssText: grid.style.cssText,
      justifyContent: getComputedStyle(grid).justifyContent,
      padding: getComputedStyle(grid).padding,
      margin: getComputedStyle(grid).margin,
      width: grid.offsetWidth
    };
  });
  
  console.log(JSON.stringify(layoutInfo, null, 2));
  await browser.close();
})();
