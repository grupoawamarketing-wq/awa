const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const els = document.querySelectorAll('.awa-header-account-prompt__line2');
    return Array.from(els).map(el => {
      let path = [];
      let curr = el;
      while(curr && curr !== document.body) {
        path.push(curr.tagName + (curr.id ? '#'+curr.id : '') + (curr.className ? '.' + curr.className.split(' ').join('.') : ''));
        curr = curr.parentElement;
      }
      return {
        path: path.join(' < '),
        flexDirection: window.getComputedStyle(el).flexDirection,
        gap: window.getComputedStyle(el).gap,
        display: window.getComputedStyle(el).display,
        html: el.innerHTML.trim().substring(0, 50)
      };
    });
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
