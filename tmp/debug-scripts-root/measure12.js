const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto('https://awamotos.com/');
    
    // Wait for the header to load
    await page.waitForSelector('.awa-site-header');

    const w = await page.evaluate(() => {
        const el = document.querySelector('.awa-header-right-col');
        if (!el) return null;
        const comp = window.getComputedStyle(el);
        return {
            width: comp.width,
            minWidth: comp.minWidth,
            flex: comp.flex
        };
    });

    console.log('Right col:', w);
    await browser.close();
})();