import { chromium } from 'playwright';

const browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 60000 });
await page.waitForTimeout(4000);

const metrics = await page.evaluate(() => {
  const HEADER_ROW_SELECTOR = '.awa-main-header__inner[data-awa-header-row], .wp-header[data-awa-header-row]';
  function g(s) {
    const el = document.querySelector(s);
    if (el === null) return null;
    const r = el.getBoundingClientRect();
    return { w: Math.round(r.width), l: Math.round(r.left), r: Math.round(r.right), h: Math.round(r.height) };
  }
  const wp = document.querySelector(HEADER_ROW_SELECTOR);
  const cs = wp ? getComputedStyle(wp) : null;
  const sr = g('.awa-header-search-col');
  return {
    vw: innerWidth,
    wpHeader: wp ? { display: cs.display, grid: cs.gridTemplateColumns, maxW: cs.maxWidth, ...g(HEADER_ROW_SELECTOR) } : 'MISSING',
    topbar: g('.awa-b2b-promo-bar__inner'),
    navbar: g('.awa-nav-bar__inner'),
    logo: g('.awa-header-brand-cell, .col-md-2.awa-header-brand'),
    search: sr ? { ...sr, center: Math.round(sr.l + sr.w / 2) } : null,
    rightCol: g('.awa-header-right-col'),
    vpCenter: Math.round(innerWidth / 2),
    searchOffset: sr ? Math.round((sr.l + sr.w / 2) - innerWidth / 2) : null
  };
});

console.log(JSON.stringify(metrics, null, 2));
await page.screenshot({ path: '/tmp/v11_hdr.png', clip: { x: 0, y: 0, width: 1440, height: 200 } });
console.log('SCREENSHOT: /tmp/v11_hdr.png');
await browser.close();
