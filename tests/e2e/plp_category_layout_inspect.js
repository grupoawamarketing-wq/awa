const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1280, height: 1024 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const cols = document.querySelector('.columns');
    if (!cols) return { error: "No .columns found" };
    
    return Array.from(cols.children).map(child => ({
      className: child.className,
      width: child.offsetWidth,
      cssFlex: getComputedStyle(child).flex,
      cssWidth: getComputedStyle(child).width,
      cssMaxWidth: getComputedStyle(child).maxWidth
    }));
  });
  
  console.log(JSON.stringify(layoutInfo, null, 2));
  await browser.close();
})();
