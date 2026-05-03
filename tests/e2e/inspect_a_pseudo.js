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
  
  await page.goto('https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html', { waitUntil: 'load' });
  await page.waitForTimeout(2000);
  
  const pseudoInfo = await page.evaluate(() => {
      const aTag = document.querySelector('.b2b-login-to-see-price a');
      if (!aTag) return null;
      const before = getComputedStyle(aTag, '::before');
      return {
          content: before.content,
          fontFamily: before.fontFamily,
          color: before.color
      };
  });
  console.log("Pseudo:", JSON.stringify(pseudoInfo, null, 2));

  await browser.close();
})();
