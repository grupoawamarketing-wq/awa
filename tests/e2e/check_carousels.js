const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
    
    // Wait for carousels to load
    await page.waitForTimeout(5000);
    
    const carousels = await page.evaluate(() => {
        const results = [];
        
        // Swiper
        document.querySelectorAll('.swiper-container').forEach((el, index) => {
            const nextBtn = el.querySelector('.swiper-button-next');
            const prevBtn = el.querySelector('.swiper-button-prev');
            const pagination = el.querySelector('.swiper-pagination');
            
            results.push({
                type: 'Swiper',
                index,
                hasNextBtn: !!nextBtn,
                hasPrevBtn: !!prevBtn,
                hasPagination: !!pagination,
                classes: el.className
            });
        });
        
        // Owl
        document.querySelectorAll('.owl-carousel').forEach((el, index) => {
            const nextBtn = el.querySelector('.owl-next');
            const prevBtn = el.querySelector('.owl-prev');
            const pagination = el.querySelector('.owl-dots');
            
            results.push({
                type: 'Owl',
                index,
                hasNextBtn: !!nextBtn,
                hasPrevBtn: !!prevBtn,
                hasPagination: !!pagination,
                classes: el.className
            });
        });

        // Slick
        document.querySelectorAll('.slick-slider').forEach((el, index) => {
            const nextBtn = el.querySelector('.slick-next');
            const prevBtn = el.querySelector('.slick-prev');
            const pagination = el.querySelector('.slick-dots');
            
            results.push({
                type: 'Slick',
                index,
                hasNextBtn: !!nextBtn,
                hasPrevBtn: !!prevBtn,
                hasPagination: !!pagination,
                classes: el.className
            });
        });

        return results;
    });
    
    console.log(JSON.stringify(carousels, null, 2));
    
    await browser.close();
})();
