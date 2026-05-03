const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/customer/account/login/');
  const content = await page.content();
  console.log('Includes awa-bundle-refinements?', content.includes('awa-bundle-refinements'));
  await browser.close();
})();
