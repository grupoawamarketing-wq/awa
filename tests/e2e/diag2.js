const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ 
    executablePath: '/home/deploy/.cache/ms-playwright/chromium_headless_shell-1208/chrome-headless-shell-linux64/chrome-headless-shell',
    args: ['--no-sandbox', '--disable-dev-shm-usage']
  });
  const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(3000);
  
  const r = await page.evaluate(() => {
    const m = (sel, root) => {
      const container = root || document;
      const el = container.querySelector(sel);
      if (!el) return 'NOT_FOUND';
      const rect = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return `h=${Math.round(rect.height)} y=${Math.round(rect.y)} vis=${cs.visibility} disp=${cs.display}`;
    };
    
    const header = document.querySelector('[data-awa-header-content]');
    
    return {
      // Inside [data-awa-header-content]
      '.logo (inside header)': m('.logo', header),
      '.top-search (inside header)': m('.top-search', header),
      '.block-search (inside header)': m('.block-search', header),
      '.awa-main-header__inner (inside header)': m('.awa-main-header__inner', header),
      '.awa-header-minicart (inside header)': m('.awa-header-minicart', header),
      '.mini-cart-wrapper (inside header)': m('.mini-cart-wrapper', header),
      '.awa-header-mobile-toggle (inside header)': m('.awa-header-mobile-toggle', header),
      '.awa-header-contact-slot (inside header)': m('.awa-header-contact-slot', header),
      '.awa-header-brand-cell (inside header)': m('.awa-header-brand-cell', header),
      // Sticky
      '.header-wrapper-sticky (doc)': m('.header-wrapper-sticky'),
      // After scroll
    };
  });
  
  console.log('\n=== 1366x768 — Inside [data-awa-header-content] ===');
  for (const [k,v] of Object.entries(r)) console.log(`  ${k}: ${v}`);
  
  // Check after scroll
  await page.evaluate(() => window.scrollTo({ top: 600, behavior: 'instant' }));
  await page.waitForTimeout(500);
  
  const r2 = await page.evaluate(() => {
    const el = document.querySelector('.header-wrapper-sticky');
    if (!el) return { sticky: 'NOT_FOUND' };
    const rect = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return {
      'sticky after scroll': `h=${Math.round(rect.height)} y=${Math.round(rect.y)} pos=${cs.position} vis=${cs.visibility}`,
    };
  });
  console.log('\n--- After scroll 600px ---');
  for (const [k,v] of Object.entries(r2)) console.log(`  ${k}: ${v}`);
  
  await browser.close();
})();
