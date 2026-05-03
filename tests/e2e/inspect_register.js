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
  
  await page.goto('https://awamotos.com/customer/account/create/', { waitUntil: 'load' });
  await page.waitForTimeout(2000);
  
  await page.screenshot({ path: '/tmp/register_page.png', fullPage: true });

  const layoutInfo = await page.evaluate(() => {
      const pageTitle = document.querySelector('.page-title-wrapper h1');
      const form = document.querySelector('.form-create-account');
      return {
          title: document.title,
          h1: pageTitle ? pageTitle.textContent.trim() : null,
          hasForm: !!form,
          formClasses: form ? form.className : null
      };
  });
  console.log("Register Info:", JSON.stringify(layoutInfo, null, 2));

  await browser.close();
})();
