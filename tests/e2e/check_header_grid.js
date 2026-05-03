const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const header = document.querySelector('.header.content') || document.querySelector('.awa-site-header');
    const style = window.getComputedStyle(header);
    return {
      display: style.display,
      gridTemplateColumns: style.gridTemplateColumns,
      justifyContent: style.justifyContent,
      gap: style.gap,
      padding: style.padding
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
