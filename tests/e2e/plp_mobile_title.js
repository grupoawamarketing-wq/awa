const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const title = document.querySelector('.filter-options-title');
    return title ? {
      text: title.innerText,
      rect: title.getBoundingClientRect(),
      css: title.style.cssText
    } : "No title found";
  });
  
  console.log(JSON.stringify(layoutInfo, null, 2));
  await browser.close();
})();
