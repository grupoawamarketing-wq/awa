import pkg from '@playwright/test';
const { firefox } = pkg;
import { writeFileSync } from 'fs';

const browser = await firefox.launch({
  executablePath: '/home/deploy/.cache/ms-playwright/firefox-1511/firefox/firefox',
  headless: true,
});

async function auditPage(url, name, vpW, vpH) {
  const ctx = await browser.newContext({ viewport: { width: vpW, height: vpH } });
  const page = await ctx.newPage();
  
  try {
    await Promise.race([
      page.goto(url, { waitUntil: 'domcontentloaded', timeout: 55000 }),
      new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), 50000))
    ]);
    await page.waitForTimeout(2000);
    
    const data = await page.evaluate(() => {
      const sw = (sel, prop) => {
        const el = document.querySelector(sel);
        return el ? getComputedStyle(el)[prop] : null;
      };
      const swAll = (sel, props) => Array.from(document.querySelectorAll(sel)).slice(0, 5).map(el => {
        const cs = getComputedStyle(el);
        return props.reduce((acc, p) => { acc[p] = cs[p]; return acc; }, { _sel: sel });
      });
      
      return {
        // === TYPOGRAPHY CONSISTENCY ===
        typography: {
          h1: { size: sw('h1', 'fontSize'), weight: sw('h1', 'fontWeight'), color: sw('h1', 'color'), lineH: sw('h1', 'lineHeight') },
          h2: { size: sw('h2', 'fontSize'), weight: sw('h2', 'fontWeight'), color: sw('h2', 'color') },
          h3: { size: sw('h3', 'fontSize'), weight: sw('h3', 'fontWeight') },
          body: { size: sw('body', 'fontSize'), color: sw('body', 'color'), lineH: sw('body', 'lineHeight') },
          a: { color: sw('a', 'color'), decoration: sw('a', 'textDecoration') },
          price: { size: sw('.price, .price-box .price', 'fontSize'), color: sw('.price, .price-box .price', 'color'), weight: sw('.price, .price-box .price', 'fontWeight') },
        },
        
        // === SECTION SPACING ===
        sectionSpacing: (() => {
          const selectors = [
            '.home-tab-product', '.awa-product-tabs-widget', '.product-tabs-section',
            '.block-static-block', '.cms-content-important',
            '.sections-row', '.block', '.widget',
            '#main-benefits', '.awa-benefits', '.faixa-beneficios',
          ];
          return selectors.map(sel => {
            const el = document.querySelector(sel);
            if (!el) return null;
            const cs = getComputedStyle(el);
            return {
              sel, 
              mt: cs.marginTop, mb: cs.marginBottom,
              pt: cs.paddingTop, pb: cs.paddingBottom,
            };
          }).filter(Boolean).slice(0, 10);
        })(),
        
        // === PRODUCT CARD STYLES ===
        productCards: swAll('.item-product, .product-item', [
          'border', 'borderRadius', 'boxShadow', 'padding', 'margin',
          'backgroundColor', 'transition',
        ]),
        
        // === BUTTON CONSISTENCY ===
        buttons: {
          primary: swAll('.action.primary, .action.tocart, .action-primary, .btn-primary', [
            'backgroundColor', 'color', 'borderRadius', 'height', 'padding', 'fontSize', 'fontWeight', 'border',
          ]),
          secondary: swAll('.action.secondary, .action-secondary', [
            'backgroundColor', 'color', 'borderRadius', 'height', 'border',
          ]),
        },
        
        // === SPACING RHYTHM ===
        spacingRhythm: {
          breadcrumbPad: sw('.breadcrumbs', 'padding'),
          pageMainPad: sw('.page-main', 'padding'),
          columnsPad: sw('.columns', 'padding'),
          mainColPad: sw('.column.main', 'padding'),
          filtersPad: sw('.sidebar-main, .filter', 'padding'),
        },
        
        // === CARD IMAGE RATIO ===
        productImages: swAll('.product-image-wrapper, .product-thumb-link img', ['paddingBottom', 'width', 'aspectRatio']),
        
        // === SEARCH ===
        searchBox: {
          height: sw('.control.search .input-text, #search', 'height'),
          border: sw('.control.search .input-text, #search', 'border'),
          radius: sw('.control.search .input-text, #search', 'borderRadius'),
          bg: sw('.control.search .input-text, #search', 'backgroundColor'),
        },
        
        // === BREADCRUMB ===
        breadcrumb: {
          sep: sw('.breadcrumbs .delimiter, .breadcrumbs .item:not(:last-child)::after', 'content'),
          padding: sw('.breadcrumbs', 'padding'),
          itemColor: sw('.breadcrumbs .item a', 'color'),
          currentColor: sw('.breadcrumbs .item strong, .breadcrumbs .item:last-child', 'color'),
        },
        
        // === LAYERED NAV ===
        filters: {
          titleBg: sw('.filter-title, .filter-options-title', 'backgroundColor'),
          titleColor: sw('.filter-title, .filter-options-title', 'color'),
          titlePad: sw('.filter-title, .filter-options-title', 'padding'),
          optionPad: sw('.filter-option-toggle, .filter-options-item, .filter-options-content', 'padding'),
        },
        
        // === PAGINATION ===
        pagination: {
          active: sw('.pages .current, .pages-item-selected', 'backgroundColor'),
          item: sw('.pages .item a, .page-item a', 'color'),
        },
        
        // === FORM ELEMENTS ===
        inputs: swAll('input[type="text"], input[type="email"], select', [
          'border', 'borderRadius', 'height', 'padding', 'backgroundColor', 'color',
        ]),
        
        // === BADGES/TAGS ===
        badges: swAll('.product-label, .label.sale, .sale-label, .badge', [
          'backgroundColor', 'color', 'borderRadius', 'fontSize', 'padding',
        ]),
        
        // === SHADOW CONSISTENCY ===
        shadowCheck: {
          header: sw('header, .page-header', 'boxShadow'),
          nav: sw('.nav-sections, .navigation', 'boxShadow'),
          cards: sw('.item-product, .product-item', 'boxShadow'),
          dropdown: sw('.dropdown, .block-minicart', 'boxShadow'),
        },
        
        // === OVERALL LAYOUT ===
        layout: {
          pageMaxW: sw('.page-wrapper', 'maxWidth'),
          containerMaxW: sw('.container', 'maxWidth'),
          mainMaxW: sw('.page-main', 'maxWidth'),
          contentMaxW: sw('.column.main', 'maxWidth'),
        },
      };
    });
    
    await ctx.close();
    return data;
  } catch(e) {
    await ctx.close();
    return { error: e.message };
  }
}

const results = {
  home_desktop: await auditPage('https://awamotos.com/', 'home', 1440, 900),
  home_mobile: await auditPage('https://awamotos.com/', 'home', 375, 812),
  category_desktop: await auditPage('https://awamotos.com/barras-de-guidao.html', 'category', 1440, 900),
  pdp_desktop: await auditPage('https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html', 'pdp', 1440, 900),
};

await browser.close();
writeFileSync('/tmp/visual-audit-2026/full-audit.json', JSON.stringify(results, null, 2));
console.log('Done! Results saved to /tmp/visual-audit-2026/full-audit.json');

// Print key findings
for (const [page, data] of Object.entries(results)) {
  if (data.error) { console.log(`${page}: ERROR - ${data.error}`); continue; }
  const typo = data.typography;
  const btns = data.buttons;
  console.log(`\n=== ${page.toUpperCase()} ===`);
  console.log(`Typography: h1=${typo.h1.size}/${typo.h1.weight}, h2=${typo.h2.size}/${typo.h2.weight}, body=${typo.body.size}`);
  console.log(`Layout: pageMaxW=${data.layout.pageMaxW}, containerMaxW=${data.layout.containerMaxW}`);
  console.log(`Shadow: header="${data.shadowCheck.header?.substring(0,40)}", cards="${data.shadowCheck.cards?.substring(0,40)}"`);
  if (btns.primary.length) {
    const b = btns.primary[0];
    console.log(`Primary btn: bg=${b.backgroundColor}, r=${b.borderRadius}, h=${b.height}`);
  }
  if (data.productCards.length) {
    const c = data.productCards[0];
    console.log(`Card: border="${c.border?.substring(0,30)}", shadow="${c.boxShadow?.substring(0,30)}", r=${c.borderRadius}`);
  }
  if (data.searchBox.height) {
    console.log(`Search: h=${data.searchBox.height}, r=${data.searchBox.radius}, border="${data.searchBox.border?.substring(0,30)}"`);
  }
  if (data.filters.titleBg) {
    console.log(`Filter title: bg=${data.filters.titleBg}, color=${data.filters.titleColor}`);
  }
}
