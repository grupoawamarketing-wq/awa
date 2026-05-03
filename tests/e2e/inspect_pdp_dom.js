const { chromium } = require('playwright-core');

(async () => {
  const browser = await chromium.launch({
    executablePath: '/usr/bin/google-chrome',
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const context = await browser.newContext({
    viewport: { width: 1440, height: 1080 },
    ignoreHTTPSErrors: true
  });
  const page = await context.newPage();
  
  console.log("Navigating to PDP...");
  await page.goto('https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html', { waitUntil: 'load' });
  await page.waitForTimeout(2000);
  
  const domInfo = await page.evaluate(() => {
      const redBox = document.querySelector('.product-info-main .login-to-see-price') || 
                     Array.from(document.querySelectorAll('div')).find(el => el.textContent.includes('para ver preços'));
                     
      const pills = document.querySelector('.product-info-stock-sku');
      
      return {
          redBoxClass: redBox ? redBox.className : null,
          redBoxHTML: redBox ? redBox.outerHTML : null,
          pillsHTML: pills ? pills.outerHTML : null,
          mediaHTML: document.querySelector('.product.media') ? document.querySelector('.product.media').innerHTML.substring(0, 500) : null
      };
  });
  console.log("DOM Info:\n", JSON.stringify(domInfo, null, 2));

  await browser.close();
})();
