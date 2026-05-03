const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  
  await page.setViewportSize({ width: 1280, height: 1024 });

  console.log("Navigating to catalog search...");
  await page.goto('https://awamotos.com/catalogsearch/result/?q=moto', { waitUntil: 'networkidle' });
  
  await page.screenshot({ path: 'plp_desktop.png', fullPage: true });
  console.log("Screenshot saved to plp_desktop.png");

  const layoutIssues = await page.evaluate(() => {
    const issues = [];
    
    const sidebar = document.querySelector('.sidebar.sidebar-main-1, .sidebar-main');
    const mainContent = document.querySelector('.column.main, .page-main');
    const productList = document.querySelector('.product-grid.container-products-switch');
    const productItems = document.querySelectorAll('.item-product');
    
    if (sidebar) {
      issues.push(`Sidebar width: ${sidebar.offsetWidth}px, padding: ${getComputedStyle(sidebar).padding}`);
    } else {
      issues.push("Sidebar not found.");
    }
    
    if (mainContent) {
      issues.push(`Main content width: ${mainContent.offsetWidth}px, padding: ${getComputedStyle(mainContent).padding}`);
    } else {
      issues.push("Main content not found.");
    }
    
    if (productList) {
      issues.push(`Product list display: ${getComputedStyle(productList).display}, flex-wrap: ${getComputedStyle(productList).flexWrap}, gap: ${getComputedStyle(productList).gap}`);
    } else {
      issues.push("Product list not found.");
    }
    
    if (productItems.length > 0) {
      const item = productItems[0];
      issues.push(`First product item width: ${item.offsetWidth}px, padding: ${getComputedStyle(item).padding}, margin: ${getComputedStyle(item).margin}`);
    } else {
      issues.push("No product items found.");
    }
    
    return issues;
  });
  
  console.log("\nLayout Info:");
  console.log(layoutIssues.join("\n"));

  await browser.close();
})();
