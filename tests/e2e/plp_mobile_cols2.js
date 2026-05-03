const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const cols = document.querySelector('.columns');
    return {
      cssDisplay: getComputedStyle(cols).display,
      cssFlexWrap: getComputedStyle(cols).flexWrap,
      cssFlexDirection: getComputedStyle(cols).flexDirection,
      width: cols.offsetWidth
    };
  });
  
  console.log(JSON.stringify(layoutInfo, null, 2));
  await browser.close();
})();
