const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const line2 = document.querySelector('.awa-header-account-prompt__line2');
    if (!line2) return null;
    
    const children = Array.from(line2.children).map(child => {
      const rect = child.getBoundingClientRect();
      const style = window.getComputedStyle(child);
      return {
        tag: child.tagName,
        className: child.className,
        text: child.innerText.trim(),
        width: rect.width,
        height: rect.height,
        margin: style.margin,
        display: style.display,
        lineHeight: style.lineHeight,
        fontSize: style.fontSize
      };
    });

    return {
      line2Height: line2.getBoundingClientRect().height,
      html: line2.innerHTML,
      children
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
