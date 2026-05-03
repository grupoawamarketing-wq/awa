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

  const elements = await page.evaluate(() => {
      // find elements that are positioned fixed or absolute on the right side
      const els = Array.from(document.querySelectorAll('*')).filter(el => {
          const style = getComputedStyle(el);
          if (style.display === 'none' || style.visibility === 'hidden') return false;
          
          const rect = el.getBoundingClientRect();
          // checking for elements on the right edge with a decent height
          if (rect.right > 300 && rect.height > 100 && (style.position === 'fixed' || style.position === 'absolute')) {
              return true;
          }
          return false;
      });
      
      return els.map(el => ({
          tag: el.tagName,
          className: el.className,
          id: el.id,
          rect: el.getBoundingClientRect().toJSON(),
          zIndex: getComputedStyle(el).zIndex
      }));
  });
  console.log("Elements:", JSON.stringify(elements, null, 2));

  await browser.close();
})();
