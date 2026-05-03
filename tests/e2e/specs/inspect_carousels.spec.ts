import { test } from '@playwright/test';

test('Inspect Carousels', async ({ page }) => {
    await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(5000);
    
    const carousels = await page.evaluate(() => {
        const results = [];
        
        // Swiper
        document.querySelectorAll('.swiper-container').forEach((el, index) => {
            results.push({
                type: 'Swiper',
                classes: el.className
            });
        });
        
        // Owl
        document.querySelectorAll('.owl-carousel').forEach((el, index) => {
            const nextBtn = el.querySelector('.owl-next, .owl-nav .owl-next');
            const prevBtn = el.querySelector('.owl-prev, .owl-nav .owl-prev');
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
            results.push({
                type: 'Slick',
                classes: el.className
            });
        });

        return results;
    });
    
    console.log(JSON.stringify(carousels, null, 2));
});
