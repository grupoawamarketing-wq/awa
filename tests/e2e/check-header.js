const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1'
  });
  const page = await context.newPage();
  
  await page.goto('https://awamotos.com/', { waitUntil: 'networkidle' });

  // Get all header elements bounding boxes
  const headerLayout = await page.evaluate(() => {
    const getBox = (selector) => {
      const el = document.querySelector(selector);
      if (!el) return null;
      const r = el.getBoundingClientRect();
      return { id: selector, x: r.x, y: r.y, w: r.width, h: r.height, display: window.getComputedStyle(el).display };
    };
    return [
      getBox('.awa-site-header'),
      getBox('.logo'),
      getBox('.block-search'),
      getBox('.minicart-wrapper'),
      getBox('.nav-toggle'),
      getBox('.header-main'),
      getBox('.header_main')
    ].filter(x => x);
  });
  
  console.log("MOBILE HEADER LAYOUT:");
  console.table(headerLayout);
  
  await browser.close();
})();
