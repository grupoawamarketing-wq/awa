import puppeteer from 'puppeteer';
const browser = await puppeteer.launch({ headless: 'new', executablePath: '/usr/bin/google-chrome-stable', args: ['--no-sandbox','--disable-setuid-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 1 });
await page.goto('https://awamotos.com/', { waitUntil: 'networkidle2', timeout: 60000 });
await new Promise(r => setTimeout(r, 4000));

const data = await page.evaluate(() => {
  const cs = el => el ? getComputedStyle(el) : null;
  // ---- HERO ----
  const heroWrap = document.querySelector('.awa-hero-swiper, .awa-hero-slider, .swiper.awa-hero-swiper');
  let hero = { found: !!heroWrap };
  if (heroWrap) {
    const w = heroWrap.getBoundingClientRect();
    const wrapper = heroWrap.querySelector('.swiper-wrapper');
    hero.wrapperTransform = wrapper ? cs(wrapper).transform : null;
    hero.box = { w: Math.round(w.width), h: Math.round(w.height) };
    hero.swiperClasses = heroWrap.className;
    const slides = [...heroWrap.querySelectorAll('.swiper-slide')];
    hero.slideCount = slides.length;
    hero.slides = slides.slice(0,5).map(s => {
      const c = cs(s); const img = s.querySelector('img'); const ic = img ? cs(img) : null; const ir = img ? img.getBoundingClientRect() : null;
      return {
        cls: s.className.replace('swiper-slide','').trim().slice(0,60),
        opacity: c.opacity, visibility: c.visibility, display: c.display, zIndex: c.zIndex,
        h: Math.round(s.getBoundingClientRect().height),
        inlineOpacity: s.style.opacity || '(none)',
        img: img ? { nw: img.naturalWidth, dispW: Math.round(ir.width), dispH: Math.round(ir.height), opacity: ic.opacity, display: ic.display, objectFit: ic.objectFit } : null,
      };
    });
  }
  // also the non-swiper banner fallback
  const bannerSlider = document.querySelector('.banner-slider, .wrapper_slider');
  hero.bannerSliderHTML = bannerSlider ? bannerSlider.outerHTML.replace(/\s+/g,' ').slice(0, 600) : null;

  // ---- BENEFIT CARDS stray elements ----
  // find the benefits section
  let benefit = null;
  const cands = [...document.querySelectorAll('*')].filter(e => /atacado/i.test(e.textContent||'') );
  // locate card container by known text
  const card = [...document.querySelectorAll('*')].find(e => /5\.000\+|5000\+/.test(e.textContent||'') && e.children.length<8);
  const section = card ? card.closest('[class*="benefit"],[class*="usp"],[class*="trust"],section,div') : null;
  function circular(root){
    const res=[];
    root.querySelectorAll('*').forEach(e=>{
      const c=getComputedStyle(e); const r=e.getBoundingClientRect();
      const br=parseFloat(c.borderTopLeftRadius)||0;
      const isCircle = (c.borderRadius==='50%'||br>=Math.min(r.width,r.height)/2-1) && r.width>4 && r.width<40 && Math.abs(r.width-r.height)<6;
      const hasDot = (c.backgroundColor && c.backgroundColor!=='rgba(0, 0, 0, 0)') || (c.borderStyle!=='none' && parseFloat(c.borderTopWidth)>0);
      if(isCircle && hasDot && (e.children.length===0)){
        res.push({tag:e.tagName.toLowerCase(),cls:(e.className||'').toString().slice(0,50),w:Math.round(r.width),h:Math.round(r.height),top:Math.round(r.top),left:Math.round(r.left),bg:c.backgroundColor,border:c.borderTopWidth+' '+c.borderStyle+' '+c.borderTopColor,radius:c.borderRadius});
      }
    });
    return res;
  }
  if(section){
    benefit = { sectionCls: section.className.slice(0,80), circles: circular(section) };
  } else { benefit = {note:'section not found', circles: circular(document.body).slice(0,10)}; }

  return { hero, benefit };
});
console.log(JSON.stringify(data, null, 2));
await browser.close();
