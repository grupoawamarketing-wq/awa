const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const PAGES = [
  { name: 'home', url: 'https://awamotos.com/' },
  { name: 'category', url: 'https://awamotos.com/bagageiros.html' },
  { name: 'search', url: 'https://awamotos.com/catalogsearch/result/?q=bagageiro' },
  { name: 'login', url: 'https://awamotos.com/b2b/account/login' },
];

const VIEWPORTS = [
  { name: 'desktop', w: 1280, h: 800 },
  { name: 'mobile', w: 375, h: 812 },
];

(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const ssDir = path.join(__dirname, 'screenshots/qa-final');
  fs.mkdirSync(ssDir, { recursive: true });
  
  const results = [];
  
  for (const vp of VIEWPORTS) {
    console.log(`\n=== ${vp.name.toUpperCase()} (${vp.w}x${vp.h}) ===`);
    
    for (const p of PAGES) {
      const page = await b.newPage();
      await page.setViewportSize({ width: vp.w, height: vp.h });
      await page.goto(p.url, { waitUntil: 'domcontentloaded', timeout: 40000 });
      await page.waitForTimeout(2000);
      
      const m = await page.evaluate(() => {
        const h3s = [...document.querySelectorAll('h3.product-name')];
        const h3Hs = h3s.map(h => Math.round(h.getBoundingClientRect().height)).filter(h => h > 0);
        const uniq = a => [...new Set(a)].sort((x,y)=>x-y);
        const range = a => a.length ? Math.max(...a) - Math.min(...a) : 0;
        const sorterEl = document.querySelector('.sorter select, .sorter-options');
        const bodyClass = document.body.className.slice(0, 100);
        return {
          bodyClass,
          h3Count: h3s.length,
          h3Heights: uniq(h3Hs),
          h3Range: range(h3Hs),
          sorterH: sorterEl ? Math.round(sorterEl.getBoundingClientRect().height) : null,
        };
      });
      
      const isHome = p.name === 'home';
      const h3ok = m.h3Count === 0 ? null : m.h3Heights.every(h => isHome ? (h >= 43 && h <= 46) : (h >= 57 && h <= 60));
      const h3status = m.h3Count === 0 ? 'N/A' : (h3ok ? 'PASS' : 'FAIL');
      const isMobile = vp.name === 'mobile';
      const sorterOk = m.sorterH === null ? true : (isMobile ? (m.sorterH >= 36 && m.sorterH <= 52) : (m.sorterH >= 35 && m.sorterH <= 38));
      const sorterStatus = m.sorterH === null ? 'N/A' : (sorterOk ? 'PASS' : 'FAIL');
      
      console.log(`${p.name.toUpperCase()}: H3=[${m.h3Heights.join(',')}] range=${m.h3Range}px ${h3status}${m.sorterH !== null ? ' | sorter=' + m.sorterH + 'px ' + sorterStatus : ''}`);
      
      const ssFile = path.join(ssDir, `${p.name}-${vp.name}.png`);
      await page.screenshot({ path: ssFile, fullPage: false });
      
      results.push({ page: p.name, vp: vp.name, h3status, sorterStatus, h3Heights: m.h3Heights, h3Range: m.h3Range, sorterH: m.sorterH });
      await page.close();
    }
  }
  
  await b.close();
  
  console.log('\n====== FINAL QA SUMMARY ======');
  const fails = results.filter(r => r.h3status === 'FAIL' || r.sorterStatus === 'FAIL');
  if (fails.length === 0) {
    console.log('ALL PASS ✓');
  } else {
    console.log('FAILURES:');
    fails.forEach(r => console.log(`  ${r.page}/${r.vp}: H3=${r.h3status} sorter=${r.sorterStatus}`));
  }
})().catch(e => { console.error(e.message); process.exit(1); });
