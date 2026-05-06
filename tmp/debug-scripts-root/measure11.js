const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto('https://awamotos.com/');
    
    // Wait for the header to load
    await page.waitForSelector('.awa-site-header');

    const styles = await page.evaluate(() => {
        const el = document.querySelector('.awa-header-account-prompt__text');
        if (!el) return null;
        const comp = window.getComputedStyle(el);
        let res = {};
        for(let i=0; i<comp.length; i++) {
            let key = comp[i];
            res[key] = comp.getPropertyValue(key);
        }
        return res;
    });

    const fs = require('fs');
    fs.writeFileSync('text_styles.json', JSON.stringify(styles, null, 2));

    await browser.close();
})();