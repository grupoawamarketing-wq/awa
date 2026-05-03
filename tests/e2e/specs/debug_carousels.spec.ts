import { test } from '@playwright/test';

test('Debug Carousels', async ({ page }) => {
    await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(5000);
    
    const info = await page.evaluate(() => {
        const slide = document.querySelector('.rokan-product-heading').parentElement.querySelector('.swiper-slide');
        if (!slide) return 'no slide found';
        
        const computed = window.getComputedStyle(slide);
        return {
            className: slide.className,
            inlineStyle: slide.getAttribute('style'),
            computedWidth: computed.width,
            computedFlex: computed.flex,
            computedMinWidth: computed.minWidth,
            computedMaxWidth: computed.maxWidth,
            computedFlexShrink: computed.flexShrink,
            computedFlexBasis: computed.flexBasis,
            html: slide.outerHTML.substring(0, 300)
        };
    });
    
    console.log(JSON.stringify(info, null, 2));
});
