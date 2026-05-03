const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto('https://awamotos.com/protetor-de-carter-xre-190-mod-2016-2022-preto-fosco-3098.html', { waitUntil: 'networkidle' });
    
    const info = await page.evaluate(() => {
        const media = document.querySelector('.product.media');
        const infoMain = document.querySelector('.product-info-main');
        
        return {
            mediaHTML: media ? media.parentElement.outerHTML.substring(0, 150) : null,
            infoHTML: infoMain ? infoMain.parentElement.outerHTML.substring(0, 150) : null,
        };
    });
    
    console.log(info);
    await browser.close();
})();
