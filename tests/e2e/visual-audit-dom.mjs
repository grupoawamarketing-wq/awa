import pkg from '@playwright/test';
const { firefox } = pkg;

const browser = await firefox.launch({
  executablePath: '/home/deploy/.cache/ms-playwright/firefox-1511/firefox/firefox',
  headless: true,
});

const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();

const results = {};

async function auditPage(url, name) {
  console.log(`\nAuditando ${name} (${url})...`);
  try {
    await Promise.race([
      page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 }),
      new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 55000))
    ]);
    await page.waitForTimeout(2000);
    
    const data = await page.evaluate(() => {
      const safeGet = (sel, prop) => {
        const el = document.querySelector(sel);
        if (!el) return null;
        return getComputedStyle(el)[prop];
      };
      const safeGetAll = (sel, props) => {
        const els = document.querySelectorAll(sel);
        if (!els.length) return [];
        return Array.from(els).slice(0, 3).map(el => {
          const cs = getComputedStyle(el);
          const result = { selector: sel };
          props.forEach(p => result[p] = cs[p]);
          return result;
        });
      };
      
      return {
        // Header
        header: {
          height: safeGet('header.page-header, .page-header', 'height'),
          bgColor: safeGet('header.page-header, .page-header', 'backgroundColor'),
          padding: safeGet('header.page-header, .page-header', 'padding'),
          border: safeGet('header.page-header, .page-header', 'borderBottom'),
          boxShadow: safeGet('header.page-header, .page-header', 'boxShadow'),
        },
        // Navigation
        nav: {
          height: safeGet('#horizontal-menu, .nav-sections', 'height'),
          bgColor: safeGet('#horizontal-menu, .nav-sections', 'backgroundColor'),
          border: safeGet('#horizontal-menu, .nav-sections', 'borderBottom'),
        },
        // Main container
        container: {
          maxWidth: safeGet('.page-main .page-wrapper, .page-main', 'maxWidth'),
          padding: safeGet('.page-main', 'padding'),
          margin: safeGet('.page-main', 'margin'),
        },
        // Product cards (sampling)
        productCards: safeGetAll('.product-item, .item-product', ['border', 'borderRadius', 'boxShadow', 'padding', 'margin', 'backgroundColor']),
        // Buttons
        buttons: safeGetAll('.btn-cart, .action.primary, .action-primary', ['backgroundColor', 'color', 'borderRadius', 'padding', 'border', 'height']),
        // Section/row spacing
        sections: safeGetAll('.block, .sections-row, .carousel-slider, .widget', ['marginBottom', 'marginTop', 'paddingBottom', 'paddingTop']),
        // Typography
        typography: {
          h1: safeGet('h1', 'fontSize') + ' / ' + safeGet('h1', 'fontWeight'),
          h2: safeGet('h2', 'fontSize') + ' / ' + safeGet('h2', 'fontWeight'),
          h3: safeGet('h3', 'fontSize') + ' / ' + safeGet('h3', 'fontWeight'),
          body: safeGet('body', 'fontSize') + ' / ' + safeGet('body', 'lineHeight'),
          bodyColor: safeGet('body', 'color'),
        },
        // Overflow check
        overflow: {
          bodyOverflowX: safeGet('body', 'overflowX'),
          htmlOverflowX: safeGet('html', 'overflowX'),
          bodyPosition: safeGet('body', 'position'),
        },
        // Footer
        footer: {
          padding: safeGet('.page-footer, footer', 'padding'),
          bgColor: safeGet('.page-footer, footer', 'backgroundColor'),
          border: safeGet('.page-footer, footer', 'borderTop'),
        },
        // Page background
        pageBg: safeGet('.page-wrapper', 'backgroundColor'),
        // Breadcrumb
        breadcrumb: {
          margin: safeGet('.breadcrumbs', 'margin'),
          padding: safeGet('.breadcrumbs', 'padding'),
          border: safeGet('.breadcrumbs', 'border'),
        },
        // Issues flags
        issues: (() => {
          const issues = [];
          // Check for horizontal overflow
          if (document.documentElement.scrollWidth > window.innerWidth) {
            issues.push(`horizontal-overflow: scrollWidth=${document.documentElement.scrollWidth} > innerWidth=${window.innerWidth}`);
          }
          // Check for elements wider than viewport
          document.querySelectorAll('*').forEach(el => {
            const rect = el.getBoundingClientRect();
            if (rect.right > window.innerWidth + 5) {
              issues.push(`overflow-right: ${el.tagName}.${el.className.split(' ').slice(0,2).join('.')} right=${Math.round(rect.right)}`);
            }
          });
          return issues.slice(0, 20);
        })(),
      };
    });
    
    results[name] = data;
    console.log(`  ✅ Dados coletados`);
    
  } catch(e) {
    console.log(`  ❌ Erro: ${e.message}`);
    results[name] = { error: e.message };
  }
}

await auditPage('https://awamotos.com/', 'home');
await auditPage('https://awamotos.com/barras-de-guidao.html', 'category');
await auditPage('https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html', 'pdp');

await browser.close();

import { writeFileSync } from 'fs';
writeFileSync('/tmp/visual-audit-2026/dom-audit.json', JSON.stringify(results, null, 2));
console.log('\n=== RESULTADO ===');
console.log(JSON.stringify(results, null, 2));
