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

    const navWidth = await page.evaluate(() => {
        const el = document.querySelector('.awa-header-primary-nav .navigation');
        return el ? el.getBoundingClientRect().width : null;
    });

    const navItems = await page.evaluate(() => {
        const el = document.querySelector('.awa-header-primary-nav .navigation > ul');
        return el ? el.getBoundingClientRect().width : null;
    });

    console.log('Account Prompt Height:', accountHeight);
    console.log('Account Prompt Width:', accountWidth);
    console.log('Navigation Width:', navWidth);
    console.log('Navigation UL Width:', navItems);

    await browser.close();
})();