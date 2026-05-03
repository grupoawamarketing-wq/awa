const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  
  const info = await page.evaluate(() => {
    const search = document.querySelector('.awa-header-search-col');
    if (!search) return 'Not found';
    
    const csSearch = window.getComputedStyle(search);
    
    return `
      Search grid-area: ${csSearch.gridArea}
      Search grid-row-start: ${csSearch.gridRowStart}
      Search grid-column-start: ${csSearch.gridColumnStart}
    `;
  });
  
  console.log(info);
  await browser.close();
})();
