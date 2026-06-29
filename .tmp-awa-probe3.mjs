import puppeteer from 'puppeteer';
const browser = await puppeteer.launch({ headless: 'new', executablePath: '/usr/bin/google-chrome-stable', args: ['--no-sandbox','--disable-setuid-sandbox'] });

async function leakCulprit(url, vw) {
  const page = await browser.newPage();
  await page.setViewport({ width: vw, height: 1000, deviceScaleFactor: 1 });
  await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });
  await new Promise(r => setTimeout(r, 3000));
  const out = await page.evaluate(() => {
    const docW = document.documentElement.clientWidth;
    const scrollW = document.documentElement.scrollWidth;
    const culprits = [];
    document.querySelectorAll('body *').forEach(e => {
      const r = e.getBoundingClientRect();
      if (r.right > docW + 0.5 && r.width > 0 && r.height > 0) {
        let clipped = false, p = e.parentElement;
        while (p) { const c = getComputedStyle(p); if (/(hidden|clip|auto|scroll)/.test(c.overflowX)) { clipped = true; break; } p = p.parentElement; }
        if (!clipped) {
          const cs = getComputedStyle(e);
          culprits.push({ right: Math.round(r.right), tag: e.tagName.toLowerCase(), cls: (e.className||'').toString().slice(0,70), w: Math.round(r.width), pos: cs.position, ml: cs.marginLeft, mr: cs.marginRight });
        }
      }
    });
    culprits.sort((a,b)=>b.right-a.right);
    return { docW, scrollW, hScroll: scrollW > docW+1, topCulprits: culprits.slice(0,12) };
  });
  await page.close();
  return { url, vw, ...out };
}

// hero pagination + announcement bar on desktop
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 900 });
await page.goto('https://awamotos.com/', { waitUntil: 'networkidle2', timeout: 60000 });
await new Promise(r => setTimeout(r, 3500));
const extras = await page.evaluate(() => {
  const pag = document.querySelector('.awa-hero-swiper .swiper-pagination, .swiper-pagination');
  let pagination = null;
  if (pag) {
    const r = pag.getBoundingClientRect();
    pagination = { cls: pag.className.slice(0,60), top: Math.round(r.top), left: Math.round(r.left), bullets: pag.querySelectorAll('.swiper-pagination-bullet').length, html: pag.outerHTML.replace(/\s+/g,' ').slice(0,260) };
  }
  // announcement bar
  const ann = document.querySelector('[class*="announce"],[class*="topbar"],[class*="top-bar"],[class*="usp-bar"],.header-top, .panel.wrapper');
  let announce = null;
  if (ann) { const r = ann.getBoundingClientRect(); announce = { cls: ann.className.slice(0,60), h: Math.round(r.height), text: (ann.textContent||'').replace(/\s+/g,' ').trim().slice(0,160), childCount: ann.children.length }; }
  return { pagination, announce };
});
await page.close();
console.log('### HERO EXTRAS\n' + JSON.stringify(extras, null, 2));

console.log('### TABLET 768 LEAK\n' + JSON.stringify(await leakCulprit('https://awamotos.com/', 768), null, 2));
await browser.close();
