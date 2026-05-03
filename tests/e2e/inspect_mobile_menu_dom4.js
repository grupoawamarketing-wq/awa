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
      document.body.classList.add('nav-open');
      document.documentElement.classList.add('nav-open');
      const menu = document.querySelector('.sections.nav-sections');
      if (menu) {
          menu.style.display = 'block';
          menu.style.visibility = 'visible';
          menu.style.opacity = '1';
          menu.style.width = '300px';
          menu.style.height = '100vh';
          menu.style.position = 'fixed';
          menu.style.top = '0';
          menu.style.left = '0';
          menu.style.zIndex = '999999';
          menu.style.backgroundColor = 'red';
      }
  });
  await page.waitForTimeout(1000);
  
  await page.screenshot({ path: '/tmp/mobile_menu_force_js.png' });

  await browser.close();
})();
