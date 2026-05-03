const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const guest = document.querySelector('.awa-header-account-prompt__guest');
    if (!guest) return null;
    
    const children = Array.from(guest.children).map(child => {
      const rect = child.getBoundingClientRect();
      const style = window.getComputedStyle(child);
      return {
        tag: child.tagName,
        className: child.className,
        width: rect.width,
        height: rect.height,
        margin: style.margin,
        display: style.display,
        flexDirection: style.flexDirection
      };
    });

    return {
      guestHeight: guest.getBoundingClientRect().height,
      flexDirection: window.getComputedStyle(guest).flexDirection,
      children
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
