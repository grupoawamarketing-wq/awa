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
  
  const state = await page.evaluate(() => {
      const toggles = Array.from(document.querySelectorAll('[data-action="toggle-nav"], .nav-toggle, [data-action="navigation"]'));
      return toggles.map(t => ({
          className: t.className,
          id: t.id,
          display: getComputedStyle(t).display,
          html: t.outerHTML
      }));
  });
  console.log("Toggles:", JSON.stringify(state, null, 2));

  await browser.close();
})();
