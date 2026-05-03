const { chromium } = require('playwright');
(async()=>{
  const browser = await chromium.launch({headless:true,args:['--no-sandbox']});
  const page = await browser.newPage({viewport:{width:1920,height:1080}});
  await page.goto('https://awamotos.com/',{waitUntil:'domcontentloaded',timeout:120000});
  await page.waitForTimeout(3500);
  const metrics = await page.evaluate(()=>{
    const wrap=document.querySelector('.page-wrapper');
    const header=document.querySelector('.awa-main-header');
    const inner=document.querySelector('.awa-main-header__inner');
    const brand=document.querySelector('.awa-header-brand-cell, .col-md-2.awa-header-brand');
    const search=document.querySelector('.awa-header-search-col.top-search, .top-search');
    const right=document.querySelector('.awa-header-right-col');
    function rect(el){if(!el) return null; const r=el.getBoundingClientRect(); return {x:r.x,y:r.y,w:r.width,h:r.height,cx:r.x+r.width/2,cy:r.y+r.height/2};}
    return {vw:window.innerWidth, wrap:rect(wrap), header:rect(header), inner:rect(inner), brand:rect(brand), search:rect(search), right:rect(right)};
  });
  console.log(JSON.stringify(metrics,null,2));
  await page.screenshot({path:'tmp/header_before_1920.png',fullPage:false});
  await browser.close();
})();
