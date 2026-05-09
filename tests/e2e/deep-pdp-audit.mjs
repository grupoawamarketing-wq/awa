import pkg from '@playwright/test';
const { firefox } = pkg;

const browser = await firefox.launch({
  executablePath: '/home/deploy/.cache/ms-playwright/firefox-1511/firefox/firefox',
  headless: true,
});
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();

await page.goto('https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html', { waitUntil: 'domcontentloaded', timeout: 60000 });
await page.waitForTimeout(2000);

const audit = await page.evaluate(() => {
  const s = (sel) => { const el = document.querySelector(sel); return el ? getComputedStyle(el) : null; };
  const sw = (sel, prop) => { const cs = s(sel); return cs ? cs[prop] : null; };

  // Find all elements that overflow beyond viewport
  const overflows = [];
  document.querySelectorAll('*').forEach(el => {
    try {
      const rect = el.getBoundingClientRect();
      if (rect.right > window.innerWidth + 10 || rect.left < -10) {
        const cs = getComputedStyle(el);
        const className = typeof el.className === 'string' ? el.className.split(' ').slice(0, 3).join('.') : el.tagName;
        overflows.push({
          tag: el.tagName,
          class: className,
          left: Math.round(rect.left),
          right: Math.round(rect.right),
          width: Math.round(rect.width),
          overflow: cs.overflow,
          overflowX: cs.overflowX,
          parent: el.parentElement ? (typeof el.parentElement.className === 'string' ? el.parentElement.className.split(' ').slice(0, 2).join('.') : el.parentElement.tagName) : 'body'
        });
      }
    } catch(e) {}
  });

  // Specific checks for PDP
  return {
    // Related products section
    relatedSection: {
      exists: !!document.querySelector('.related, .block-related, .crosssell'),
      overflow: sw('.related', 'overflowX'),
      width: sw('.related', 'width'),
    },
    // Product images
    productMedia: {
      width: sw('.product.media, .product-images-container', 'width'),
      overflow: sw('.product.media, .product-images-container', 'overflow'),
    },
    // Product info
    productInfo: {
      width: sw('.product-info-main, .product-info-wrapper', 'width'),
      padding: sw('.product-info-main, .product-info-wrapper', 'padding'),
    },
    // Price
    price: {
      display: sw('.price-box, .price-wrapper', 'display'),
      fontSize: sw('.price-box .price, .price-wrapper .price', 'fontSize'),
      color: sw('.price-box .price, .price-wrapper .price', 'color'),
      fontWeight: sw('.price-box .price, .price-wrapper .price', 'fontWeight'),
    },
    // Add to cart button
    addToCart: {
      bg: sw('.action.tocart, #product-addtocart-button', 'backgroundColor'),
      radius: sw('.action.tocart, #product-addtocart-button', 'borderRadius'),
      height: sw('.action.tocart, #product-addtocart-button', 'height'),
      padding: sw('.action.tocart, #product-addtocart-button', 'padding'),
      width: sw('.action.tocart, #product-addtocart-button', 'width'),
    },
    // Breadcrumb
    breadcrumb: {
      padding: sw('.breadcrumbs', 'padding'),
      margin: sw('.breadcrumbs', 'margin'),
      fontSize: sw('.breadcrumbs a, .breadcrumbs li', 'fontSize'),
      color: sw('.breadcrumbs a', 'color'),
    },
    // Reviews
    reviews: {
      exists: !!document.querySelector('.product-reviews-summary, .reviews-actions'),
    },
    // Tabs (description/reviews)
    tabs: {
      exists: !!document.querySelector('.product.info.detailed, .product-tabs'),
      bg: sw('.product.info.detailed, .product-tabs', 'backgroundColor'),
      border: sw('.product.info.detailed, .product-tabs', 'border'),
    },
    // Related products grid
    relatedGrid: (() => {
      const grid = document.querySelector('.related .product-items, .crosssell .product-items, .related .products-grid');
      if (!grid) return null;
      const cs = getComputedStyle(grid);
      return {
        display: cs.display,
        overflow: cs.overflow,
        overflowX: cs.overflowX,
        width: cs.width,
        gridTemplateColumns: cs.gridTemplateColumns,
      };
    })(),
    // Swiper containers
    swipers: Array.from(document.querySelectorAll('[class*="swiper"], [class*="owl"]')).slice(0, 5).map(el => ({
      class: typeof el.className === 'string' ? el.className.split(' ').slice(0, 2).join('.') : el.tagName,
      overflow: getComputedStyle(el).overflow,
      overflowX: getComputedStyle(el).overflowX,
      width: getComputedStyle(el).width,
      maxWidth: getComputedStyle(el).maxWidth,
    })),
    overflows: overflows.slice(0, 15),
    scrollWidth: document.documentElement.scrollWidth,
    innerWidth: window.innerWidth,
    hasHorizontalScroll: document.documentElement.scrollWidth > window.innerWidth,
  };
});

console.log(JSON.stringify(audit, null, 2));
await browser.close();
