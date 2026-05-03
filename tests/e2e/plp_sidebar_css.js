const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const sidebar = document.querySelector('.col-xs-12.col-sm-3');
    if (!sidebar) return { error: "No sidebar found" };
    
    return {
      width: sidebar.offsetWidth,
      cssWidth: getComputedStyle(sidebar).width,
      cssMaxWidth: getComputedStyle(sidebar).maxWidth,
      cssMinWidth: getComputedStyle(sidebar).minWidth,
      padding: getComputedStyle(sidebar).padding,
      margin: getComputedStyle(sidebar).margin
    };
  });
  
  console.log(JSON.stringify(layoutInfo, null, 2));
  await browser.close();
})();
