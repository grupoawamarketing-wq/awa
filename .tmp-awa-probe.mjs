import puppeteer from 'puppeteer';

const browser = await puppeteer.launch({
  headless: 'new',
  executablePath: '/usr/bin/google-chrome-stable',
  args: ['--no-sandbox', '--disable-setuid-sandbox'],
});

async function probe(url, vw, vh) {
  const page = await browser.newPage();
  await page.setViewport({ width: vw, height: vh, deviceScaleFactor: 1 });
  const failed = [];
  page.on('requestfailed', r => failed.push(r.url().split('/').pop() + ' :: ' + r.failure()?.errorText));
  const resp = {};
  page.on('response', r => { const u = r.url(); if (/\.(png|jpg|jpeg|webp|gif|svg)(\?|$)/i.test(u)) { const c = r.status(); resp[c] = (resp[c]||0)+1; if (c>=400) failed.push('HTTP'+c+' '+u.split('/').pop()); } });
  await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });
  await new Promise(r => setTimeout(r, 1500));
  await page.evaluate(async () => { await new Promise(res => { let y=0; const s=()=>{window.scrollBy(0,600);y+=600; if(y<document.body.scrollHeight) setTimeout(s,80); else res();}; s(); }); });
  await new Promise(r => setTimeout(r, 2000));
  const data = await page.evaluate(() => {
    const out = {};
    // hero
    const heroSel = ['.awa-hero', '.owl-carousel', '.home-main-slider', '[class*="slider"]', '.block-banners', '.awa-home-hero'];
    let hero = null;
    for (const s of heroSel) { const e = document.querySelector(s); if (e) { const r=e.getBoundingClientRect(); hero={sel:s, w:Math.round(r.width), h:Math.round(r.height), html:e.outerHTML.slice(0,300)}; break; } }
    out.hero = hero;
    // hero imgs
    const topImgs = [...document.querySelectorAll('img')].slice(0,12).map(i=>({src:(i.currentSrc||i.src||'').split('/').pop().slice(0,40), nw:i.naturalWidth, nh:i.naturalHeight, complete:i.complete, loading:i.loading, dispW:Math.round(i.getBoundingClientRect().width)}));
    out.topImgs = topImgs;
    // product images
    const pImgs = [...document.querySelectorAll('img.product-image-photo, .product-image-photo, .product-item-photo img')];
    out.productImgCount = pImgs.length;
    out.productImgBroken = pImgs.filter(i=>i.complete && i.naturalWidth===0).length;
    out.productImgLoaded = pImgs.filter(i=>i.naturalWidth>0).length;
    out.productImgPending = pImgs.filter(i=>!i.complete).length;
    out.productImgSample = pImgs.slice(0,6).map(i=>({src:(i.currentSrc||i.src||'').split('/').pop().slice(0,40), nw:i.naturalWidth, complete:i.complete, loading:i.loading, ds:i.getAttribute('data-src')?'Y':'', vis: i.getBoundingClientRect().width>0}));
    return out;
  });
  await page.close();
  return { url, vw, imgResp: resp, failedSample: [...new Set(failed)].slice(0,15), ...data };
}

const home = await probe('https://awamotos.com/', 1440, 900);
console.log('### HOME 1440\n' + JSON.stringify(home, null, 2));
const plp = await probe('https://awamotos.com/bauletos.html', 1440, 900);
console.log('### PLP 1440\n' + JSON.stringify(plp, null, 2));

await browser.close();
