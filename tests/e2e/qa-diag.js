const { chromium } = require('@playwright/test');

(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const page = await b.newPage();
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 40000 });
  await page.waitForTimeout(2000);

  const diag = await page.evaluate(() => {
    // Body classes
    const bodyClass = document.body.className;
    
    // Find awa-visual-qa-home.css in document stylesheets
    const sheets = [...document.styleSheets].map(s => s.href || 'inline').filter(h => h.includes('qa') || h.includes('visual'));
    
    // Check first H3
    const h3 = document.querySelector('h3.product-name');
    const h3cs = h3 ? window.getComputedStyle(h3) : null;
    const h3parent = h3 ? h3.closest('.item-product') : null;
    const h3context = h3parent ? h3parent.parentElement?.className?.slice(0,80) : null;
    
    // Check a specific H3's applied CSS
    const matched = h3 ? window.getComputedStyle(h3).height : null;
    
    return {
      bodyClass: bodyClass.slice(0, 200),
      qaSheets: sheets,
      h3Height: matched,
      h3Context: h3context,
      h3Tag: h3 ? h3.outerHTML.slice(0,100) : null,
    };
  });
  
  console.log('Body class:', diag.bodyClass.slice(0,150));
  console.log('QA Stylesheets found:', diag.qaSheets.length);
  diag.qaSheets.forEach(s => console.log('  -', s.slice(-60)));
  console.log('First H3 height:', diag.h3Height);
  console.log('H3 context (parent .parent class):', diag.h3Context?.slice(0,80));
  console.log('H3 outerHTML:', diag.h3Tag);
  
  await b.close();
})().catch(e => console.error('ERR:', e.message.slice(0,200)));
