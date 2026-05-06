const { chromium } = require('@playwright/test');

(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  
  for (const [name, url] of [
    ['category', 'https://awamotos.com/bagageiros.html'],
    ['search', 'https://awamotos.com/catalogsearch/result/?q=bagageiro'],
  ]) {
    const page = await b.newPage();
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 40000 });
    await page.waitForTimeout(2000);
    
    const d = await page.evaluate(() => {
      const s = document.querySelector('.sorter select, .sorter-options');
      if (!s) return { found: false };
      const cs = window.getComputedStyle(s);
      return {
        found: true,
        tag: s.tagName,
        classList: s.className,
        height: cs.height,
        minHeight: cs.minHeight,
        maxHeight: cs.maxHeight,
        boxSizing: cs.boxSizing,
        padding: cs.paddingTop + ' ' + cs.paddingBottom,
        parentClass: s.parentElement?.className?.slice(0,60),
      };
    });
    console.log(name.toUpperCase(), JSON.stringify(d, null, 2));
    await page.close();
  }
  
  await b.close();
})().catch(e => console.error(e.message));
