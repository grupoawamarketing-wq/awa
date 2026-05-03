const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const filterBtn = document.querySelector('.filter-toggle');
    const sidebar = document.querySelector('.sidebar-main');
    return {
      filterBtnClass: filterBtn ? filterBtn.className : null,
      filterBtnDisplay: filterBtn ? getComputedStyle(filterBtn).display : null,
      sidebarDisplay: sidebar ? getComputedStyle(sidebar).display : null,
    };
  });
  
  console.log(layoutInfo);
  await browser.close();
})();
