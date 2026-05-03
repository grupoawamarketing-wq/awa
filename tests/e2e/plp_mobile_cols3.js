const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const cols = document.querySelector('.columns');
    const sidebar = document.querySelector('.col-xs-12.col-sm-3');
    return {
      colsAlignItems: getComputedStyle(cols).alignItems,
      sidebarAlignSelf: getComputedStyle(sidebar).alignSelf,
      sidebarWidth: getComputedStyle(sidebar).width,
      sidebarDisplay: getComputedStyle(sidebar).display
    };
  });
  
  console.log(JSON.stringify(layoutInfo, null, 2));
  await browser.close();
})();
