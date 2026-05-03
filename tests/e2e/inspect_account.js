const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const text = document.querySelector('.awa-header-account-prompt__text');
    if (!text) return null;
    
    const children = Array.from(text.children).map(child => {
      const rect = child.getBoundingClientRect();
      const style = window.getComputedStyle(child);
      return {
        tag: child.tagName,
        className: child.className,
        width: rect.width,
        height: rect.height,
        margin: style.margin,
        padding: style.padding,
        display: style.display,
        position: style.position,
        top: style.top,
        bottom: style.bottom
      };
    });

    const style = window.getComputedStyle(text);
    return {
      textHeight: text.getBoundingClientRect().height,
      padding: style.padding,
      margin: style.margin,
      children
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
