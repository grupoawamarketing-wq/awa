const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto('https://awamotos.com/');
    
    // Wait for the header to load
    await page.waitForSelector('.awa-site-header');

    const html = await page.evaluate(() => {
        const el = document.querySelector('.awa-header-account-prompt');
        return el ? el.outerHTML : null;
    });

    console.log(html);

    await browser.close();
})();