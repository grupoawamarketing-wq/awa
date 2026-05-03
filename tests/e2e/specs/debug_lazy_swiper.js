const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto('https://awamotos.com/', { waitUntil: 'networkidle' });
    
    await page.waitForTimeout(5000);
    
    for (let i = 0; i < 15; i++) {
        await page.mouse.wheel(0, 300);
        await page.waitForTimeout(300);
    }
    
    const info = await page.evaluate(() => {
        const swipers = Array.from(document.querySelectorAll('.swiper-container, .products-swiper, .swiper, .hot-deal-slide')).map(el => ({
            classes: el.className,
        }));
        return swipers;
    });
    
    console.log(JSON.stringify(info, null, 2));
    await browser.close();
})();
