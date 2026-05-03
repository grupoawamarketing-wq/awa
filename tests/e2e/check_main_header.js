const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const header = document.querySelector('.header.awa-main-header');
    if (!header) return null;
    
    let children = [];
    for (let c of header.children) {
      children.push({
        className: c.className,
        display: window.getComputedStyle(c).display,
        width: c.getBoundingClientRect().width,
        height: c.getBoundingClientRect().height,
        html: c.innerHTML.substring(0, 50).replace(/\n/g, '')
      });
    }

    return {
      className: header.className,
      display: window.getComputedStyle(header).display,
      gridTemplateColumns: window.getComputedStyle(header).gridTemplateColumns,
      children
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
