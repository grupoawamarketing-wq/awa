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
  
  const toggle = await page.$('.awa-header-mobile-toggle');
  if (toggle) {
      await toggle.click();
      await page.waitForTimeout(1000);
  }
  
  const state = await page.evaluate(() => {
      return {
          htmlClass: document.documentElement.className,
          bodyClass: document.body.className
      };
  });
  console.log("State after click:", JSON.stringify(state, null, 2));

  await browser.close();
})();
