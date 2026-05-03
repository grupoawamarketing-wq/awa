const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('https://awamotos.com/', { waitUntil: 'networkidle' });
    await page.waitForTimeout(5000);

    // Main slider
    const mainSlider = page.locator('.slidebanner').first();
    if (await mainSlider.isVisible()) {
        await mainSlider.screenshot({ path: 'screenshots/main_slider_desktop_fixed.png' });
    }

    // First product carousel
    const productCarousel = page.locator('.rokan-product-heading').first().locator('..');
    if (await productCarousel.isVisible()) {
        await productCarousel.scrollIntoViewIfNeeded();
        await page.waitForTimeout(1000);
        await productCarousel.screenshot({ path: 'screenshots/product_carousel_desktop_fixed.png' });
    }
    
    await browser.close();
})();
