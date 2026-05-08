const { chromium } = require('playwright');
const fs = require('fs');
const OUT = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/tests/e2e/audit_results';
const BASE = 'https://awamotos.com/';

(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox','--disable-gpu','--disable-dev-shm-usage'] });

  // ── Desktop ──────────────────────────────────────────────────
  const dctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const dp   = await dctx.newPage();
  await dp.goto(BASE, { waitUntil: 'networkidle', timeout: 45000 });
  await dp.waitForTimeout(1500);

  await dp.screenshot({ path: `${OUT}/after_desktop_header.png`, clip: {x:0,y:0,width:1440,height:160} });
  await dp.screenshot({ path: `${OUT}/after_desktop_full.png`, fullPage: false });

  // Force-open the vertical menu
  await dp.evaluate(() => {
    const ul = document.querySelector('ul.togge-menu, ul.list-category-dropdown');
    const nav = document.querySelector('nav.navigation.verticalmenu');
    if (ul) {
      ul.classList.add('menu-open', 'vmm-open');
      ul.style.setProperty('display', 'grid', 'important');
      ul.style.setProperty('max-height', '700px', 'important');
      ul.style.setProperty('opacity', '1', 'important');
      ul.style.setProperty('visibility', 'visible', 'important');
      ul.style.setProperty('overflow-y', 'auto', 'important');
    }
    if (nav) nav.classList.add('menu-open', 'vmm-open');
  });
  await dp.waitForTimeout(500);
  await dp.screenshot({ path: `${OUT}/after_desktop_vmenu_open.png`, clip: {x:0,y:0,width:440,height:800} });

  // Check computed styles after fix
  const afterReport = await dp.evaluate(() => {
    const cs = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return { error: 'NOT FOUND' };
      const s = window.getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return {
        display: s.display, position: s.position,
        offsetW: el.offsetWidth, offsetH: el.offsetHeight,
        rect: { x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height) },
      };
    };
    return {
      'awa-vertical-extra':  cs('.awa-vertical-extra, #menu\\.vertical\\.extra'),
      'awa-vem-extra-li':    cs('li.awa-vem-extra-li'),
      'verticalmenu-ul':     cs('ul.togge-menu'),
      'verticalmenu-nav':    cs('nav.navigation.verticalmenu'),
      'overflow': { bodyScrollW: document.body.scrollWidth, bodyOffsetW: document.body.offsetWidth, isHoriz: document.body.scrollWidth > document.body.offsetWidth },
    };
  });
  fs.writeFileSync(`${OUT}/after_computed_styles.json`, JSON.stringify(afterReport, null, 2));
  console.log('AFTER DESKTOP:', JSON.stringify(afterReport, null, 2));

  await dctx.close();

  // ── Mobile ───────────────────────────────────────────────────
  const mctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const mp   = await mctx.newPage();
  await mp.goto(BASE, { waitUntil: 'networkidle', timeout: 45000 });
  await mp.waitForTimeout(1500);
  await mp.screenshot({ path: `${OUT}/after_mobile_full.png`,   fullPage: false });
  await mp.screenshot({ path: `${OUT}/after_mobile_header.png`, clip: {x:0,y:0,width:390,height:160} });
  await mctx.close();

  await browser.close();
  console.log('✅ After-fix audit complete. Files:', OUT);
})();
