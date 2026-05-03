const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  
  const innerHtml = await page.evaluate(() => {
    const inner = document.querySelector('.awa-main-header__inner');
    const search = document.querySelector('.awa-header-search-col');
    if (!inner) return 'Not found';
    
    const csInner = window.getComputedStyle(inner);
    const csSearch = window.getComputedStyle(search);
    
    return `
      Inner Display: ${csInner.display}
      Inner GTC: ${csInner.gridTemplateColumns}
      Search Display: ${csSearch.display}
      Search Width: ${csSearch.width}
      Search Pos: ${csSearch.position}
      Search Flex: ${csSearch.flex}
    `;
  });
  
  console.log(innerHtml);
  await browser.close();
})();
