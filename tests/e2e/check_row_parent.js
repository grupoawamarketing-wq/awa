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
    const row = logo.closest('.row');
    const style = window.getComputedStyle(row);
    
    let children = [];
    for (let c of row.children) {
      children.push({
        className: c.className,
        width: c.getBoundingClientRect().width,
        html: c.innerHTML.substring(0, 50).replace(/\n/g, '')
      });
    }

    return {
      className: row.className,
      display: style.display,
      alignItems: style.alignItems,
      justifyContent: style.justifyContent,
      children
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
