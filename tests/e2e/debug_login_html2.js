const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/customer/account/login/');
  const inputHTML = await page.evaluate(() => {
    const el = document.querySelector('#b2b-email') || document.querySelector('#email');
    if(!el) return 'not found';
    return {
      el: el.outerHTML,
      parent1: el.parentElement.outerHTML,
      parent2: el.parentElement.parentElement.outerHTML
    };
  });
  console.log(inputHTML);
  await browser.close();
})();
