const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const inner = document.querySelector('.awa-main-header__inner.wp-header');
    if (!inner) return null;
    
    let children = [];
    for (let c of inner.children) {
      children.push({
        className: c.className,
        display: window.getComputedStyle(c).display,
        width: c.getBoundingClientRect().width,
        html: c.innerHTML.substring(0, 30).replace(/\n/g, '')
      });
    }

    return {
      className: inner.className,
      display: window.getComputedStyle(inner).display,
      gridTemplateColumns: window.getComputedStyle(inner).gridTemplateColumns,
      gap: window.getComputedStyle(inner).gap,
      padding: window.getComputedStyle(inner).padding,
      children
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
