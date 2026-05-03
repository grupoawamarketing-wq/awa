const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/customer/account/login/');
  const inputHTML = await page.evaluate(() => {
    const el = document.querySelector('#b2b-email') || document.querySelector('#email');
    return el ? el.outerHTML : 'not found';
  });
  console.log(inputHTML);
  await browser.close();
})();
