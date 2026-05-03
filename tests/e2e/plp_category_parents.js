const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1280, height: 1024 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const grid = document.querySelector('.product-grid');
    if (!grid) return { error: "No grid found" };
    
    let current = grid;
    const hierarchy = [];
    while (current && current.className !== 'columns layout layout-2-col row') {
      hierarchy.push({
        tag: current.tagName,
        className: current.className,
        width: current.offsetWidth,
        margin: getComputedStyle(current).margin,
        padding: getComputedStyle(current).padding,
        maxWidth: getComputedStyle(current).maxWidth,
        display: getComputedStyle(current).display,
      });
      current = current.parentElement;
    }
    return hierarchy;
  });
  
  console.log(JSON.stringify(layoutInfo, null, 2));
  await browser.close();
})();
