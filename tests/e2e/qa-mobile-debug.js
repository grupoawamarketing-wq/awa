const { chromium } = require('@playwright/test');

(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  
  // Check category sorter on mobile
  const page = await b.newPage();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com/bagageiros.html', { waitUntil: 'domcontentloaded', timeout: 40000 });
  await page.waitForTimeout(4000); // Wait longer for async CSS
  
  const d = await page.evaluate(() => {
    const s = document.querySelector('.sorter select, .sorter-options');
    const cs = s ? window.getComputedStyle(s) : {};
    
    // Check if our qa-category CSS is loaded
    const qaLoaded = [...document.styleSheets].some(sh => sh.href && sh.href.includes('awa-visual-qa-category'));
    
    return {
      sorterH: s ? Math.round(s.getBoundingClientRect().height) : null,
      computedH: cs.height,
      computedMH: cs.minHeight,
      qaLoaded,
      sorterClass: s?.className,
      sorterTag: s?.tagName,
    };
  });
  
  console.log('Mobile category:', JSON.stringify(d, null, 2));
  
  // Check home H3 on mobile  
  const p2 = await b.newPage();
  await p2.setViewportSize({ width: 375, height: 812 });
  await p2.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 40000 });
  await p2.waitForTimeout(4000);
  
  const d2 = await p2.evaluate(() => {
    const h3s = [...document.querySelectorAll('h3.product-name')];
    const visible = h3s.filter(h => h.getBoundingClientRect().height > 0);
    const hidden = h3s.filter(h => h.getBoundingClientRect().height === 0);
    return {
      total: h3s.length,
      visible: visible.length,
      hiddenCount: hidden.length,
      visibleHeights: [...new Set(visible.map(h => Math.round(h.getBoundingClientRect().height)))].sort((a,b)=>a-b),
    };
  });
  console.log('Mobile home H3:', JSON.stringify(d2, null, 2));
  
  await b.close();
})().catch(e => console.error(e.message));
