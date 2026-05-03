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
  
  const layoutInfo = await page.evaluate(() => {
      const loginForm = document.querySelector('.login-container');
      const forms = document.querySelectorAll('form');
      return {
          title: document.title,
          hasLoginForm: !!loginForm,
          formCount: forms.length,
          loginFormHTML: loginForm ? loginForm.innerHTML.substring(0, 300) : null
      };
  });
  console.log("Login Layout:", JSON.stringify(layoutInfo, null, 2));
  
  await page.screenshot({ path: '/tmp/login_page.png', fullPage: true });

  await browser.close();
})();
