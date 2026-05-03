const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  await page.screenshot({ path: 'plp_mobile.png', fullPage: true });
  console.log("Screenshot saved to plp_mobile.png");
  
  await browser.close();
})();
