const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const logo = document.querySelector('.logo');
    if (!logo) return null;
    
    const parent = logo.parentElement; // col-md-2
    const row = parent.parentElement; // should be the flex container
    
    let children = [];
    for (let c of row.children) {
      children.push({
        className: c.className,
        width: c.getBoundingClientRect().width
      });
    }

    return {
      className: row.className,
      display: window.getComputedStyle(row).display,
      alignItems: window.getComputedStyle(row).alignItems,
      justifyContent: window.getComputedStyle(row).justifyContent,
      gap: window.getComputedStyle(row).gap,
      children
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
