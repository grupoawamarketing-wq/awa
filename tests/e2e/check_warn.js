const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ 
    executablePath: '/home/deploy/.cache/ms-playwright/chromium_headless_shell-1208/chrome-headless-shell-linux64/chrome-headless-shell',
    args: ['--no-sandbox', '--disable-dev-shm-usage']
  });
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await page.waitForTimeout(2000);
  
  const r = await page.evaluate(() => {
    const hamburgerSels = ['.awa-header-mobile-toggle','.awa-btn-menu','[data-trigger="navigation"]','.action.nav-toggle','.nav-toggle','.awa-hamburger','[aria-label*="menu" i]','.header-toggle','.btn-menu','button.awa-nav-toggle'];
    const promoSels = ['.awa-b2b-promo-bar','.awa-utility-bar','.awa-topbar','.top-bar','.promo-bar','.top-header-bar','[data-awa-header-utility-legacy]','.awa-utility-bar-legacy'];
    const m = (sels) => {
      const r = {};
      for (const sel of sels) {
        const el = document.querySelector(sel);
        if (el) {
          const rect = el.getBoundingClientRect();
          r[sel] = `h=${Math.round(rect.height)} disp=${getComputedStyle(el).display}`;
        }
      }
      return Object.keys(r).length ? r : {none: 'NOT_FOUND'};
    };
    return { hamburger: m(hamburgerSels), promo: m(promoSels) };
  });
  
  console.log('\n[Mobile 375] Hamburger:', JSON.stringify(r.hamburger, null, 2));
  console.log('[Mobile 375] Promo bar:', JSON.stringify(r.promo, null, 2));
  
  await page.setViewportSize({ width: 1366, height: 768 });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await page.waitForTimeout(1500);
  const r2 = await page.evaluate(() => {
    const sels = ['.awa-b2b-promo-bar','.awa-utility-bar','.awa-topbar','.top-bar','.awa-utility-bar-legacy','[data-awa-header-utility-legacy]','.top-header'];
    const res = {};
    for (const sel of sels) {
      const el = document.querySelector(sel);
      if (el) res[sel] = `h=${Math.round(el.getBoundingClientRect().height)} disp=${getComputedStyle(el).display}`;
    }
    return Object.keys(res).length ? res : {none: 'NOT_FOUND'};
  });
  console.log('[Desktop 1366] Promo bar:', JSON.stringify(r2, null, 2));
  await browser.close();
})();
