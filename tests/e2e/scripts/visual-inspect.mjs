import { chromium } from 'playwright';

const PAGES = [
  { slug: 'home', url: 'https://awamotos.com/' },
  { slug: 'category-guidoes', url: 'https://awamotos.com/guidoes.html' },
  { slug: 'category-bagageiros', url: 'https://awamotos.com/bagageiros.html' },
  { slug: 'pdp-ret-biz', url: 'https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html' },
  { slug: 'search-bagageiro', url: 'https://awamotos.com/catalogsearch/result/?q=bagageiro' },
  { slug: 'login', url: 'https://awamotos.com/customer/account/login/' },
  { slug: 'cart', url: 'https://awamotos.com/checkout/cart/' },
  { slug: 'b2b-landing', url: 'https://awamotos.com/b2b' }
];

async function inspectPage(page, slug) {
  const overflowX = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  const brokenImgs = await page.evaluate(() => Array.from(document.querySelectorAll('img')).filter(i => i.getBoundingClientRect().width > 40 && i.complete && i.naturalWidth === 0).map(i => ({ src: i.src.substring(0, 120), alt: i.alt })));
  const headerInfo = await page.evaluate(() => { const h = document.querySelector('.page-header, header.awa-site-header, #header'); if (!h) return { visible: false, height: 0 }; const r = h.getBoundingClientRect(); return { visible: r.width > 0, height: Math.round(r.height), overflowsViewport: r.right > window.innerWidth }; });
  const footerInfo = await page.evaluate(() => { const f = document.querySelector('.page-footer, footer'); if (!f) return { visible: false }; const r = f.getBoundingClientRect(); return { visible: r.width > 0, height: Math.round(r.height), overflowsViewport: r.right > window.innerWidth }; });
  const productCount = await page.evaluate(() => document.querySelectorAll('.product-item').length);
  const gridCols = await page.evaluate(() => { const grid = document.querySelector('.products-grid .product-items, .products.list .product-items'); if (!grid) return null; const items = grid.querySelectorAll('.product-item'); if (items.length < 2) return null; const firstTop = items[0].getBoundingClientRect().top; let cols = 0; for (const item of items) { if (Math.abs(item.getBoundingClientRect().top - firstTop) < 5) cols++; else break; } return cols; });
  const ctaInfo = await page.evaluate(() => { const btn = document.querySelector('#product-addtocart-button, .action.tocart.primary'); if (!btn) return { exists: false }; const r = btn.getBoundingClientRect(); return { exists: true, visible: r.width > 0 && r.height > 0, text: btn.textContent.trim().substring(0, 50) }; });
  const spacingIssues = await page.evaluate(() => { const sections = document.querySelectorAll('.column.main > *'); const gaps = []; for (let i = 1; i < Math.min(sections.length, 10); i++) { const prev = sections[i-1].getBoundingClientRect(); const curr = sections[i].getBoundingClientRect(); const gap = curr.top - prev.bottom; if (gap > 100 || gap < -5) gaps.push({ between: i-1 + '-' + i, gap: Math.round(gap) }); } return gaps; });
  return { slug, overflowX, brokenImgs, header: headerInfo, footer: footerInfo, productCount, gridCols, cta: ctaInfo, spacingIssues };
}

(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  const findings = { desktop: [], mobile: [] };

  console.error('=== Desktop (1366x768) ===');
  const dCtx = await browser.newContext({ viewport: { width: 1366, height: 768 } });
  const dPage = await dCtx.newPage();
  for (const p of PAGES) {
    console.error('  ' + p.slug);
    try {
      await dPage.goto(p.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await dPage.waitForTimeout(2500);
      findings.desktop.push(await inspectPage(dPage, p.slug));
    } catch (e) { findings.desktop.push({ slug: p.slug, error: e.message }); }
  }
  await dCtx.close();

  console.error('=== Mobile (375x667) ===');
  const mCtx = await browser.newContext({ viewport: { width: 375, height: 667 }, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)' });
  const mPage = await mCtx.newPage();
  for (const p of PAGES) {
    console.error('  ' + p.slug);
    try {
      await mPage.goto(p.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await mPage.waitForTimeout(2500);
      findings.mobile.push(await inspectPage(mPage, p.slug));
    } catch (e) { findings.mobile.push({ slug: p.slug, error: e.message }); }
  }
  await mCtx.close();
  await browser.close();
  console.log(JSON.stringify(findings, null, 2));
})();
