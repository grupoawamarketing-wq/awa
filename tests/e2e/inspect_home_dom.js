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
  
  await page.goto('https://awamotos.com/', { waitUntil: 'load' });
  await page.waitForTimeout(3000);
  
  const layoutInfo = await page.evaluate(() => {
      // Look at the main structure
      const pageMain = document.querySelector('.page-main');
      const container = document.querySelector('.container, .container-fluid, .page-wrapper');
      const categoriesSection = document.querySelector('.section-categories, .category-slider, [class*="category"]');
      const superOfertas = document.querySelector('.rokan-product-heading');
      const carousels = Array.from(document.querySelectorAll('.swiper-container')).map(el => el.className);
      
      return {
          mainPadding: pageMain ? getComputedStyle(pageMain).padding : null,
          mainWidth: pageMain ? getComputedStyle(pageMain).width : null,
          hasCategories: !!categoriesSection,
          superOfertasTitle: superOfertas ? superOfertas.textContent.trim() : null,
          carousels
      };
  });
  console.log("Home Layout Info:", JSON.stringify(layoutInfo, null, 2));

  await browser.close();
})();
