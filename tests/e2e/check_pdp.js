const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  
  await page.goto('https://awamotos.com/');
  const productLink = await page.$('a.product-item-link');
  if (productLink) {
    const url = await productLink.getAttribute('href');
    console.log('Navigating to product:', url);
    await page.goto(url);
    await page.waitForTimeout(3000);
    await page.screenshot({ path: 'pdp_desktop.png', fullPage: false });
    console.log('PDP screenshot saved to tests/e2e/pdp_desktop.png');
  } else {
    console.log('Could not find a product link on the homepage.');
  }

  await browser.close();
})();
