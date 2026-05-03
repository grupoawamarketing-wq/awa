const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1440, height: 900 });
    
    await page.goto('https://awamotos.com/protetor-de-carter-xre-190-mod-2016-2022-preto-fosco-3098.html', { waitUntil: 'networkidle' });
    
    const info = await page.evaluate(() => {
        const tabs = document.querySelector('.product.data.items');
        if (!tabs) return null;
        
        const computed = window.getComputedStyle(tabs);
        
        return {
            height: computed.height,
            minHeight: computed.minHeight,
            padding: computed.padding,
            margin: computed.margin,
            boxSizing: computed.boxSizing
        };
    });
    
    console.log(info);
    await browser.close();
})();
