const { chromium } = require('playwright');
const fs = require('fs');
const OUT = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/tests/e2e/audit_results';
fs.mkdirSync(OUT, { recursive: true });
const BASE = 'https://awamotos.com/';

async function auditDesktop(browser) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(BASE, { waitUntil: 'networkidle', timeout: 45000 });
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${OUT}/before_desktop_header.png`, clip: { x:0,y:0,width:1440,height:160 } });
  await page.screenshot({ path: `${OUT}/before_desktop_full.png`, fullPage: false });
  const report = await page.evaluate(() => {
    const cs = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return { error: 'NOT FOUND' };
      const s = window.getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return {
        display:s.display, position:s.position, zIndex:s.zIndex,
        overflow:s.overflow+'/'+s.overflowX+'/'+s.overflowY,
        width:s.width, maxWidth:s.maxWidth, height:s.height,
        padding:[s.paddingTop,s.paddingRight,s.paddingBottom,s.paddingLeft].join(' '),
        margin:[s.marginTop,s.marginRight,s.marginBottom,s.marginLeft].join(' '),
        borderTop:s.borderTop, borderBottom:s.borderBottom,
        background:s.backgroundColor, boxSizing:s.boxSizing,
        offsetW:el.offsetWidth, offsetH:el.offsetHeight,
        scrollW:el.scrollWidth,
        rect:{x:Math.round(r.x),y:Math.round(r.y),w:Math.round(r.width),h:Math.round(r.height)},
      };
    };
    const checks = {
      'page-header':           cs('.page-header'),
      'awa-site-header':       cs('.awa-site-header'),
      'header-content':        cs('header.page-header .header.content'),
      'logo':                  cs('.logo, a.logo, .awa-logo'),
      'searchbar':             cs('#search_mini_form, .block-search'),
      'minicart':              cs('.minicart-wrapper'),
      'nav-sections':          cs('.nav-sections'),
      'verticalmenu-nav':      cs('.navigation.verticalmenu, nav.verticalmenu'),
      'verticalmenu-ul':       cs('ul.togge-menu, ul.list-category-dropdown'),
      'awa-vertical-extra':    cs('.awa-vertical-extra, #menu\\.vertical\\.extra'),
      'awa-extra-nav':         cs('.awa-vertical-extra-menu'),
      'top-navigation':        cs('.navigation:not(.verticalmenu)'),
    };
    checks['_border_analysis'] = {
      pageHeaderBorderBottom: document.querySelector('.page-header') ? window.getComputedStyle(document.querySelector('.page-header')).borderBottom : null,
      awsHeaderBorderBottom: document.querySelector('.awa-site-header') ? window.getComputedStyle(document.querySelector('.awa-site-header')).borderBottom : null,
      navSectionsBorderTop: document.querySelector('.nav-sections') ? window.getComputedStyle(document.querySelector('.nav-sections')).borderTop : null,
    };
    checks['_overflow'] = {
      htmlOverflow: window.getComputedStyle(document.documentElement).overflow,
      bodyOverflow: window.getComputedStyle(document.body).overflow,
      bodyScrollW: document.body.scrollWidth,
      bodyOffsetW: document.body.offsetWidth,
      isHorizScroll: document.body.scrollWidth > document.body.offsetWidth,
    };
    const hc = document.querySelector('.header.content');
    checks['_header_children'] = hc ? Array.from(hc.children).map(c=>({
      tag:c.tagName, cls:c.className.substring(0,60),
      w:c.offsetWidth, h:c.offsetHeight,
      x:Math.round(c.getBoundingClientRect().x),
    })) : null;
    return checks;
  });
  fs.writeFileSync(`${OUT}/desktop_computed_styles.json`, JSON.stringify(report, null, 2));
  console.log('DESKTOP:', JSON.stringify(report, null, 2));

  // open vertical menu via JS and screenshot
  try {
    await page.evaluate(() => {
      const ul = document.querySelector('ul.togge-menu, ul.list-category-dropdown');
      if (ul) { ul.style.cssText += 'display:grid!important;max-height:600px!important;opacity:1!important;visibility:visible!important'; }
      const wrap = document.querySelector('.navigation.verticalmenu, nav.verticalmenu');
      if (wrap) { wrap.style.cssText += 'visibility:visible!important;opacity:1!important'; }
    });
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${OUT}/before_desktop_vmenu_open.png`, clip: { x:0,y:0,width:440,height:750 } });
  } catch(e) { console.log('vmenu force-open err:', e.message); }

  // hover on horizontal nav link
  try {
    const topLink = await page.$('.navigation:not(.verticalmenu) li.level-top > a');
    if (topLink) {
      await topLink.hover();
      await page.waitForTimeout(400);
      await page.screenshot({ path: `${OUT}/before_desktop_topnav_hover.png`, clip: {x:0,y:80,width:1440,height:180} });
    }
  } catch(e) { console.log('top nav hover err:', e.message); }

  await ctx.close();
}

async function auditMobile(browser) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await ctx.newPage();
  await page.goto(BASE, { waitUntil: 'networkidle', timeout: 45000 });
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${OUT}/before_mobile_full.png`,   fullPage: false });
  await page.screenshot({ path: `${OUT}/before_mobile_header.png`, clip: { x:0,y:0,width:390,height:130 } });
  const rep = await page.evaluate(() => {
    const cs = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return { error:'NOT FOUND' };
      const s = window.getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return { display:s.display, width:s.width, height:s.height,
        padding:[s.paddingTop,s.paddingRight,s.paddingBottom,s.paddingLeft].join(' '),
        overflow:s.overflow+'/'+s.overflowX, offsetW:el.offsetWidth, offsetH:el.offsetHeight,
        scrollW:el.scrollWidth, rect:{x:Math.round(r.x),y:Math.round(r.y),w:Math.round(r.width),h:Math.round(r.height)} };
    };
    return {
      'page-header': cs('.page-header'),
      'header.content': cs('.header.content'),
      'logo': cs('.logo, a.logo'),
      'nav-toggle': cs('.nav-toggle'),
      'minicart': cs('.minicart-wrapper'),
      'body-overflow': { scrollW:document.body.scrollWidth, offsetW:document.body.offsetWidth, isHoriz:document.body.scrollWidth>document.body.offsetWidth },
    };
  });
  fs.writeFileSync(`${OUT}/mobile_computed_styles.json`, JSON.stringify(rep, null, 2));
  console.log('MOBILE:', JSON.stringify(rep, null, 2));
  await ctx.close();
}

(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox','--disable-gpu','--disable-dev-shm-usage'] });
  try {
    console.log('=== DESKTOP AUDIT ===');
    await auditDesktop(browser);
    console.log('\n=== MOBILE AUDIT ===');
    await auditMobile(browser);
    console.log('\n✅ Done. Files:', OUT);
  } catch(e) { console.error('ERROR:', e); }
  finally { await browser.close(); }
})();
