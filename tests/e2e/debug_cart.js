const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  
  // Test Cart
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/checkout/cart/');
  
  const bodyClass = await page.evaluate(() => document.body.className);
  console.log('Cart page body classes:', bodyClass);

  const h1 = await page.evaluate(() => {
    const el = document.querySelector('h1');
    return el ? el.textContent : 'no h1';
  });
  console.log('Cart page H1:', h1);

  await browser.close();
})();
