const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto('https://awamotos.com/customer/account/login/');
  const elements = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('*'))
      .map(el => {
        const rect = el.getBoundingClientRect();
        return {
          tag: el.tagName.toLowerCase(),
          class: el.className,
          width: Math.round(rect.width)
        };
      })
      .filter(el => el.width > 900 && el.width < 1000);
  });
  console.log('Elements with width between 900 and 1000:', elements);
  await browser.close();
})();
