const { chromium } = require('@playwright/test');

const PAGES = [
  { name: 'home', url: 'https://awamotos.com/' },
  { name: 'category', url: 'https://awamotos.com/bagageiros.html' },
  { name: 'search', url: 'https://awamotos.com/catalogsearch/result/?q=cg+160' },
  { name: 'login', url: 'https://awamotos.com/b2b/account/login' },
];

(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const results = [];

  for (const p of PAGES) {
    const page = await b.newPage();
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(p.url, { waitUntil: 'domcontentloaded', timeout: 40000 });
    await page.waitForTimeout(2000);

    const m = await page.evaluate(() => {
      const h3s = [...document.querySelectorAll('h3.product-name')];
      const cards = [...document.querySelectorAll('.item-product')];
      const sorterEl = document.querySelector('.sorter select, .sorter-options');
      const mobileHeader = document.querySelector('.page-header, .awa-site-header');
      
      const h3Hs = h3s.map(h => Math.round(h.getBoundingClientRect().height)).filter(h => h > 0);
      const uniq = a => [...new Set(a)].sort((x,y) => x-y);
      const range = a => a.length ? Math.max(...a) - Math.min(...a) : 0;
      
      return {
        h3Count: h3s.length,
        h3Heights: uniq(h3Hs),
        h3Range: range(h3Hs),
        cardCount: cards.length,
        sorterH: sorterEl ? Math.round(parseFloat(getComputedStyle(sorterEl).height)) : null,
        headerH: mobileHeader ? Math.round(mobileHeader.getBoundingClientRect().height) : null,
      };
    });
    
    results.push({ name: p.name, ...m });
    await page.close();
  }
  
  await b.close();
  
  console.log('\n====== VISUAL QA AUDIT — Phase 4 ======\n');
  for (const r of results) {
    const isHome = r.name === 'home';
    const h3ok = r.h3Heights.length > 0 && r.h3Heights.every(h => isHome ? (h >= 43 && h <= 46) : (h >= 57 && h <= 60));
    const h3status = r.h3Count === 0 ? 'N/A' : (h3ok ? 'PASS' : 'FAIL');
    console.log('PAGE: ' + r.name.toUpperCase());
    console.log('  H3 product-name: count=' + r.h3Count + ' heights=[' + r.h3Heights.join(',') + '] range=' + r.h3Range + 'px => ' + h3status);
    console.log('  Cards: ' + r.cardCount);
    if (r.sorterH !== null) console.log('  Sorter height: ' + r.sorterH + 'px ' + (r.sorterH >= 35 && r.sorterH <= 38 ? '(PASS)' : '(INFO)'));
    console.log('  Header height: ' + r.headerH + 'px');
    console.log('');
  }
  console.log('====== DONE ======');
})().catch(e => { console.error('ERR:', e.message.slice(0,200)); process.exit(1); });
