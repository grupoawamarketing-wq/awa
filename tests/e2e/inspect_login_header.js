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
  
  const btnInfo = await page.evaluate(() => {
      // Find all red buttons in the header
      const header = document.querySelector('.awa-site-header');
      if (!header) return null;
      
      const buttons = Array.from(header.querySelectorAll('a, button, li')).filter(el => {
          const style = getComputedStyle(el);
          return style.backgroundColor === 'rgb(183, 51, 55)' || style.backgroundColor.includes('183'); // var(--awa-red)
      });
      
      return buttons.map(b => ({
          tag: b.tagName,
          text: b.textContent.trim(),
          className: b.className,
          display: getComputedStyle(b).display
      }));
  });
  console.log("Red Buttons:", JSON.stringify(btnInfo, null, 2));

  await browser.close();
})();
