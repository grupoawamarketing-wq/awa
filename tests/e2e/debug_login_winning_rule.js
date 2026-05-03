const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/customer/account/login/');
  const sizes = await page.evaluate(() => {
    const el = document.querySelector('#b2b-email');
    return {
      fontSize: window.getComputedStyle(el).fontSize,
      zoom: window.getComputedStyle(el).zoom,
      transform: window.getComputedStyle(el).transform,
      parentZoom: window.getComputedStyle(el.parentElement).zoom
    };
  });
  console.log(sizes);
  await browser.close();
})();
