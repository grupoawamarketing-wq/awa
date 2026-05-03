const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/catalogsearch/result/?q=moto', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const productsWrapper = document.querySelector('.products-grid, .products-list, .products.wrapper');
    return productsWrapper ? productsWrapper.innerHTML.substring(0, 2000) : "No wrapper";
  });
  
  console.log(layoutInfo);
  await browser.close();
})();
