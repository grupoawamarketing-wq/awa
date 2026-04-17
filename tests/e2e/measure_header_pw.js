const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(6000);
  const m = await page.evaluate(() => {
    const HEADER_ROW_SELECTOR = '.awa-main-header__inner[data-awa-header-row], .wp-header[data-awa-header-row]';
    const wp = document.querySelector(HEADER_ROW_SELECTOR);
    if (!wp) return { error: 'no header row', body: document.body.innerText.substring(0, 300) };
    const cs = getComputedStyle(wp);
    const r = wp.getBoundingClientRect();
    const get = (sel) => { const el = document.querySelector(sel); return el ? el.getBoundingClientRect() : null; };
    const round = (rect) => rect ? { w: Math.round(rect.width), l: Math.round(rect.left), r: Math.round(rect.right) } : null;
    const sr = get('.awa-header-search-col');
    return {
      vw: innerWidth,
      display: cs.display,
      grid: cs.gridTemplateColumns,
      maxW: cs.maxWidth,
      wp: { w: Math.round(r.width), l: Math.round(r.left), h: Math.round(r.height) },
      topbar: round(get('.awa-b2b-promo-bar__inner')),
      navbar: round(get('.awa-nav-bar__inner')),
      logo: round(get('.awa-header-brand-cell, .col-md-2.awa-header-brand')),
      search: sr ? { w: Math.round(sr.width), l: Math.round(sr.left), center: Math.round(sr.left + sr.width / 2) } : null,
      rightCol: round(get('.awa-header-right-col')),
      vpCenter: Math.round(innerWidth / 2),
      searchOffset: sr ? Math.round((sr.left + sr.width / 2) - innerWidth / 2) : null,
      axisAlign: (() => {
        const wpL = Math.round(r.left);
        const tbL = round(get('.awa-b2b-promo-bar__inner'));
        const navL = round(get('.awa-nav-bar__inner'));
        return { wpLeft: wpL, topbarLeft: tbL ? tbL.l : null, navbarLeft: navL ? navL.l : null };
      })()
    };
  });
  console.log(JSON.stringify(m, null, 2));
  await page.screenshot({ path: '/tmp/v11_header.png', clip: { x: 0, y: 0, width: 1440, height: 200 } });
  console.log('Screenshot saved: /tmp/v11_header.png');
  await browser.close();
})();
