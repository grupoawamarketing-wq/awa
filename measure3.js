const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto('https://awamotos.com/');
    
    // Wait for the header to load
    await page.waitForSelector('.awa-site-header');

    const styles = await page.evaluate(() => {
        const el = document.querySelector('.awa-header-account-prompt');
        if (!el) return null;
        const comp = window.getComputedStyle(el);
        return {
            display: comp.display,
            width: comp.width,
            height: comp.height,
            flexDirection: comp.flexDirection,
            position: comp.position,
            maxWidth: comp.maxWidth,
            minWidth: comp.minWidth
        };
    });

    console.log('Account styles:', styles);

    const textStyles = await page.evaluate(() => {
        const el = document.querySelector('.awa-header-account-prompt__text');
        if (!el) return null;
        const comp = window.getComputedStyle(el);
        return {
            display: comp.display,
            width: comp.width,
            height: comp.height,
            flexDirection: comp.flexDirection
        };
    });

    console.log('Text styles:', textStyles);

    await browser.close();
})();