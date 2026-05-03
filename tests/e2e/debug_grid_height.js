const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const parent = document.querySelector('.awa-site-header'); // Or whatever the grid container is
    // Wait, the grid container is probably inside .awa-site-header, like .header.content
    const headerContent = document.querySelector('.header.content');
    
    if (!headerContent) return null;

    let children = [];
    for (let child of headerContent.children) {
      children.push({
        className: child.className,
        height: window.getComputedStyle(child).height
      });
    }
    
    return {
      height: window.getComputedStyle(headerContent).height,
      display: window.getComputedStyle(headerContent).display,
      gridTemplateRows: window.getComputedStyle(headerContent).gridTemplateRows,
      children
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
