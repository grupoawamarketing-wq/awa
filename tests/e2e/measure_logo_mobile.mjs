import { chromium } from 'playwright';
const browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const page = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
await page.goto('https://awamotos.com/?v=logofix2', { waitUntil: 'domcontentloaded', timeout: 60000 });
await page.waitForTimeout(2500);
const data = await page.evaluate(() => {
  const g = (s) => {
    const el = document.querySelector(s);
    if (!el) return null;
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return { w: Math.round(r.width), h: Math.round(r.height), l: Math.round(r.left), t: Math.round(r.top), display: cs.display, maxW: cs.maxWidth, maxH: cs.maxHeight };
  };
  return {
    brand: g('.awa-header-brand-cell'),
    logoWrap: g('.awa-header-brand-cell .logo'),
    logoImg: g('.awa-header-brand-cell .logo img'),
    toggle: g('.awa-header-mobile-toggle'),
    cart: g('.awa-header-cart-link')
  };
});
console.log(JSON.stringify(data, null, 2));
await page.screenshot({ path: '/tmp/mobile-logo-fix2.png', clip: { x: 0, y: 0, width: 390, height: 220 } });
await browser.close();
