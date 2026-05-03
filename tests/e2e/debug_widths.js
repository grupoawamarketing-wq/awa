const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  
  const info = await page.evaluate(() => {
    const body = document.body.getBoundingClientRect();
    const inner = document.querySelector('.awa-main-header__inner').getBoundingClientRect();
    const search = document.querySelector('.awa-header-search-col').getBoundingClientRect();
    
    return `
      Body width: ${body.width}
      Inner width: ${inner.width}
      Search width: ${search.width}
    `;
  });
  
  console.log(info);
  await browser.close();
})();
