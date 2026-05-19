import { chromium } from '@playwright/test';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
await page.goto('https://awamotos.com/?v=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(8000);

await page.evaluate(() => {
  document.querySelectorAll('link[rel="stylesheet"]').forEach((l) => { l.media = 'all'; });
});
await page.waitForTimeout(3000);

await page.waitForFunction(() => document.querySelector('.rokan-bestseller .owl-loaded'), { timeout: 30000 }).catch(() => {});

const data = await page.evaluate(() => {
  const m = (sel) => {
    const el = document.querySelector(sel);
    if (!el) return null;
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return { w: Math.round(r.width), h: Math.round(r.height), display: cs.display, minH: cs.minHeight, position: cs.position, objectFit: cs.objectFit };
  };
  return {
    catPrev: m('.awa-category-carousel__prev'),
    img: m('.rokan-bestseller .product-image-photo'),
    thumb: m('.rokan-bestseller .product-thumb'),
    owl: m('.rokan-bestseller .owl-stage-outer'),
    newImg: m('.rokan-newproduct .product-image-photo'),
  };
});

console.log(JSON.stringify(data, null, 2));
await page.screenshot({ path: '/tmp/carousel-final-desktop.png', fullPage: false });
await browser.close();
