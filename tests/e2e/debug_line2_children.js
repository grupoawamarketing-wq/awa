const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const line2 = document.querySelector('.awa-header-account-prompt__line2');
    if (!line2) return null;

    let children = [];
    for (let child of line2.children) {
      const c = window.getComputedStyle(child);
      children.push({
        className: child.className,
        height: c.height,
        lineHeight: c.lineHeight,
        padding: c.padding,
        margin: c.margin,
        display: c.display
      });
    }
    return children;
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
