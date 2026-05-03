const { chromium } = require('@playwright/test');
const fs = require('fs');

const PAGES = [
  { name: 'home', url: 'https://awamotos.com/', wait: 3000 },
  { name: 'category', url: 'https://awamotos.com/bagageiros.html', wait: 3000 },
  { name: 'pdp', url: 'https://awamotos.com/catalogsearch/result/?q=bagageiro', wait: 3000 },
  { name: 'login', url: 'https://awamotos.com/customer/account/login/', wait: 2000 },
  { name: 'register', url: 'https://awamotos.com/b2b/register/', wait: 2000 },
];

const VIEWPORTS = [
  { name: 'mobile', width: 375, height: 812 },
  { name: 'desktop', width: 1366, height: 900 },
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const outDir = '/tmp/audit_screenshots';
  fs.mkdirSync(outDir, { recursive: true });

  for (const vp of VIEWPORTS) {
    const page = await browser.newPage({ viewport: { width: vp.width, height: vp.height } });
    for (const pg of PAGES) {
      try {
        await page.goto(pg.url, { timeout: 15000, waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(pg.wait);
        
        // Capture above-the-fold (viewport only)
        await page.screenshot({ 
          path: `${outDir}/${vp.name}_${pg.name}_fold.png`,
          fullPage: false 
        });
        
        // Capture full page
        await page.screenshot({ 
          path: `${outDir}/${vp.name}_${pg.name}_full.png`,
          fullPage: true 
        });
        
        // Collect computed style metrics for header
        const metrics = await page.evaluate(() => {
          const header = document.querySelector('.awa-site-header') || document.querySelector('#header');
          const search = document.querySelector('#search');
          const footer = document.querySelector('.footer-container') || document.querySelector('.page-footer');
          const body = document.body;
          
          const getRect = (el) => el ? el.getBoundingClientRect() : null;
          const getStyles = (el) => {
            if (!el) return null;
            const s = getComputedStyle(el);
            return {
              display: s.display,
              position: s.position,
              overflow: s.overflow,
              width: s.width,
              height: s.height,
              padding: s.padding,
              margin: s.margin,
              zIndex: s.zIndex,
            };
          };
          
          return {
            bodyOverflow: body.scrollWidth > body.clientWidth,
            bodyWidth: body.scrollWidth,
            viewportWidth: window.innerWidth,
            header: { rect: getRect(header), styles: getStyles(header) },
            search: { rect: getRect(search), styles: getStyles(search) },
            footer: { rect: getRect(footer), styles: getStyles(footer) },
          };
        });
        
        fs.writeFileSync(
          `${outDir}/${vp.name}_${pg.name}_metrics.json`,
          JSON.stringify(metrics, null, 2)
        );
        
        console.log(`✓ ${vp.name} / ${pg.name}`);
      } catch (e) {
        console.log(`✗ ${vp.name} / ${pg.name}: ${e.message.slice(0, 80)}`);
      }
    }
    await page.close();
  }

  await browser.close();
  console.log(`\nDone. Screenshots at ${outDir}/`);
})();
