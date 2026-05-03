const { chromium } = require('playwright-core');

(async () => {
  const browser = await chromium.launch({
    executablePath: '/usr/bin/google-chrome',
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const context = await browser.newContext({
    viewport: { width: 1440, height: 1080 },
    ignoreHTTPSErrors: true
  });
  const page = await context.newPage();
  
  await page.goto('https://awamotos.com/customer/account/login/', { waitUntil: 'load' });
  await page.waitForTimeout(2000);
  
  const cssCheck = await page.evaluate(() => {
      const links = document.querySelectorAll('link[rel="stylesheet"]');
      const hasFixedCSS = Array.from(links).some(l => l.href.includes('awa-layout-fixed.css'));
      
      const loginForm = document.querySelector('.block-customer-login');
      const style = loginForm ? getComputedStyle(loginForm) : null;
      
      return {
          hasFixedCSS,
          formBackground: style ? style.backgroundColor : null,
          formBoxShadow: style ? style.boxShadow : null,
          bodyClasses: document.body.className
      };
  });
  console.log("CSS Check:", JSON.stringify(cssCheck, null, 2));

  await browser.close();
})();
