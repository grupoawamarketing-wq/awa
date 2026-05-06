const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  
  const results = {};
  
  // Desktop 1366px
  await page.setViewportSize({ width: 1366, height: 768 });
  
  // Homepage
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(3000);
  await page.screenshot({ path: '/tmp/desktop-home.png', fullPage: false });
  results.desktopHome = await page.evaluate(() => {
    const body = document.body;
    const overflow = document.documentElement.scrollWidth > window.innerWidth;
    const header = document.querySelector('.wp-header');
    const headerCs = header ? getComputedStyle(header) : null;
    const logo = document.querySelector('.wp-header .logo img, .wp-header .awa-header-brand-cell img');
    const search = document.querySelector('.wp-header .top-search, .wp-header .awa-header-search-col');
    const rightCol = document.querySelector('.wp-header .awa-header-right-col');
    return {
      overflow,
      headerDisplay: headerCs ? headerCs.display : null,
      headerHeight: header ? header.offsetHeight : null,
      logoHeight: logo ? logo.offsetHeight : null,
      logoCut: logo ? (logo.getBoundingClientRect().bottom > (header ? header.getBoundingClientRect().bottom : 999)) : null,
      searchWidth: search ? search.offsetWidth : null,
      rightColWidth: rightCol ? rightCol.offsetWidth : null,
      bodyClasses: body.className.substring(0, 100)
    };
  });
  
  // Category page
  await page.goto('https://awamotos.com/bagageiros.html', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(3000);
  await page.screenshot({ path: '/tmp/desktop-category.png', fullPage: false });
  results.desktopCategory = await page.evaluate(() => {
    const overflow = document.documentElement.scrollWidth > window.innerWidth;
    const header = document.querySelector('.wp-header');
    const headerCs = header ? getComputedStyle(header) : null;
    const logo = document.querySelector('.wp-header .logo img, .wp-header .awa-header-brand-cell img');
    const search = document.querySelector('.wp-header .top-search, .wp-header .awa-header-search-col');
    const rightCol = document.querySelector('.wp-header .awa-header-right-col');
    // Check for element overlaps in header
    const searchRect = search ? search.getBoundingClientRect() : null;
    const rightRect = rightCol ? rightCol.getBoundingClientRect() : null;
    const overlap = (searchRect && rightRect) ? searchRect.right > rightRect.left : null;
    return {
      overflow,
      headerDisplay: headerCs ? headerCs.display : null,
      headerHeight: header ? header.offsetHeight : null,
      logoHeight: logo ? logo.offsetHeight : null,
      logoCut: logo ? (logo.getBoundingClientRect().bottom > (header ? header.getBoundingClientRect().bottom : 999)) : null,
      searchWidth: search ? search.offsetWidth : null,
      rightColWidth: rightCol ? rightCol.offsetWidth : null,
      overlap
    };
  });
  
  // Product page
  await page.goto('https://awamotos.com/bagageiro-honda-cg-160-titan-fan-start-tubular.html', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(3000);
  await page.screenshot({ path: '/tmp/desktop-product.png', fullPage: false });
  results.desktopProduct = await page.evaluate(() => {
    const overflow = document.documentElement.scrollWidth > window.innerWidth;
    const header = document.querySelector('.wp-header');
    const headerCs = header ? getComputedStyle(header) : null;
    return {
      overflow,
      headerDisplay: headerCs ? headerCs.display : null,
      headerHeight: header ? header.offsetHeight : null
    };
  });
  
  // Mobile 375px
  await page.setViewportSize({ width: 375, height: 812 });
  
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(3000);
  await page.screenshot({ path: '/tmp/mobile-home.png', fullPage: false });
  results.mobileHome = await page.evaluate(() => {
    return {
      overflow: document.documentElement.scrollWidth > 375,
      scrollWidth: document.documentElement.scrollWidth,
      headerHeight: document.querySelector('.wp-header') ? document.querySelector('.wp-header').offsetHeight : null
    };
  });
  
  await page.goto('https://awamotos.com/bagageiros.html', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(3000);
  await page.screenshot({ path: '/tmp/mobile-category.png', fullPage: false });
  results.mobileCategory = await page.evaluate(() => {
    return {
      overflow: document.documentElement.scrollWidth > 375,
      scrollWidth: document.documentElement.scrollWidth,
      headerHeight: document.querySelector('.wp-header') ? document.querySelector('.wp-header').offsetHeight : null
    };
  });
  
  await browser.close();
  console.log(JSON.stringify(results, null, 2));
})();
