const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  let cssUrl = '';
  page.on('response', response => {
    if (response.url().includes('awa-bundle-refinements.unmin.css') || response.url().includes('awa-bundle-refinements.css')) {
      cssUrl = response.url();
    }
  });
  await page.goto('https://awamotos.com/customer/account/login/');
  if (cssUrl) {
    const res = await page.evaluate(async (url) => {
        const resp = await fetch(url);
        return await resp.text();
    }, cssUrl);
    console.log(res.split('FIX LOGIN INPUT FONT SIZE')[1].substring(0, 300));
  }
  await browser.close();
})();
