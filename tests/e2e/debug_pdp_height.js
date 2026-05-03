const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1440, height: 900 });
    
    await page.goto('https://awamotos.com/protetor-de-carter-xre-190-mod-2016-2022-preto-fosco-3098.html', { waitUntil: 'networkidle' });
    
    const info = await page.evaluate(() => {
        const tabs = document.querySelector('.product.info.detailed');
        if (!tabs) return null;
        
        return {
            inlineStyle: tabs.getAttribute('style'),
            computedHeight: window.getComputedStyle(tabs).height,
            computedMinHeight: window.getComputedStyle(tabs).minHeight,
            children: Array.from(tabs.children).map(c => ({
                class: c.className,
                height: window.getComputedStyle(c).height,
                minHeight: window.getComputedStyle(c).minHeight,
                inlineStyle: c.getAttribute('style')
            }))
        };
    });
    
    console.log(info);
    await browser.close();
})();
