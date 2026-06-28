import puppeteer from 'puppeteer';
const browser = await puppeteer.launch({ headless: 'new', executablePath: '/usr/bin/google-chrome-stable', args: ['--no-sandbox','--disable-setuid-sandbox'] });

function lum(rgb){ const m=rgb.match(/[\d.]+/g); if(!m) return null; const [r,g,b]=m.map(Number).map(v=>{v/=255; return v<=0.03928? v/12.92 : Math.pow((v+0.055)/1.055,2.4);}); return 0.2126*r+0.7152*g+0.0722*b; }

// Home tablet: cart counter + benefit circles
let page = await browser.newPage();
await page.setViewport({ width: 768, height: 1024 });
await page.goto('https://awamotos.com/', { waitUntil: 'networkidle2', timeout: 60000 });
await new Promise(r => setTimeout(r, 3000));
const home = await page.evaluate(() => {
  const el = document.querySelector('.counter.qty');
  const cart = el ? (()=>{const c=getComputedStyle(el);const r=el.getBoundingClientRect();return {cls:el.className,display:c.display,text:(el.textContent||'').trim(),w:Math.round(r.width),h:Math.round(r.height),bg:c.backgroundColor,borderW:c.borderTopWidth,top:Math.round(r.top),left:Math.round(r.left)};})() : null;
  // find benefit container: the element holding "5.000+ itens"
  let benef = [...document.querySelectorAll('div,section')].find(e => /5\.000\+/.test(e.textContent||'') && /atacado/i.test(e.textContent||'') && e.querySelectorAll('*').length < 60);
  const circlesInBenef = [];
  if (benef) {
    benef.querySelectorAll('*').forEach(e=>{const c=getComputedStyle(e);const r=e.getBoundingClientRect(); const br=parseFloat(c.borderTopLeftRadius)||0; const round=c.borderRadius==='50%'||br>=Math.min(r.width,r.height)/2-2; if(round && r.width>=5 && r.width<=40 && Math.abs(r.width-r.height)<6){circlesInBenef.push({tag:e.tagName.toLowerCase(),cls:(e.className||'').toString().slice(0,45),w:Math.round(r.width),top:Math.round(r.top),left:Math.round(r.left),bg:c.backgroundColor,border:parseFloat(c.borderTopWidth)+' '+c.borderStyle+' '+c.borderTopColor});}});
  }
  return { cart, benefFound: !!benef, circlesInBenef };
});
console.log('HOME:', JSON.stringify(home, null, 2));
await page.close();

// PLP desktop: banner title contrast
page = await browser.newPage();
await page.setViewport({ width: 1440, height: 900 });
await page.goto('https://awamotos.com/bauletos.html', { waitUntil: 'networkidle2', timeout: 60000 });
await new Promise(r => setTimeout(r, 3000));
const plp = await page.evaluate(() => {
  const out = {};
  const title = document.querySelector('.page-title span, .page-title, h1');
  if (title){const c=getComputedStyle(title);const r=title.getBoundingClientRect();out.title={text:(title.textContent||'').trim().slice(0,40),color:c.color,fontSize:c.fontSize,opacity:c.opacity,top:Math.round(r.top),left:Math.round(r.left),w:Math.round(r.width),h:Math.round(r.height)};
    // effective background: walk ancestors for non-transparent bg
    let p=title, bg='rgba(0, 0, 0, 0)'; while(p){const pc=getComputedStyle(p); if(pc.backgroundColor && pc.backgroundColor!=='rgba(0, 0, 0, 0)'){bg=pc.backgroundColor;break;} p=p.parentElement;} out.title.effectiveBg=bg;
  }
  // banner image area
  const bi = document.querySelector('.category-image img, [class*="banner"] img, .category-view img');
  if(bi){const r=bi.getBoundingClientRect();out.bannerImg={src:(bi.currentSrc||bi.src).split('/').pop(),nw:bi.naturalWidth,nh:bi.naturalHeight,w:Math.round(r.width),h:Math.round(r.height),objectFit:getComputedStyle(bi).objectFit};}
  return out;
});
// contrast calc
if (plp.title){ const L1=lum(plp.title.color), L2=lum(plp.title.effectiveBg); if(L1!=null&&L2!=null){const hi=Math.max(L1,L2),lo=Math.min(L1,L2); plp.title.contrast=Number(((hi+0.05)/(lo+0.05)).toFixed(2));}}
console.log('PLP:', JSON.stringify(plp, null, 2));
await page.screenshot({ path: '/tmp/awa-shots/plp-top.png', clip: { x: 0, y: 60, width: 1440, height: 260 } });
console.log('plp-top shot ok');
await page.close();
await browser.close();
