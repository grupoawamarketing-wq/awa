const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  
  const headerHtml = await page.evaluate(() => {
    const header = document.querySelector('header');
    return header ? header.outerHTML : 'No header element found';
  });
  
  fs.writeFileSync('mobile_header_dom.html', headerHtml);
  console.log('Saved mobile_header_dom.html');
  await browser.close();
})();
