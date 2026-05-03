const { chromium } = require('playwright-core');

(async () => {
  try {
    const browser = await chromium.launch({
      executablePath: '/usr/bin/google-chrome',
      args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const context = await browser.newContext({
      viewport: { width: 1440, height: 1080 },
      ignoreHTTPSErrors: true
    });
    const page = await context.newPage();
    
    console.log("Navigating to PDP...");
    await page.goto('https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html', { waitUntil: 'load', timeout: 15000 });
    await page.screenshot({ path: '/tmp/pdp.png' });
    console.log("Screenshot taken: /tmp/pdp.png");
    
    const title = await page.title();
    console.log("Title: " + title);
    
    await browser.close();
  } catch (e) {
    console.error("ERROR:", e);
  }
})();
