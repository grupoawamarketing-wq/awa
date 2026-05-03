const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', err => errors.push(err.message));
    page.on('console', msg => {
        if (msg.type() === 'error') errors.push(msg.text());
    });
    
    await page.goto('https://awamotos.com/', { waitUntil: 'networkidle' });
    await page.waitForTimeout(5000);
    
    console.log(JSON.stringify(errors, null, 2));
    await browser.close();
})();
