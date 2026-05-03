const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(5000);
    
    const info = await page.evaluate(() => {
        const swipers = Array.from(document.querySelectorAll('.swiper-container, .products-swiper, .swiper')).map(el => ({
            classes: el.className,
            id: el.id,
            parentClass: el.parentElement.className
        }));
        return swipers;
    });
    
    console.log(JSON.stringify(info, null, 2));
    await browser.close();
})();
