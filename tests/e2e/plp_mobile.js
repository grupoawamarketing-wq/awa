const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const grid = document.querySelector('.product-grid');
    if (!grid) return { error: "No grid found" };
    
    return {
      width: grid.offsetWidth,
      gridTemplateColumns: getComputedStyle(grid).gridTemplateColumns,
      gridGap: getComputedStyle(grid).gap,
      padding: getComputedStyle(grid).padding,
      firstItemWidth: document.querySelector('.product-grid .item-product') ? document.querySelector('.product-grid .item-product').offsetWidth : null
    };
  });
  
  console.log(JSON.stringify(layoutInfo, null, 2));
  
  // also check sidebar
  const sidebarInfo = await page.evaluate(() => {
    const sidebar = document.querySelector('.sidebar.sidebar-main-1, .sidebar-main');
    return sidebar ? {
      display: getComputedStyle(sidebar).display,
      width: sidebar.offsetWidth
    } : "No sidebar";
  });
  console.log("Sidebar:", sidebarInfo);
  
  await browser.close();
})();
