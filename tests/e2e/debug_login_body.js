const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/customer/account/login/');
  const bodyId = await page.evaluate(() => document.body.id);
  const inputStyle = await page.evaluate(() => {
    const el = document.querySelector('#b2b-email');
    if (!el) return null;
    const style = window.getComputedStyle(el);
    return { fontSize: style.fontSize, height: style.height };
  });
  console.log('Body ID:', bodyId);
  console.log('Input Style:', inputStyle);
  await browser.close();
})();
