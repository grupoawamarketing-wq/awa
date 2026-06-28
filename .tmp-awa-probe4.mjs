import puppeteer from 'puppeteer';
const browser = await puppeteer.launch({ headless: 'new', executablePath: '/usr/bin/google-chrome-stable', args: ['--no-sandbox','--disable-setuid-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 1 });
await page.goto('https://awamotos.com/', { waitUntil: 'networkidle2', timeout: 60000 });
await new Promise(r => setTimeout(r, 4500));

const info = await page.evaluate(() => {
  const hero = document.querySelector('.awa-hero-swiper');
  const r = hero.getBoundingClientRect();
  const cx = Math.round(r.left + r.width/2);
  const cy = Math.round(r.top + r.height/2);
  // stack of elements at hero center
  const stack = (document.elementsFromPoint(cx, cy)||[]).slice(0,8).map(e=>{
    const c=getComputedStyle(e); const er=e.getBoundingClientRect();
    return { tag:e.tagName.toLowerCase(), cls:(e.className||'').toString().slice(0,55), bg:c.backgroundColor, op:c.opacity, z:c.zIndex, pos:c.position, w:Math.round(er.width), h:Math.round(er.height) };
  });
  // the active img specifics
  const img = hero.querySelector('.swiper-slide-active img');
  let imgInfo = null;
  if (img){ const ir=img.getBoundingClientRect(); const ic=getComputedStyle(img);
    imgInfo = { src:(img.currentSrc||img.src), top:Math.round(ir.top), left:Math.round(ir.left), w:Math.round(ir.width), h:Math.round(ir.height), op:ic.opacity, transform:ic.transform, clip:ic.clipPath, filter:ic.filter, objectFit:ic.objectFit, objectPosition:ic.objectPosition, position:ic.position, visibility:ic.visibility };
  }
  // active slide style
  const slide = hero.querySelector('.swiper-slide-active');
  const sc = getComputedStyle(slide);
  return { heroBox:{top:Math.round(r.top),left:Math.round(r.left),w:Math.round(r.width),h:Math.round(r.height)}, cx, cy, stackTop: stack, imgInfo, slideStyle:{transform:sc.transform, clipPath:sc.clipPath, overflow:sc.overflow, position:sc.position, filter:sc.filter, mixBlend:sc.mixBlendMode} };
});
console.log(JSON.stringify(info, null, 2));

// screenshot just the hero element
const hero = await page.$('.awa-hero-swiper');
if (hero) { await hero.screenshot({ path: '/tmp/awa-shots/hero-elem.png' }); console.log('hero element screenshot saved'); }
await browser.close();
