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
  
  const domInfo = await page.evaluate(() => {
      const main = document.querySelector('.column.main');
      const b2bContainer = document.querySelector('.b2b-register-container, .b2b-register, .form-b2b-register, #b2b-register-app') || document.querySelector('[id*="b2b"]');
      
      const pageWrapper = document.querySelector('.page-wrapper');
      
      return {
          mainClasses: main ? main.className : null,
          mainWidth: main ? getComputedStyle(main).width : null,
          mainDisplay: main ? getComputedStyle(main).display : null,
          b2bContainerClasses: b2bContainer ? b2bContainer.className : null,
          b2bContainerWidth: b2bContainer ? getComputedStyle(b2bContainer).width : null,
          bodyClasses: document.body.className
      };
  });
  console.log("Register DOM Info:", JSON.stringify(domInfo, null, 2));

  await browser.close();
})();
