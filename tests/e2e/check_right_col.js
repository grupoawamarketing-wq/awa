const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const col = document.querySelector('.awa-header-right-col');
    if (!col) return null;
    const style = window.getComputedStyle(col);
    return {
      x: col.getBoundingClientRect().x,
      width: col.getBoundingClientRect().width,
      maxWidth: style.maxWidth,
      minWidth: style.minWidth,
      marginLeft: style.marginLeft,
      marginRight: style.marginRight,
      className: col.className
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
