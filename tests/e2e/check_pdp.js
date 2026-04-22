const { chromium } = require('playwright');
(async () => {
  const PDP_URL = 'https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html';
  const browser = await chromium.launch({ 
    executablePath: '/home/deploy/.cache/ms-playwright/chromium_headless_shell-1208/chrome-headless-shell-linux64/chrome-headless-shell',
    args: ['--no-sandbox', '--disable-dev-shm-usage']
  });

  const checks = [];
  const c = (name, status, value) => checks.push({ name, status, value });

  for (const [label, vw, vh] of [['Desktop 1366', 1366, 768], ['Mobile 375', 375, 812]]) {
    const page = await browser.newPage({ viewport: { width: vw, height: vh } });
    await page.goto(PDP_URL, { waitUntil: 'domcontentloaded', timeout: 25000 });
    await page.waitForTimeout(2000);

    const r = await page.evaluate(() => {
      const m = (sel) => {
        const el = document.querySelector(sel);
        if (!el) return null;
        const rect = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        return { h: Math.round(rect.height), w: Math.round(rect.width), disp: cs.display, vis: cs.visibility };
      };
      const hasHScroll = document.documentElement.scrollWidth > document.documentElement.clientWidth + 2;
      return {
        hScroll: hasHScroll,
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
        // Imagem principal
        mainImage: m('.product.media .fotorama__img, .product.media img.photo, .gallery-placeholder img'),
        // Título
        productName: m('.product-info-main .page-title, h1.page-title'),
        // Preço
        price: m('.product-info-price .price-box, .price-container'),
        // Botão Add to Cart
        addToCart: m('#product-addtocart-button, .action.tocart'),
        // Fitment / compatibilidade
        fitment: m('.awa-fitment, .awa-fitment-widget, [data-awa-component="fitment"]'),
        // SKU
        sku: m('.product.attribute.sku'),
        // Tabs / accordions 
        tabs: m('.data.tabs, .product-info-additional, .awa-pdp-tabs'),
        // Related products
        related: m('.block.related, .products-related'),
        // Breadcrumb
        breadcrumb: m('.breadcrumbs'),
        // Social share / schema
        socialShare: m('.product-social-links, .tocompare'),
        // B2B gate (login to see price)
        b2bGate: m('.b2b-login-to-see-price, .awa-b2b-price-gate'),
        // Estoque / disponibilidade
        stockStatus: m('.stock.available, .stock.unavailable, .product-info-stock-sku .stock'),
      };
    });

    c(`[${label}] Overflow horizontal`, r.hScroll ? 'FAIL' : 'OK', r.hScroll ? `${r.scrollWidth}px > ${r.clientWidth}px` : 'ok');
    c(`[${label}] Imagem principal`, r.mainImage ? 'OK' : 'WARN', r.mainImage ? `${r.mainImage.w}x${r.mainImage.h}` : 'NOT_FOUND');
    c(`[${label}] Título (h1)`, r.productName ? 'OK' : 'FAIL', r.productName ? `h=${r.productName.h}` : 'NOT_FOUND');
    c(`[${label}] Preço`, r.price ? 'OK' : 'FAIL', r.price ? `${r.price.w}x${r.price.h}` : 'NOT_FOUND');
    c(`[${label}] Add to Cart`, r.addToCart ? 'OK' : 'FAIL', r.addToCart ? `disp=${r.addToCart.disp} h=${r.addToCart.h}` : 'NOT_FOUND');
    c(`[${label}] Breadcrumb`, r.breadcrumb ? 'OK' : 'WARN', r.breadcrumb ? `h=${r.breadcrumb.h}` : 'NOT_FOUND');
    c(`[${label}] Fitment widget`, r.fitment ? 'OK' : 'WARN', r.fitment ? `${r.fitment.w}x${r.fitment.h}` : 'NOT_FOUND');
    c(`[${label}] Tabs/accordion`, r.tabs ? 'OK' : 'WARN', r.tabs ? `h=${r.tabs.h}` : 'NOT_FOUND');
    c(`[${label}] SKU`, r.sku ? 'OK' : 'WARN', r.sku ? 'presente' : 'NOT_FOUND');
    c(`[${label}] Stock status`, r.stockStatus ? 'OK' : 'WARN', r.stockStatus ? `disp=${r.stockStatus.disp}` : 'NOT_FOUND');
    c(`[${label}] Produtos relacionados`, r.related ? 'OK' : 'WARN', r.related ? `h=${r.related.h}` : 'NOT_FOUND');

    // Screenshots
    await page.screenshot({ path: `/tmp/pdp-${vw}.png`, clip: { x: 0, y: 0, width: vw, height: Math.min(1200, vh * 2) } });
    console.log(`[${label}] screenshot salvo: /tmp/pdp-${vw}.png`);
    await page.close();
  }

  // Tablet
  const tPage = await browser.newPage({ viewport: { width: 768, height: 1024 } });
  await tPage.goto(PDP_URL, { waitUntil: 'domcontentloaded', timeout: 25000 });
  await tPage.waitForTimeout(1500);
  const tR = await tPage.evaluate(() => ({
    hScroll: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
    image: document.querySelector('.fotorama__img, .product.media img')?.getBoundingClientRect(),
    price: document.querySelector('.price-box')?.getBoundingClientRect(),
  }));
  checks.push({ name: '[Tablet 768] Overflow horizontal', status: tR.hScroll ? 'FAIL' : 'OK', value: tR.hScroll ? 'overflow' : 'ok' });
  checks.push({ name: '[Tablet 768] Layout imagem+preço', status: (tR.image && tR.price) ? 'OK' : 'WARN', value: tR.image ? `img=${Math.round(tR.image.w)}x${Math.round(tR.image.h)} price_y=${Math.round(tR.price?.y||0)}` : 'sem imagem' });
  await tPage.screenshot({ path: '/tmp/pdp-768.png', clip: { x: 0, y: 0, width: 768, height: 1200 } });
  await tPage.close();

  await browser.close();

  console.log('\n=== PDP Health Check ===');
  for (const chk of checks) {
    const icon = chk.status === 'OK' ? '✓' : chk.status === 'FAIL' ? '✗' : '⚠';
    console.log(`${icon} ${chk.name}: ${chk.value}`);
  }
  const fails = checks.filter(c => c.status === 'FAIL').length;
  const warns = checks.filter(c => c.status === 'WARN').length;
  console.log(`\nResultado: ${fails} FAIL | ${warns} WARN | ${checks.filter(c=>c.status==='OK').length} OK`);
})();
