const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const middle = document.querySelector('.middle-header');
    if (!middle) return null;
    const style = window.getComputedStyle(middle);
    
    let children = [];
    for (let c of middle.children) {
      children.push({
        className: c.className,
        width: c.getBoundingClientRect().width
      });
    }

    return {
      display: style.display,
      gridTemplateColumns: style.gridTemplateColumns,
      gap: style.gap,
      children
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
