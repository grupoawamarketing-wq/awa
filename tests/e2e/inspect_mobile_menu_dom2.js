const { chromium } = require('playwright-core');

(async () => {
  const browser = await chromium.launch({
    executablePath: '/usr/bin/google-chrome',
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const context = await browser.newContext({
    viewport: { width: 375, height: 812 },
    ignoreHTTPSErrors: true
  });
  const page = await context.newPage();
  
  await page.goto('https://awamotos.com/', { waitUntil: 'load' });
  await page.waitForTimeout(2000);
  
  await page.evaluate(() => {
      document.documentElement.classList.add('nav-open');
  });
  await page.waitForTimeout(1000);

  const domInfo = await page.evaluate(() => {
      const menu = document.querySelector('.sections.nav-sections');
      if (!menu) return null;
      
      const style = getComputedStyle(menu);
      return {
          className: menu.className,
          display: style.display,
          visibility: style.visibility,
          opacity: style.opacity,
          left: style.left,
          top: style.top,
          zIndex: style.zIndex,
          width: style.width,
          height: style.height,
          hasChildren: menu.children.length,
          firstChildClass: menu.children[0] ? menu.children[0].className : null
      };
  });
  console.log("DOM Info:", JSON.stringify(domInfo, null, 2));

  await browser.close();
})();
