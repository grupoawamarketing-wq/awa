import { test } from '@playwright/test';

test('Debug Swiper Config', async ({ page }) => {
    await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(5000);
    
    const info = await page.evaluate(() => {
        const swiperEl = document.querySelector('.rokan-product-heading').parentElement.querySelector('.swiper-container');
        if (!swiperEl) return 'no swiper found';
        
        return {
            classes: swiperEl.className,
            dataset: Object.assign({}, swiperEl.dataset),
            html: swiperEl.outerHTML.substring(0, 500)
        };
    });
    
    console.log(JSON.stringify(info, null, 2));
});
