import { test } from '@playwright/test';

test('Screenshot Carousels', async ({ page }) => {
    await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(5000); // let carousels init

    // Hide cookie banner if exists to not block view
    await page.evaluate(() => {
        const cookies = document.querySelector('.cc-window');
        if (cookies) cookies.remove();
        
        // hide fixed bottom buttons like whatsapp
        const wa = document.querySelector('.whatsapp-button');
        if (wa) wa.remove();
    });

    // Main slider
    const mainSlider = page.locator('.slidebanner').first();
    if (await mainSlider.isVisible()) {
        await mainSlider.screenshot({ path: 'screenshots/main_slider_desktop.png' });
    }

    // First product carousel
    const productCarousel = page.locator('.rokan-product-heading').first().locator('..');
    if (await productCarousel.isVisible()) {
        await productCarousel.scrollIntoViewIfNeeded();
        await page.waitForTimeout(1000);
        await productCarousel.screenshot({ path: 'screenshots/product_carousel_desktop.png' });
    }
});
