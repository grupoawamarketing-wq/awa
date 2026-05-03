const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 1024 });
  await page.goto('https://awamotos.com/catalogsearch/result/?q=moto', { waitUntil: 'networkidle' });
  
  const data = await page.evaluate(() => {
    const title = document.querySelector('h1')?.getBoundingClientRect();
    const bread = document.querySelector('.breadcrumbs li')?.getBoundingClientRect();
    const cols = document.querySelector('.columns');
    const c1 = cols?.children[0]?.getBoundingClientRect();
    return {
      titleLeft: title?.left,
      breadLeft: bread?.left,
      colLeft: c1?.left,
      titleWidth: title?.width
    };
  });
  console.log(data);
  await page.screenshot({ path: 'plp_fixed2.png', fullPage: true });
  await browser.close();
})();
