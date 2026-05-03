const { chromium } = require('playwright-core');

(async () => {
  const browser = await chromium.launch({
    executablePath: '/usr/bin/google-chrome',
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const context = await browser.newContext({
    viewport: { width: 1440, height: 1080 },
    ignoreHTTPSErrors: true
  });
  const page = await context.newPage();
  
  await page.goto('https://awamotos.com/catalogsearch/result/?q=retrovisor', { waitUntil: 'load' });
  await page.waitForTimeout(2000);
  
  const layoutInfo = await page.evaluate(() => {
      const pageTitle = document.querySelector('.page-title-wrapper h1');
      const sidebar = document.querySelector('.sidebar.sidebar-main');
      const productsGrid = document.querySelector('.products-grid');
      const columns = document.querySelector('.columns');
      return {
          title: document.title,
          h1: pageTitle ? pageTitle.textContent.trim() : null,
          hasSidebar: !!sidebar,
          hasGrid: !!productsGrid,
          columnsClasses: columns ? columns.className : null,
          columnsDisplay: columns ? getComputedStyle(columns).display : null,
          columnsGridTemplate: columns ? getComputedStyle(columns).gridTemplateColumns : null
      };
  });
  console.log("Layout:", JSON.stringify(layoutInfo, null, 2));
  
  await page.screenshot({ path: '/tmp/plp_search.png', fullPage: true });

  await browser.close();
})();
