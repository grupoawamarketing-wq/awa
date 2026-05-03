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
  
  await page.goto('https://awamotos.com/checkout/cart/', { waitUntil: 'load' });
  await page.waitForTimeout(2000);
  
  await page.screenshot({ path: '/tmp/cart_page.png', fullPage: true });

  const layoutInfo = await page.evaluate(() => {
      const pageTitle = document.querySelector('.page-title-wrapper h1');
      const cartEmpty = document.querySelector('.cart-empty');
      return {
          title: document.title,
          h1: pageTitle ? pageTitle.textContent.trim() : null,
          isEmpty: !!cartEmpty
      };
  });
  console.log("Cart Info:", JSON.stringify(layoutInfo, null, 2));

  await browser.close();
})();
