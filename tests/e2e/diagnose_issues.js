const { chromium } = require('@playwright/test');
const fs = require('fs');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });

  // Check login page overflow
  await page.goto('https://awamotos.com/customer/account/login/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3000);

  const result = await page.evaluate(() => {
    // Find all elements causing overflow
    const overflowing = [];
    document.querySelectorAll('*').forEach(el => {
      const rect = el.getBoundingClientRect();
      if (rect.right > 375) {
        const cs = getComputedStyle(el);
        overflowing.push({
          tag: el.tagName.toLowerCase(),
          classes: el.className?.toString?.()?.slice(0, 80) || '',
          id: el.id || '',
          right: Math.round(rect.right),
          width: Math.round(rect.width),
          left: Math.round(rect.left),
          display: cs.display,
          position: cs.position,
          overflow: cs.overflow,
        });
      }
    });
    // Sort by how far they stick out
    overflowing.sort((a, b) => b.right - a.right);
    return overflowing.slice(0, 20);
  });

  fs.writeFileSync('/tmp/overflow_elements.json', JSON.stringify(result, null, 2));
  console.log('Top overflowing elements:');
  result.forEach(el => {
    console.log(`  ${el.tag}.${el.classes.slice(0,40)} | right=${el.right} width=${el.width} pos=${el.position}`);
  });

  // Check search bar layout on category page
  await page.goto('https://awamotos.com/bagageiros.html', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3000);

  const searchLayout = await page.evaluate(() => {
    const searchCol = document.querySelector('.awa-header-search-col');
    const searchInput = document.querySelector('#search');
    const searchBtn = document.querySelector('.action.search');
    const searchForm = document.querySelector('#search_mini_form');
    const searchField = document.querySelector('.field.search');
    const searchActions = document.querySelector('#search_mini_form .actions');
    
    const getInfo = (el, name) => {
      if (!el) return { name, found: false };
      const r = el.getBoundingClientRect();
      const s = getComputedStyle(el);
      return {
        name,
        found: true,
        rect: { top: Math.round(r.top), left: Math.round(r.left), width: Math.round(r.width), height: Math.round(r.height) },
        display: s.display,
        position: s.position,
        flexDirection: s.flexDirection,
        flexWrap: s.flexWrap,
        alignItems: s.alignItems,
        width: s.width,
        maxWidth: s.maxWidth,
      };
    };

    return {
      searchCol: getInfo(searchCol, 'searchCol'),
      searchForm: getInfo(searchForm, 'searchForm'),
      searchField: getInfo(searchField, 'searchField'),
      searchInput: getInfo(searchInput, 'searchInput'),
      searchActions: getInfo(searchActions, 'searchActions'),
      searchBtn: getInfo(searchBtn, 'searchBtn'),
    };
  });

  fs.writeFileSync('/tmp/search_layout.json', JSON.stringify(searchLayout, null, 2));
  console.log('\nSearch layout on category page:');
  Object.values(searchLayout).forEach(el => {
    if (el.found) {
      console.log(`  ${el.name}: ${el.rect.width}x${el.rect.height} @ (${el.rect.left},${el.rect.top}) display=${el.display} pos=${el.position} flex=${el.flexDirection || '-'}`);
    }
  });

  await browser.close();
})();
