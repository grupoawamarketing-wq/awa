const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const btn = document.querySelector('.filter-toggle, [data-role="tocart"], .action.filter, .block-title.filter-title');
    if (!btn) return "No filter button found";
    
    return {
      text: btn.innerText,
      className: btn.className,
      display: getComputedStyle(btn).display
    };
  });
  
  console.log(layoutInfo);
  await browser.close();
})();
