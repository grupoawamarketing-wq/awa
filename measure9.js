const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto('https://awamotos.com/');
    
    // Wait for the header to load
    await page.waitForSelector('.awa-site-header');

    const accountHeight = await page.evaluate(() => {
        const el = document.querySelector('.awa-header-account-prompt');
        return el ? el.getBoundingClientRect().height : null;
    });

    const accountWidth = await page.evaluate(() => {
        const el = document.querySelector('.awa-header-account-prompt');
        return el ? el.getBoundingClientRect().width : null;
    });

    const navLinks = await page.evaluate(() => {
        const el = document.querySelector('.awa-nav-quick-links');
        return el ? el.getBoundingClientRect().width : null;
    });

    console.log('Account Prompt Height:', accountHeight);
    console.log('Account Prompt Width:', accountWidth);
    console.log('Nav Quick Links Width:', navLinks);

    await browser.close();
})();