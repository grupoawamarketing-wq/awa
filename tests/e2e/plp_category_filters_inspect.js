const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1280, height: 1024 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const filters = document.querySelector('.filter-options');
    if (!filters) return { error: "No filters found" };
    
    return {
      cssText: filters.style.cssText,
      className: filters.className,
      html: filters.innerHTML.substring(0, 1000)
    };
  });
  
  console.log(layoutInfo);
  await browser.close();
})();
