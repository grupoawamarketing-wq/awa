import { chromium } from '@playwright/test';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
await page.goto('https://awamotos.com/', { waitUntil: 'networkidle', timeout: 60000 });
await page.waitForTimeout(3000);

const cookieBtn = page.locator('#awa-cookie-accept, .awa-cookie-banner__btn--accept').first();
if (await cookieBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
  await cookieBtn.click({ force: true }).catch(() => {});
}

await page.evaluate(() => {
  document.querySelectorAll('link[rel="stylesheet"][media="print"]').forEach((l) => { l.media = 'all'; });
});
await page.waitForTimeout(2000);

const data = await page.evaluate(() => {
  const prev = document.querySelector('.awa-category-carousel__prev');
  const next = document.querySelector('.awa-category-carousel__next');
  const inspect = (el, name) => {
    if (!el) return { name, found: false };
    const cs = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    return {
      name,
      found: true,
      w: Math.round(r.width),
      h: Math.round(r.height),
      display: cs.display,
      visibility: cs.visibility,
      opacity: cs.opacity,
      position: cs.position,
      width: cs.width,
      height: cs.height,
      minWidth: cs.minWidth,
      minHeight: cs.minHeight,
      overflow: cs.overflow,
      parent: el.parentElement?.className,
      html: el.outerHTML.slice(0, 200),
    };
  };

  const img = document.querySelector('.rokan-bestseller .product-image-photo');
  const imgCs = img ? getComputedStyle(img) : null;
  const thumb = document.querySelector('.rokan-bestseller .product-thumb');
  const thumbR = thumb?.getBoundingClientRect();

  return {
    prev: inspect(prev, 'prev'),
    next: inspect(next, 'next'),
    productImg: img ? {
      w: Math.round(img.getBoundingClientRect().width),
      h: Math.round(img.getBoundingClientRect().height),
      position: imgCs.position,
      objectFit: imgCs.objectFit,
      thumb: thumbR ? `${Math.round(thumbR.width)}x${Math.round(thumbR.height)}` : null,
    } : null,
  };
});

console.log(JSON.stringify(data, null, 2));
await page.screenshot({ path: '/tmp/carousel-verify-desktop.png' });
await browser.close();
