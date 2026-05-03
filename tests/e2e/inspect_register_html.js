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
  
  await page.goto('https://awamotos.com/customer/account/create/', { waitUntil: 'load' });
  await page.waitForTimeout(2000);
  
  const html = await page.evaluate(() => {
      const main = document.querySelector('.column.main');
      return main ? main.innerHTML.substring(0, 1000) : null;
  });
  console.log("Register HTML:", html);

  await browser.close();
})();
