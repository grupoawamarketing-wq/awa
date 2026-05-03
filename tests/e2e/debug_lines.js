const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const guest = document.querySelector('.awa-header-account-prompt__guest');
    if (!guest) return null;

    let children = [];
    for (let child of guest.children) {
      const c = window.getComputedStyle(child);
      children.push({
        className: child.className,
        height: c.height,
        lineHeight: c.lineHeight,
        padding: c.padding,
        margin: c.margin,
        display: c.display,
        html: child.outerHTML
      });
    }
    return children;
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
