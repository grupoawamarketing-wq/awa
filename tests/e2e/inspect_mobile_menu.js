const { chromium } = require('playwright-core');

(async () => {
  const browser = await chromium.launch({
    executablePath: '/usr/bin/google-chrome',
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const context = await browser.newContext({
    viewport: { width: 375, height: 812 },
    ignoreHTTPSErrors: true
  });
  const page = await context.newPage();
  
  await page.goto('https://awamotos.com/', { waitUntil: 'load' });
  await page.waitForTimeout(2000);
  
  // Click the hamburger toggle if it exists
  const toggle = await page.$('.awa-header-mobile-toggle, .nav-toggle');
  if (toggle) {
      await toggle.click();
      await page.waitForTimeout(1000);
  }
  
  await page.screenshot({ path: '/tmp/mobile_menu.png' });

  const menuInfo = await page.evaluate(() => {
      const menu = document.querySelector('.nav-sections, .navigation, .mobile-menu');
      return {
          menuExists: !!menu,
          menuClasses: menu ? menu.className : null,
          display: menu ? getComputedStyle(menu).display : null,
          position: menu ? getComputedStyle(menu).position : null,
          width: menu ? getComputedStyle(menu).width : null
      };
  });
  console.log("Mobile Menu Info:", JSON.stringify(menuInfo, null, 2));

  await browser.close();
})();
