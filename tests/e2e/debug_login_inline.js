const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/customer/account/login/');
  const size = await page.evaluate(() => {
    const el = document.querySelector('#b2b-email');
    return { style: el.getAttribute('style') };
  });
  console.log(size);
  await browser.close();
})();
