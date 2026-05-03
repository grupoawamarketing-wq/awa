const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const grid = document.querySelector('.product-grid');
    if (!grid) return null;
    
    const items = document.querySelectorAll('.product-grid .item-product');
    return {
      gridDisplay: getComputedStyle(grid).display,
      gridTemplateColumns: getComputedStyle(grid).gridTemplateColumns,
      itemCount: items.length,
      firstItemDisplay: items.length ? getComputedStyle(items[0]).display : null,
      firstItemWidth: items.length ? items[0].offsetWidth : null,
      firstItemHeight: items.length ? items[0].offsetHeight : null
    };
  });
  
  console.log(layoutInfo);
  await browser.close();
})();
