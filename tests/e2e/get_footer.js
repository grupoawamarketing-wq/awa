const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  
  const bottomNavHtml = await page.evaluate(() => {
    const nav = document.querySelector('.awa-mobile-bottom-nav') || document.querySelector('.footer-container') || document.querySelector('.page-footer');
    return nav ? nav.outerHTML : 'No footer found';
  });
  
  fs.writeFileSync('mobile_footer_dom.html', bottomNavHtml);
  console.log('Saved mobile_footer_dom.html');
  await browser.close();
})();
