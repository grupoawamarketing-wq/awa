const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const items = document.querySelectorAll('.product-grid .item-product');
    if (!items.length) return null;
    
    return Array.from(items).slice(0, 4).map(item => ({
      x: item.getBoundingClientRect().x,
      y: item.getBoundingClientRect().y,
      w: item.getBoundingClientRect().width,
      h: item.getBoundingClientRect().height
    }));
  });
  
  console.log(layoutInfo);
  await browser.close();
})();
