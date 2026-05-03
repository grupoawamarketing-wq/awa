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
  
  await page.goto('https://awamotos.com/eletronicos.html', { waitUntil: 'load' });
  await page.waitForTimeout(2000);
  
  const sidebarInfo = await page.evaluate(() => {
      const sidebar = document.querySelector('.sidebar');
      const filter = document.querySelector('.block.filter');
      return {
          sidebarExists: !!sidebar,
          sidebarClass: sidebar ? sidebar.className : null,
          filterExists: !!filter,
          columnsClasses: document.querySelector('.columns') ? document.querySelector('.columns').className : null
      };
  });
  console.log("Sidebar:", JSON.stringify(sidebarInfo, null, 2));

  await browser.close();
})();
