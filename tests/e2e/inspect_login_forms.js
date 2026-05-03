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
  
  const formInfo = await page.evaluate(() => {
      const forms = Array.from(document.querySelectorAll('form')).map(f => ({
          id: f.id,
          className: f.className,
          action: f.action
      }));
      
      const b2bLogin = document.querySelector('.b2b-login-wrapper, .login-container, .block-customer-login');
      return {
          forms,
          b2bLoginHTML: b2bLogin ? b2bLogin.innerHTML.substring(0, 300) : null,
          b2bLoginClass: b2bLogin ? b2bLogin.className : null
      };
  });
  console.log("Forms:", JSON.stringify(formInfo, null, 2));

  await browser.close();
})();
