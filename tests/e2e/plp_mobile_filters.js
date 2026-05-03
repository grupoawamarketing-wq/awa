const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/retrovisores.html', { waitUntil: 'networkidle' });
  
  const layoutInfo = await page.evaluate(() => {
    const sidebar = document.querySelector('.sidebar.sidebar-main-1, .sidebar-main');
    if (!sidebar) return { error: "No sidebar found" };
    
    return {
      sidebarHtml: sidebar.innerHTML.substring(0, 1500)
    };
  });
  
  console.log(layoutInfo);
  await browser.close();
})();
