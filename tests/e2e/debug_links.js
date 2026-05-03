const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  
  await page.goto('https://awamotos.com/?nocache=' + Date.now(), { waitUntil: 'networkidle' });
  
  const navData = await page.evaluate(() => {
    const nav = document.querySelector('.awa-header-primary-nav');
    if (!nav) return { error: 'nav not found' };
    const navStyle = getComputedStyle(nav);
    
    const items = Array.from(document.querySelectorAll('.awa-header-primary-nav .level0'));
    const itemsData = items.map(el => {
      const s = getComputedStyle(el);
      const a = el.querySelector('a');
      const as = a ? getComputedStyle(a) : null;
      return {
        text: el.innerText.trim(),
        w: el.offsetWidth,
        display: s.display,
        visibility: s.visibility,
        opacity: s.opacity,
        a_display: as ? as.display : null,
        a_w: a ? a.offsetWidth : null
      };
    });
    
    return {
      nav_display: navStyle.display,
      nav_w: nav.offsetWidth,
      items: itemsData
    };
  });
  
  console.log(JSON.stringify(navData, null, 2));
  await browser.close();
})();
