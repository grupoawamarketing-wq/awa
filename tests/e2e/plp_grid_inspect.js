const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1280, height: 1024 });
  await page.goto('https://awamotos.com/catalogsearch/result/?q=moto', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const grid = document.querySelector('.product-grid');
    if (!grid) return { error: "No grid found" };
    
    const items = document.querySelectorAll('.product-grid .item-product');
    
    return {
      gridDisplay: getComputedStyle(grid).display,
      gridTemplateColumns: getComputedStyle(grid).gridTemplateColumns,
      gridGap: getComputedStyle(grid).gap,
      itemsCount: items.length,
      firstItem: items.length > 0 ? {
        width: items[0].offsetWidth,
        className: items[0].className,
        rect: items[0].getBoundingClientRect()
      } : null,
      secondItem: items.length > 1 ? {
        width: items[1].offsetWidth,
        rect: items[1].getBoundingClientRect()
      } : null,
      thirdItem: items.length > 2 ? {
        width: items[2].offsetWidth,
        rect: items[2].getBoundingClientRect()
      } : null,
      fourthItem: items.length > 3 ? {
        width: items[3].offsetWidth,
        rect: items[3].getBoundingClientRect()
      } : null
    };
  });
  
  console.log(JSON.stringify(layoutInfo, null, 2));
  await browser.close();
})();
