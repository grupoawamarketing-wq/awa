const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const rules = await page.evaluate(() => {
    const sidebar = document.querySelector('.col-xs-12.col-sm-3');
    if (!sidebar) return { error: "No sidebar found" };
    
    // let's just get the flex basis and max width
    return {
      styleText: sidebar.style.cssText,
      cssFlexBasis: getComputedStyle(sidebar).flexBasis,
      cssWidth: getComputedStyle(sidebar).width,
    };
  });
  
  console.log(JSON.stringify(rules, null, 2));
  await browser.close();
})();
