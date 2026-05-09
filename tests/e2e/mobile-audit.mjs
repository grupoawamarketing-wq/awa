import pkg from '@playwright/test';
const { firefox } = pkg;

const browser = await firefox.launch({
  executablePath: '/home/deploy/.cache/ms-playwright/firefox-1511/firefox/firefox',
  headless: true,
});

const PAGES = [
  { name: 'home', url: 'https://awamotos.com/' },
  { name: 'category', url: 'https://awamotos.com/barras-de-guidao.html' },
  { name: 'pdp', url: 'https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html' },
];

const results = {};

for (const vp of [
  { name: 'mobile', w: 375, h: 812 },
  { name: 'tablet', w: 768, h: 1024 },
]) {
  const ctx = await browser.newContext({ viewport: { width: vp.w, height: vp.h } });
  const page = await ctx.newPage();
  results[vp.name] = {};
  
  for (const pg of PAGES) {
    try {
      await Promise.race([
        page.goto(pg.url, { waitUntil: 'domcontentloaded', timeout: 55000 }),
        new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), 50000))
      ]);
      await page.waitForTimeout(1500);
      
      const audit = await page.evaluate((vpWidth) => {
        const sw = (sel, prop) => {
          const el = document.querySelector(sel);
          return el ? getComputedStyle(el)[prop] : null;
        };
        
        // Check actual horizontal scroll
        const hasHScrollBody = document.body.scrollWidth > vpWidth;
        const hasHScrollDoc = document.documentElement.scrollWidth > vpWidth;
        
        // Find overflowing elements
        const overflows = [];
        document.querySelectorAll('*').forEach(el => {
          try {
            const rect = el.getBoundingClientRect();
            if (rect.right > vpWidth + 5 || rect.left < -5) {
              const cs = getComputedStyle(el);
              const cls = typeof el.className === 'string' ? el.className.split(' ').slice(0,2).join('.') : '';
              if (!cls.includes('fotorama') && rect.left > -1000) {
                overflows.push({ tag: el.tagName, class: cls, right: Math.round(rect.right), left: Math.round(rect.left) });
              }
            }
          } catch(e) {}
        });
        
        return {
          hasHScrollBody,
          hasHScrollDoc,
          bodyScrollWidth: document.body.scrollWidth,
          docScrollWidth: document.documentElement.scrollWidth,
          viewportWidth: vpWidth,
          // Mobile nav
          mobileNav: {
            hamburgerVisible: (() => {
              const el = document.querySelector('.nav-toggle, .mobile-menu-toggle, [class*="hamburger"], .toggle-nav');
              return el ? getComputedStyle(el).display !== 'none' : false;
            })(),
          },
          // Product grid on mobile
          productGrid: (() => {
            const grid = document.querySelector('.product-grid, .products-grid, ul.product-items');
            if (!grid) return null;
            const cs = getComputedStyle(grid);
            // Count visible items
            const items = grid.querySelectorAll('.product-item, .item-product');
            return { display: cs.display, cols: cs.gridTemplateColumns, gap: cs.gap, itemCount: items.length };
          })(),
          // Columns layout
          columns: {
            leftSidebar: sw('.sidebar-main, .sidebar.sidebar-main, [class*="sidebar"]', 'width'),
            mainContent: sw('.column.main, .catalog-category-view .column.main', 'width'),
          },
          // Header height
          headerHeight: sw('header.page-header, .page-header, .awa-nav-bar', 'height'),
          footerBorder: sw('.page-footer, footer', 'borderTop'),
          // Container padding
          containerPadding: sw('.page-main, .container', 'padding'),
          overflows: overflows.slice(0, 8),
        };
      }, vp.w);
      
      results[vp.name][pg.name] = audit;
      console.log(`✅ ${vp.name} - ${pg.name}: hscroll=${audit.hasHScrollDoc} scrollW=${audit.docScrollWidth}`);
    } catch(e) {
      console.log(`❌ ${vp.name} - ${pg.name}: ${e.message}`);
      results[vp.name][pg.name] = { error: e.message };
    }
  }
  await ctx.close();
}

await browser.close();
console.log('\n=== MOBILE/TABLET AUDIT ===');
console.log(JSON.stringify(results, null, 2));
