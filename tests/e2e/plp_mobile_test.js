const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  await page.evaluate(() => {
    document.querySelector('.columns').style.alignItems = 'stretch';
    document.querySelector('.columns').style.width = '100%';
  });
  
  const layoutInfo = await page.evaluate(() => {
    const sidebar = document.querySelector('.col-xs-12.col-sm-3');
    return {
      sidebarWidth: sidebar.offsetWidth
    };
  });
  
  console.log(layoutInfo);
  await browser.close();
})();
