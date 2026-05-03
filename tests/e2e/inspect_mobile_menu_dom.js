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
  
  await page.evaluate(() => {
      document.documentElement.classList.add('nav-open');
  });
  
  await page.waitForTimeout(1000);

  const domInfo = await page.evaluate(() => {
      const menu = document.querySelector('.nav-sections, .navigation, .mobile-menu');
      return {
          html: menu ? menu.outerHTML.substring(0, 1500) : null,
          cssText: menu ? menu.style.cssText : null,
          computed: menu ? {
              display: getComputedStyle(menu).display,
              position: getComputedStyle(menu).position,
              top: getComputedStyle(menu).top,
              left: getComputedStyle(menu).left,
              right: getComputedStyle(menu).right,
              width: getComputedStyle(menu).width,
              height: getComputedStyle(menu).height,
              zIndex: getComputedStyle(menu).zIndex,
              transform: getComputedStyle(menu).transform,
              visibility: getComputedStyle(menu).visibility,
              opacity: getComputedStyle(menu).opacity
          } : null
      };
  });
  console.log("DOM Info:", JSON.stringify(domInfo, null, 2));

  await browser.close();
})();
