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
  
  await page.goto('https://awamotos.com/eletronicos.html', { waitUntil: 'load' });
  await page.waitForTimeout(2000);
  
  const titleInfo = await page.evaluate(() => {
      const pageTitle = document.querySelector('.page-title-wrapper h1');
      return {
          title: document.title,
          h1: pageTitle ? pageTitle.textContent.trim() : null
      };
  });
  console.log("Title:", JSON.stringify(titleInfo, null, 2));

  await browser.close();
})();
