const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const title = document.querySelector('.filter-options-title');
    if (!title) return null;
    const after = getComputedStyle(title, '::after');
    const before = getComputedStyle(title, '::before');
    return {
      after: {
        content: after.content,
        width: after.width,
        height: after.height,
        bg: after.backgroundColor,
        position: after.position,
        right: after.right
      },
      before: {
        content: before.content,
        bg: before.backgroundColor
      }
    };
  });
  
  console.log(JSON.stringify(layoutInfo, null, 2));
  await browser.close();
})();
