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
  
  // Force add nav-open class to HTML
  await page.evaluate(() => {
      document.documentElement.classList.add('nav-open');
  });
  
  await page.waitForTimeout(1000);
  
  await page.screenshot({ path: '/tmp/mobile_menu_force.png' });

  await browser.close();
})();
