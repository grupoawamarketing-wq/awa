const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const OUT = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/tests/e2e/audit_results';
fs.mkdirSync(OUT, { recursive: true });

const pages = [
  { name: 'home',     url: 'https://awamotos.com/' },
  { name: 'plp',      url: 'https://awamotos.com/bauletos.html' },
  { name: 'pdp',      url: 'https://awamotos.com/bauleto-givi-e30-monolock.html' },
  { name: 'login',    url: 'https://awamotos.com/customer/account/login/' },
];

(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });

  // ── DESKTOP ──────────────────────────────────────────────────────────────
  const desktop = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const dp = await desktop.newPage();

  // Homepage — closed state
  await dp.goto(pages[0].url, { waitUntil: 'networkidle', timeout: 30000 });
  await dp.screenshot({ path: `${OUT}/home_desktop_closed.png`, fullPage: false });

  // Homepage — try to open the vertical menu via JS
  await dp.evaluate(() => {
    const ul = document.querySelector('ul.togge-menu.list-category-dropdown');
    if (ul) {
      ul.classList.add('menu-open', 'vmm-open');
      ul.style.removeProperty('display');
      ul.style.setProperty('display', 'grid', 'important');
      ul.style.setProperty('max-height', '600px', 'important');
      ul.style.setProperty('opacity', '1', 'important');
      ul.style.setProperty('visibility', 'visible', 'important');
    }
  });
  await dp.waitForTimeout(300);
  await dp.screenshot({ path: `${OUT}/home_desktop_menu_open.png`, fullPage: false });
  // fullpage
  await dp.screenshot({ path: `${OUT}/home_desktop_full.png`, fullPage: true });

  // Report computed style conflicts
  const conflicts = await dp.evaluate(() => {
    const checks = [
      { sel: '.awa-site-header, .page-header', label: 'Header' },
      { sel: '.page-wrapper .modals-wrapper', label: 'Modal wrapper' },
      { sel: '.back-to-top, .scroll-to-top, #btn-to-top', label: 'Back to top' },
      { sel: '.page-wrapper .awa-site-header', label: 'AWA Header' },
      { sel: 'ul.togge-menu.list-category-dropdown', label: 'Vertical menu UL' },
      { sel: '.our_categories.title-category-dropdown', label: 'Departamentos button' },
    ];
    return checks.map(c => {
      const el = document.querySelector(c.sel);
      if (!el) return { label: c.label, error: 'NOT FOUND' };
      const s = getComputedStyle(el);
      return {
        label: c.label,
        display: s.display,
        position: s.position,
        zIndex: s.zIndex,
        visibility: s.visibility,
        opacity: s.opacity,
        overflow: s.overflow,
        width: el.offsetWidth,
        height: el.offsetHeight,
      };
    });
  });
  console.log('DESKTOP CONFLICTS:\n', JSON.stringify(conflicts, null, 2));

  // Other pages
  for (const pg of pages.slice(1)) {
    await dp.goto(pg.url, { waitUntil: 'networkidle', timeout: 30000 });
    await dp.screenshot({ path: `${OUT}/${pg.name}_desktop.png`, fullPage: true });
  }
  await desktop.close();

  // ── MOBILE ───────────────────────────────────────────────────────────────
  const mobile = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const mp = await mobile.newPage();
  for (const pg of pages) {
    await mp.goto(pg.url, { waitUntil: 'networkidle', timeout: 30000 });
    await mp.screenshot({ path: `${OUT}/${pg.name}_mobile.png`, fullPage: true });
  }
  await mobile.close();

  await browser.close();
  console.log('Audit complete — files in:', OUT);
})();
