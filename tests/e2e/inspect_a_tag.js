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
  
  const cssInfo = await page.evaluate(() => {
      const aTag = document.querySelector('.b2b-login-to-see-price a');
      if (!aTag) return null;
      const styles = getComputedStyle(aTag);
      return {
          display: styles.display,
          width: styles.width,
          height: styles.height,
          background: styles.background,
          color: styles.color,
          fontSize: styles.fontSize,
          padding: styles.padding,
          margin: styles.margin,
          textIndent: styles.textIndent
      };
  });
  console.log("CSS:", JSON.stringify(cssInfo, null, 2));

  await browser.close();
})();
