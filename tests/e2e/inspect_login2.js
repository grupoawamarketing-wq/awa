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
  
  await page.goto('https://awamotos.com/customer/account/login/', { waitUntil: 'load' });
  await page.waitForTimeout(2000);
  
  await page.screenshot({ path: '/tmp/login_page_fixed.png', fullPage: true });

  await browser.close();
})();
