import { chromium } from '@playwright/test';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
await page.goto('https://awamotos.com/?v=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(6000);

const cookie = page.locator('#awa-cookie-accept, .awa-cookie-banner__btn--accept').first();
if (await cookie.isVisible({ timeout: 2000 }).catch(() => false)) {
  await cookie.click({ force: true });
}

await page.evaluate(() => {
  document.querySelectorAll('link[rel="stylesheet"]').forEach((l) => { l.media = 'all'; });
});
await page.waitForTimeout(3000);

await page.locator('.rokan-bestseller, .rokan-newproduct').first().scrollIntoViewIfNeeded();
await page.waitForTimeout(2000);

const data = await page.evaluate(() => {
  const section = document.querySelector('.rokan-bestseller') || document.querySelector('.rokan-newproduct');
  const cards = section ? [...section.querySelectorAll('.item-product')].slice(0, 4) : [];

  return {
    sectionClass: section?.className || null,
    owlLoaded: !!section?.querySelector('.owl-loaded'),
    owlItems: section?.querySelectorAll('.owl-item').length || 0,
    cards: cards.map((card, i) => {
      const img = card.querySelector('.product-image-photo, .product-thumb img');
      const thumb = card.querySelector('.product-thumb');
      const wrap = card.querySelector('.product-image-wrapper');
      const cs = img ? getComputedStyle(img) : {};
      const tcs = thumb ? getComputedStyle(thumb) : {};
      return {
        i,
        title: card.querySelector('.product-name, .product-item-link')?.textContent?.trim().slice(0, 60),
        imgSrc: img?.getAttribute('src')?.slice(-80),
        imgNatural: img ? `${img.naturalWidth}x${img.naturalHeight}` : null,
        imgBox: img ? `${Math.round(img.getBoundingClientRect().width)}x${Math.round(img.getBoundingClientRect().height)}` : null,
        thumbBox: thumb ? `${Math.round(thumb.getBoundingClientRect().width)}x${Math.round(thumb.getBoundingClientRect().height)}` : null,
        thumbMinH: tcs.minHeight,
        imgPos: cs.position,
        imgFit: cs.objectFit,
        imgOpacity: cs.opacity,
        imgWidth: cs.width,
        imgMaxWidth: cs.maxWidth,
        wrapPad: wrap ? getComputedStyle(wrap).paddingBottom : null,
      };
    }),
  };
});

console.log(JSON.stringify(data, null, 2));
await page.screenshot({ path: '/tmp/carousel-bug-audit.png', fullPage: false });
await browser.close();
