import pkg from '@playwright/test';
const { firefox } = pkg;

const browser = await firefox.launch({
  executablePath: '/home/deploy/.cache/ms-playwright/firefox-1511/firefox/firefox',
  headless: true,
});
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();

// Coletar requests de CSS
const cssRequests = [];
page.on('response', response => {
  if (response.url().includes('.css') || response.url().includes('css?')) {
    cssRequests.push({ url: response.url().split('/').pop().split('?')[0], status: response.status() });
  }
});

await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 60000 });
await page.waitForTimeout(3000);

// Deep CSS audit
const audit = await page.evaluate(() => {
  // 1. Carregar todos os stylesheets
  const sheets = Array.from(document.styleSheets);
  const sheetNames = sheets.map(s => s.href ? s.href.split('/').pop().split('?')[0] : 'inline');
  
  // 2. Verificar especificamente problemas visuais
  const checks = {
    // Footer
    footerBorderTop: getComputedStyle(document.querySelector('.page-footer, footer')).borderTop,
    footerBg: getComputedStyle(document.querySelector('.page-footer, footer')).backgroundColor,
    
    // Header
    headerEl: document.querySelector('header.page-header, .page-header')?.tagName,
    stickyHeaderShadow: getComputedStyle(document.querySelector('.page-header.fixed, .page-header.sticky, header.sticky') || document.querySelector('header'))?.boxShadow,
    
    // Navigation
    navBg: getComputedStyle(document.querySelector('nav.navigation, #horizontal-menu, .navigation') || document.querySelector('.nav-sections')).backgroundColor,
    
    // Section spacing inconsistency
    sections: Array.from(document.querySelectorAll('.sections-row, .block-type-products, .product-tabs-section')).slice(0, 5).map(el => ({
      class: el.className.split(' ').slice(0, 3).join('.'),
      marginBottom: getComputedStyle(el).marginBottom,
      marginTop: getComputedStyle(el).marginTop,
      paddingTop: getComputedStyle(el).paddingTop,
      paddingBottom: getComputedStyle(el).paddingBottom,
    })),
    
    // Check swiper containers
    swiperContainers: Array.from(document.querySelectorAll('.swiper-container, [class*="swiper"]')).slice(0, 5).map(el => ({
      class: el.className.split(' ').slice(0, 2).join('.'),
      overflow: getComputedStyle(el).overflow,
      width: getComputedStyle(el).width,
    })),
    
    // Product grid layout
    productGrid: (() => {
      const grid = document.querySelector('ul.product-grid, ol.product-items, .products-grid');
      if (!grid) return null;
      const cs = getComputedStyle(grid);
      return { display: cs.display, gridTemplateColumns: cs.gridTemplateColumns, gap: cs.gap };
    })(),
    
    // Check for duplicate borders (elements with both border and box-shadow)
    duplicateBorders: Array.from(document.querySelectorAll('.product-item, .item-product, .block, .widget')).slice(0, 10).map(el => {
      const cs = getComputedStyle(el);
      return {
        class: el.className.split(' ').slice(0, 2).join('.'),
        border: cs.border.substring(0, 40),
        shadow: cs.boxShadow.substring(0, 40),
      };
    }),
    
    // Container widths
    containers: Array.from(document.querySelectorAll('.page-width, .container, .page-main > .page-main-inner, .columns')).slice(0, 5).map(el => {
      const cs = getComputedStyle(el);
      return {
        class: el.className.split(' ').slice(0, 2).join('.'),
        maxWidth: cs.maxWidth,
        width: cs.width,
        padding: cs.padding,
      };
    }),
    
    styleSheetCount: sheets.length,
    styleSheetNames: sheetNames.filter(n => n !== 'inline').slice(0, 30),
    
    // Check hero/banner section
    heroBanner: (() => {
      const hero = document.querySelector('.ams-slideshow, .slide-banner, .hero-banner, [class*="slider"]');
      if (!hero) return null;
      const cs = getComputedStyle(hero);
      return { width: cs.width, maxWidth: cs.maxWidth, margin: cs.margin };
    })(),
  };
  return checks;
});

console.log(JSON.stringify(audit, null, 2));
console.log('\n=== CSS LOADED ===');
cssRequests.forEach(r => console.log(`${r.status} ${r.url}`));

await browser.close();
