const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto('https://awamotos.com/');
    
    // Wait for the header to load
    await page.waitForSelector('.awa-site-header');

    const width = await page.evaluate(() => {
        const el = document.querySelector('.awa-header-primary-nav');
        if (!el) return null;
        const comp = window.getComputedStyle(el);
        return comp.width;
    });

    console.log('Primary nav width:', width);

    const menuLeftWidth = await page.evaluate(() => {
        const el = document.querySelector('.awa-header-categories');
        if (!el) return null;
        const comp = window.getComputedStyle(el);
        return comp.width;
    });

    console.log('Menu left width:', menuLeftWidth);

    await browser.close();
})();