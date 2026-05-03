const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/adaptadores.html', { waitUntil: 'networkidle' });
  const html = await page.content();
  console.log(html.substring(0, 1000));
  
  // Try another category if adaptadores is empty
  const bodyClass = await page.evaluate(() => document.body.className);
  console.log("Body class:", bodyClass);
  await browser.close();
})();
