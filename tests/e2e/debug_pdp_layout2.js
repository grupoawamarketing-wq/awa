const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1440, height: 900 });
    
    await page.goto('https://awamotos.com/protetor-de-carter-xre-190-mod-2016-2022-preto-fosco-3098.html', { waitUntil: 'networkidle' });
    
    const info = await page.evaluate(() => {
        const media = document.querySelector('.product.media');
        const infoMain = document.querySelector('.product-info-main');
        
        return {
            mediaParent: media ? media.parentElement.className : null,
            infoParent: infoMain ? infoMain.parentElement.className : null,
            mediaWidth: media ? window.getComputedStyle(media).width : null,
            infoWidth: infoMain ? window.getComputedStyle(infoMain).width : null,
        };
    });
    
    console.log('Layout Info:', info);
    await browser.close();
})();
