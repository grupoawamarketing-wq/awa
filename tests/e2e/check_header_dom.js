const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const header = document.querySelector('.header.content');
    if (!header) return null;
    return header.innerHTML.substring(0, 1000);
  });

  console.log(data);
  await browser.close();
})();
