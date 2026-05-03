const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  
  const cssInfo = await page.evaluate(() => {
    const row = document.querySelector('.awa-header-primary-row');
    if (!row) return 'Row not found';
    const computed = window.getComputedStyle(row);
    
    const searchCol = document.querySelector('.awa-header-search-col');
    const searchComputed = window.getComputedStyle(searchCol);

    const inner = document.querySelector('.awa-main-header__inner');
    const innerComputed = window.getComputedStyle(inner);
    
    return `
      Row display: ${computed.display}
      Row width: ${computed.width}
      Inner display: ${innerComputed.display}
      Inner grid-template-areas: ${innerComputed.gridTemplateAreas}
      Search display: ${searchComputed.display}
      Search width: ${searchComputed.width}
    `;
  });
  
  console.log(cssInfo);
  await browser.close();
})();
