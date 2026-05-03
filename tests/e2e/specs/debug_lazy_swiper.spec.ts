import { test } from '@playwright/test';

test('Debug Lazy Swiper', async ({ page }) => {
    await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
    
    // scroll down slowly to trigger lazy load
    for (let i = 0; i < 10; i++) {
        await page.mouse.wheel(0, 500);
        await page.waitForTimeout(500);
    }
    
    const info = await page.evaluate(() => {
        const swipers = Array.from(document.querySelectorAll('.swiper-container, .products-swiper, .swiper, .hot-deal-slide')).map(el => ({
            classes: el.className,
        }));
        return swipers;
    });
    
    console.log(JSON.stringify(info, null, 2));
});
