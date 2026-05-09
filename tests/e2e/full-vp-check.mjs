import pkg from '@playwright/test';
const { firefox } = pkg;

const browser = await firefox.launch({
  executablePath: '/home/deploy/.cache/ms-playwright/firefox-1511/firefox/firefox',
  headless: true,
});

async function audit(url, label, vp = { width: 1440, height: 900 }) {
  const ctx = await browser.newContext({ viewport: vp });
  const page = await ctx.newPage();
  try {
    await Promise.race([
      page.goto(url, { waitUntil: 'domcontentloaded', timeout: 55000 }),
      new Promise((_, r) => setTimeout(() => r(new Error('timeout')), 50000))
    ]);
    await page.waitForTimeout(4000);

    return await page.evaluate(() => {
      const sw = (sel, prop) => {
        const el = document.querySelector(sel);
        return el ? getComputedStyle(el)[prop] : null;
      };
      const has = sel => !!document.querySelector(sel);

      // find product card selectors
      const cardSelectors = ['.product-item', '.item-product', '.product-item-info', '[class*="product-item"]'];
      let cardSel = null, cardShadow = null, cardBorder = null;
      for (const s of cardSelectors) {
        const el = document.querySelector(s);
        if (el) { cardSel = s; cardShadow = getComputedStyle(el).boxShadow; cardBorder = getComputedStyle(el).borderColor; break; }
      }

      // breadcrumb
      const bcEl = document.querySelector('.breadcrumbs');
      const bcPad = bcEl ? getComputedStyle(bcEl).padding : null;
      const bcPadTop = bcEl ? getComputedStyle(bcEl).paddingTop : null;

      // inputs
      const inp = document.querySelector('input[type="text"], input[type="search"], input[type="email"]');
      const inpBorder = inp ? getComputedStyle(inp).borderColor : null;
      const inpBorderWidth = inp ? getComputedStyle(inp).borderTopWidth : null;

      // footer
      const footer = document.querySelector('.page-footer');
      const ftBorderW = footer ? getComputedStyle(footer).borderTopWidth : null;
      const ftBorderC = footer ? getComputedStyle(footer).borderTopColor : null;

      // header sticky shadow
      const hdr = document.querySelector('.page-header');
      const hdrShadow = hdr ? getComputedStyle(hdr).boxShadow : null;

      // filter title (category)
      const filtTitle = document.querySelector('.filter-options-title');
      const filtFontW = filtTitle ? getComputedStyle(filtTitle).fontWeight : null;
      const filtTransform = filtTitle ? getComputedStyle(filtTitle).textTransform : null;

      // pagination
      const pgLink = document.querySelector('.pages .item a');
      const pgBorder = pgLink ? getComputedStyle(pgLink).borderRadius : null;

      // PDP price
      const priceEl = document.querySelector('.price-box .price');
      const priceFontSz = priceEl ? getComputedStyle(priceEl).fontSize : null;
      const priceColor = priceEl ? getComputedStyle(priceEl).color : null;

      // polish loaded
      const polishLoaded = Array.from(document.styleSheets).some(s => s.href && s.href.includes('polish-r2'));

      return {
        polishLoaded,
        footer: { width: ftBorderW, color: ftBorderC },
        card: { sel: cardSel, shadow: cardShadow, border: cardBorder },
        breadcrumb: { padding: bcPad, paddingTop: bcPadTop },
        input: { border: inpBorder, borderWidth: inpBorderWidth },
        header: { shadow: hdrShadow },
        filter: { fontWeight: filtFontW, textTransform: filtTransform },
        pagination: { borderRadius: pgBorder },
        price: { fontSize: priceFontSz, color: priceColor },
      };
    });
  } catch(e) {
    return { error: e.message };
  } finally {
    await ctx.close();
  }
}

const pages = [
  { url: 'https://awamotos.com/', label: 'HOME' },
  { url: 'https://awamotos.com/', label: 'HOME-MOBILE', vp: { width: 375, height: 812 } },
  { url: 'https://awamotos.com/barras-de-guidao.html', label: 'CATEGORY' },
  { url: 'https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html', label: 'PDP' },
];

const results = {};
for (const p of pages) {
  process.stdout.write(`Checking ${p.label}... `);
  results[p.label] = await audit(p.url, p.label, p.vp || { width: 1440, height: 900 });
  console.log('done');
}

// Summary
const checks = [
  { vp: 'VP-1', label: 'Footer border 1px #e5e5e5', fn: r => r.footer?.width === '1px' && !r.footer?.color?.includes('183, 51, 55') },
  { vp: 'VP-2', label: 'Card shadow presente', fn: r => r.card?.shadow && r.card.shadow !== 'none' && !r.card.shadow.includes('undefined') },
  { vp: 'VP-3', label: 'Card border #E5E5E5', fn: r => r.card?.border && !r.card.border.includes('15, 23, 42') },
  { vp: 'VP-5', label: 'Breadcrumb padding ≥12px (se presente)', fn: r => { const t = parseInt(r.breadcrumb?.paddingTop); return isNaN(t) || t >= 12; } },
  { vp: 'VP-10', label: 'Input border normalizado', fn: r => { const b = r.input?.border; const w = r.input?.borderWidth; if (!b || w === '0px') return true; return !b.includes('233, 236, 239') && !b.includes('0, 0, 0'); } },
  { vp: 'VP-11', label: 'Pagination border-radius ≥4px (se presente)', fn: r => { const br = parseInt(r.pagination?.borderRadius); return isNaN(br) || br >= 4; } },
  { vp: 'VP-8', label: 'PDP price color brand', fn: r => !r.price?.color || r.price.color.includes('183, 51, 55') || r.label === 'HOME' || r.label === 'HOME-MOBILE' || r.label === 'CATEGORY' },
];

console.log('\n=== FULL VP AUDIT ===\n');
for (const [label, r] of Object.entries(results)) {
  if (r.error) { console.log(`[${label}] ERROR: ${r.error}`); continue; }
  console.log(`--- ${label} ---`);
  console.log(`  VP-1 footer:   ${r.footer.width} ${r.footer.color}`);
  console.log(`  VP-2 card:     sel=${r.card.sel} shadow=${r.card.shadow?.substring(0,60)}`);
  console.log(`  VP-3 card brd: ${r.card.border}`);
  console.log(`  VP-5 breadcrb: paddingTop=${r.breadcrumb?.paddingTop}`);
  console.log(`  VP-10 input:   border=${r.input.border} w=${r.input.borderWidth}`);
  console.log(`  VP-11 pgn:     border-radius=${r.pagination.borderRadius}`);
  if (label === 'PDP') console.log(`  VP-8 price:    ${r.price.fontSize} ${r.price.color}`);
  if (label.includes('CATEGORY')) console.log(`  VP-6 filter:   fw=${r.filter.fontWeight} tt=${r.filter.textTransform}`);
}

// Pass/fail matrix
console.log('\n=== PASS/FAIL ===\n');
for (const chk of checks) {
  let pass = 0, fail = 0, failPages = [];
  for (const [label, r] of Object.entries(results)) {
    if (r.error) continue;
    const ok = chk.fn({ ...r, label });
    if (ok) pass++; else { fail++; failPages.push(label); }
  }
  const icon = fail === 0 ? '✅' : '❌';
  console.log(`${icon} ${chk.vp}: ${chk.label}${fail > 0 ? ' — FALHA em: ' + failPages.join(', ') : ''}`);
}

await browser.close();
