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
  
  await page.screenshot({ path: '/tmp/home_full.png', fullPage: true });

  const homeInfo = await page.evaluate(() => {
      // Find all sections that are likely content blocks/banners
      const sections = Array.from(document.querySelectorAll('.page-main > div, .columns > div, .widget, .rokan-product-heading, .swiper-container, .banner, .footer-container'));
      
      return sections.map((el, i) => {
          const style = getComputedStyle(el);
          return {
              tag: el.tagName,
              className: el.className,
              marginTop: style.marginTop,
              marginBottom: style.marginBottom,
              paddingTop: style.paddingTop,
              paddingBottom: style.paddingBottom,
              width: style.width,
              height: style.height
          };
      }).slice(0, 15); // Return first 15 for brevity
  });
  console.log("Home Sections Info:", JSON.stringify(homeInfo, null, 2));

  await browser.close();
})();
