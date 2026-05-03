const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1440, height: 900 });
    
    console.log('Navigating to Category Page...');
    await page.goto('https://awamotos.com/protetores-de-carter.html', { waitUntil: 'networkidle' });
    await page.screenshot({ path: 'screenshots/audit_plp_desktop.png', fullPage: true });

    console.log('Extracting PLP info...');
    const plpInfo = await page.evaluate(() => {
        const grid = document.querySelector('.products-grid');
        const sidebar = document.querySelector('.sidebar-main');
        const toolbar = document.querySelector('.toolbar-products');
        return {
            hasGrid: !!grid,
            gridColumns: grid ? window.getComputedStyle(grid).gridTemplateColumns : null,
            gridGap: grid ? window.getComputedStyle(grid).gap : null,
            hasSidebar: !!sidebar,
            sidebarWidth: sidebar ? window.getComputedStyle(sidebar).width : null,
            hasToolbar: !!toolbar,
            toolbarDisplay: toolbar ? window.getComputedStyle(toolbar).display : null
        };
    });
    console.log('PLP Info:', plpInfo);

    console.log('Finding a product URL...');
    const productUrl = await page.evaluate(() => {
        const link = document.querySelector('.product-item-link');
        return link ? link.href : null;
    });

    if (productUrl) {
        console.log('Navigating to Product Page:', productUrl);
        await page.goto(productUrl, { waitUntil: 'networkidle' });
        await page.screenshot({ path: 'screenshots/audit_pdp_desktop.png', fullPage: true });
        
        console.log('Extracting PDP info...');
        const pdpInfo = await page.evaluate(() => {
            const media = document.querySelector('.product.media');
            const info = document.querySelector('.product-info-main');
            const tabs = document.querySelector('.product.data.items');
            return {
                mediaWidth: media ? window.getComputedStyle(media).width : null,
                infoWidth: info ? window.getComputedStyle(info).width : null,
                tabsDisplay: tabs ? window.getComputedStyle(tabs).display : null,
            };
        });
        console.log('PDP Info:', pdpInfo);
    } else {
        console.log('No product found on category page.');
    }

    await browser.close();
})();
