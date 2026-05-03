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
      const child = document.querySelector('#awa-category-navigation');
      if (!child) return null;
      
      const style = getComputedStyle(child);
      return {
          display: style.display,
          visibility: style.visibility,
          opacity: style.opacity,
          height: style.height
      };
  });
  console.log("Child Info:", JSON.stringify(domInfo, null, 2));

  await browser.close();
})();
