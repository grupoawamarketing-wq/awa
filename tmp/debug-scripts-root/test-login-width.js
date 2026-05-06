const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto('https://awamotos.com/customer/account/login/');
  const width = await page.evaluate(() => {
    const el = document.querySelector('.page-main');
    return el ? el.getBoundingClientRect().width : null;
  });
  console.log('page-main width:', width);
  await browser.close();
})();
