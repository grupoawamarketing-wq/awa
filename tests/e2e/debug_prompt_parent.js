const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const el = document.querySelector('.awa-header-account-prompt');
    if (!el) return null;
    const parent = el.parentElement;

    let children = [];
    for (let child of parent.children) {
      children.push({
        className: child.className,
        height: window.getComputedStyle(child).height,
        alignSelf: window.getComputedStyle(child).alignSelf
      });
    }

    return {
      parentClass: parent.className,
      height: window.getComputedStyle(parent).height,
      display: window.getComputedStyle(parent).display,
      alignItems: window.getComputedStyle(parent).alignItems,
      gridTemplateRows: window.getComputedStyle(parent).gridTemplateRows,
      children
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
