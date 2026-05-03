const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    // Find any elements with a red background in the top part of the page
    const all = Array.from(document.querySelectorAll('*'));
    const redBtns = all.filter(el => {
      const bg = getComputedStyle(el).backgroundColor;
      const rect = el.getBoundingClientRect();
      return (bg.includes('rgb(204, 0, 0)') || bg.includes('rgb(194, 39, 45)') || bg.includes('rgb(193, 39, 45)') || bg.includes('var(--awa-red)')) 
             && rect.y > 200 && rect.y < 400 && rect.width > 10 && rect.height > 10;
    }).map(el => ({
      className: el.className,
      tag: el.tagName,
      rect: el.getBoundingClientRect(),
      bg: getComputedStyle(el).backgroundColor
    }));
    return redBtns;
  });
  
  console.log(JSON.stringify(layoutInfo, null, 2));
  await browser.close();
})();
