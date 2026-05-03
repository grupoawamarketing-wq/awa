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
  
  console.log("Navigating to PDP...");
  await page.goto('https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html', { waitUntil: 'load' });
  await page.waitForTimeout(2000);
  
  const pdpInfo = await page.evaluate(() => {
      const main = document.querySelector('.column.main');
      const media = document.querySelector('.product.media');
      const info = document.querySelector('.product-info-main');
      const tocart = document.querySelector('.box-tocart');
      
      return {
          main: main ? { display: getComputedStyle(main).display, width: getComputedStyle(main).width, flexDirection: getComputedStyle(main).flexDirection } : null,
          media: media ? { display: getComputedStyle(media).display, width: getComputedStyle(media).width, float: getComputedStyle(media).float } : null,
          info: info ? { display: getComputedStyle(info).display, width: getComputedStyle(info).width, float: getComputedStyle(info).float, padding: getComputedStyle(info).padding } : null,
          tocart: tocart ? { display: getComputedStyle(tocart).display, alignItems: getComputedStyle(tocart).alignItems, gap: getComputedStyle(tocart).gap, margin: getComputedStyle(tocart).margin } : null
      };
  });
  console.log("PDP Info:\n", JSON.stringify(pdpInfo, null, 2));

  console.log("\nNavigating to PLP...");
  await page.goto('https://awamotos.com/eletronicos.html', { waitUntil: 'load' });
  await page.waitForTimeout(2000);

  const plpInfo = await page.evaluate(() => {
      const columns = document.querySelector('.columns');
      const sidebar = document.querySelector('.sidebar.sidebar-main');
      const main = document.querySelector('.column.main');
      const toolbar = document.querySelector('.toolbar-products');
      
      return {
          columns: columns ? { display: getComputedStyle(columns).display, flexWrap: getComputedStyle(columns).flexWrap, gap: getComputedStyle(columns).gap } : null,
          sidebar: sidebar ? { width: getComputedStyle(sidebar).width, float: getComputedStyle(sidebar).float, padding: getComputedStyle(sidebar).padding } : null,
          main: main ? { width: getComputedStyle(main).width, float: getComputedStyle(main).float } : null,
          toolbar: toolbar ? { display: getComputedStyle(toolbar).display, alignItems: getComputedStyle(toolbar).alignItems, margin: getComputedStyle(toolbar).margin } : null
      };
  });
  console.log("PLP Info:\n", JSON.stringify(plpInfo, null, 2));

  await browser.close();
})();
