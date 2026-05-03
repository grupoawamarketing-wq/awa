const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1440, height: 900 });
    
    await page.goto('https://awamotos.com/protetor-de-carter-xre-190-mod-2016-2022-preto-fosco-3098.html', { waitUntil: 'networkidle' });
    
    const info = await page.evaluate(() => {
        const media = document.querySelector('.product.media');
        const infoMain = document.querySelector('.product-info-main');
        const parent = media ? media.parentElement : null;
        
        return {
            parentTag: parent ? parent.tagName : null,
            parentClasses: parent ? parent.className : null,
            parentDisplay: parent ? window.getComputedStyle(parent).display : null,
            parentFlexWrap: parent ? window.getComputedStyle(parent).flexWrap : null,
            parentWidth: parent ? window.getComputedStyle(parent).width : null,
            mediaWidth: media ? window.getComputedStyle(media).width : null,
            infoMainWidth: infoMain ? window.getComputedStyle(infoMain).width : null,
        };
    });
    
    console.log('Layout Info:', info);
    await browser.close();
})();
