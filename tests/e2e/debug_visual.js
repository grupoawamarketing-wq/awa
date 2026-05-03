const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  
  // Test Header
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/');
  const header = await page.$('#header, .awa-site-header');
  if (header) {
    const box = await header.boundingBox();
    console.log('Header height:', box ? box.height : 'no box');
  } else {
    console.log('Header not found');
  }
  
  // Test Login Inputs
  await page.goto('https://awamotos.com/customer/account/login/');
  const input = await page.$('#b2b-email, #email');
  if (input) {
    const styles = await page.evaluate(el => {
      const s = window.getComputedStyle(el);
      return { height: s.height, borderRadius: s.borderRadius, fontSize: s.fontSize };
    }, input);
    console.log('Login Input styles:', styles);
  } else {
    console.log('Login Input not found');
  }

  // Test Cart
  const cartRes = await page.goto('https://awamotos.com/checkout/cart/');
  console.log('Cart page status:', cartRes ? cartRes.status() : 'no response');
  const cartTitle = await page.$('.page-title-wrapper, h1.page-title');
  if (cartTitle) {
      console.log('Cart Title visible:', await cartTitle.isVisible());
  } else {
      console.log('Cart Title not found');
  }

  await browser.close();
})();
