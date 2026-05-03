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
  
  const cssInfo = await page.evaluate(() => {
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(l => l.href);
      return links;
  });
  console.log("CSS Links:", JSON.stringify(cssInfo, null, 2));

  await browser.close();
})();
