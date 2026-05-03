const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1440, height: 900 });
    
    console.log('--- PDP ---');
    await page.goto('https://awamotos.com/protetor-de-carter-xre-190-mod-2016-2022-preto-fosco-3098.html', { waitUntil: 'networkidle' });
    
    const pdpEmptyBox = await page.evaluate(() => {
        // Find the element that is causing the large empty space
        // It's likely .product.data.items or something similar
        const tabs = document.querySelector('.product.info.detailed');
        return {
            classes: tabs ? tabs.className : null,
            html: tabs ? tabs.innerHTML.substring(0, 500) : null,
            height: tabs ? window.getComputedStyle(tabs).height : null
        };
    });
    console.log('PDP Tabs Box:', pdpEmptyBox);

    console.log('\n--- PLP ---');
    await page.goto('https://awamotos.com/protetores-de-carter.html', { waitUntil: 'networkidle' });
    
    const plpSearchBox = await page.evaluate(() => {
        // Find the element below the banner and above the toolbar
        const banner = document.querySelector('.category-image');
        const toolbar = document.querySelector('.toolbar-products');
        
        let el = banner ? banner.nextElementSibling : null;
        return {
            classes: el ? el.className : null,
            id: el ? el.id : null,
            html: el ? el.outerHTML.substring(0, 500) : null,
            height: el ? window.getComputedStyle(el).height : null
        };
    });
    console.log('PLP Search Box:', plpSearchBox);

    await browser.close();
})();
