const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto('https://awamotos.com/');
    
    // Wait for the header to load
    await page.waitForSelector('.awa-site-header');

    const html = await page.evaluate(() => {
        const el = document.querySelector('.awa-site-header');
        return el ? el.innerHTML : null;
    });

    const fs = require('fs');
    fs.writeFileSync('header.html', html);
    await browser.close();
})();