const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1280, height: 1024 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const grid = document.querySelector('.product-grid');
    if (!grid) return { error: "No grid found" };
    
    return {
      cssDisplay: getComputedStyle(grid).display,
      cssGridTemplateColumns: getComputedStyle(grid).gridTemplateColumns,
      cssGap: getComputedStyle(grid).gap,
      width: grid.offsetWidth,
      firstItemWidth: document.querySelector('.product-grid .item-product') ? document.querySelector('.product-grid .item-product').offsetWidth : null
    };
  });
  
  console.log(JSON.stringify(layoutInfo, null, 2));
  await browser.close();
})();
