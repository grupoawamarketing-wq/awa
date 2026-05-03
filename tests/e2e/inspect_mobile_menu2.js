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
  
  // Wait for toggle
  const toggle = await page.waitForSelector('.awa-header-mobile-toggle, .nav-toggle', { timeout: 5000 });
  await toggle.click();
  
  // Wait for body to get nav-open class
  await page.waitForFunction(() => document.documentElement.classList.contains('nav-open') || document.body.classList.contains('nav-open'), { timeout: 5000 });
  
  await page.screenshot({ path: '/tmp/mobile_menu_open.png' });

  const menuInfo = await page.evaluate(() => {
      const menu = document.querySelector('.nav-sections, .navigation, .mobile-menu');
      return {
          htmlClass: document.documentElement.className,
          bodyClass: document.body.className,
          menuClasses: menu ? menu.className : null,
          menuDisplay: menu ? getComputedStyle(menu).display : null,
          menuLeft: menu ? getComputedStyle(menu).left : null,
          menuTransform: menu ? getComputedStyle(menu).transform : null,
          menuZIndex: menu ? getComputedStyle(menu).zIndex : null
      };
  });
  console.log("Mobile Menu Info:", JSON.stringify(menuInfo, null, 2));

  await browser.close();
})();
