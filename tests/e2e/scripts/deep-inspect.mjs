import { chromium } from 'playwright';
const browser = await chromium.launch({ args: ['--no-sandbox'] });
const ctx = await browser.newContext({ viewport: { width: 1366, height: 768 } });
const page = await ctx.newPage();

// Home - header structure
await page.goto('https://awamotos.com/', { waitUntil: 'networkidle', timeout: 45000 });
const headerData = await page.evaluate(() => {
  const candidates = ['header', '.page-header', '.page-header-v2', '#header', '.header', '.page-wrapper > *:first-child'];
  const found = [];
  for (const sel of candidates) {
    const el = document.querySelector(sel);
    if (el) {
      const r = el.getBoundingClientRect();
      found.push({ sel, tag: el.tagName, cls: el.className.substring(0, 120), h: Math.round(r.height), w: Math.round(r.width) });
    }
  }
  const bodyKids = Array.from(document.body.children).slice(0, 5).map(c => ({
    tag: c.tagName, cls: c.className.substring(0, 80), h: Math.round(c.getBoundingClientRect().height), id: c.id
  }));
  const wrapperKids = Array.from(document.querySelector('.page-wrapper')?.children || []).slice(0, 6).map(c => ({
    tag: c.tagName, cls: c.className.substring(0, 80), h: Math.round(c.getBoundingClientRect().height)
  }));
  return { candidates: found, bodyTop: bodyKids, wrapperTop: wrapperKids };
});
console.log('=== HEADER STRUCTURE ===');
console.log(JSON.stringify(headerData, null, 2));

// Category - products
await page.goto('https://awamotos.com/bagageiros.html', { waitUntil: 'networkidle', timeout: 45000 });
const catData = await page.evaluate(() => {
  const items = document.querySelectorAll('.product-item, .product-item-info, li[class*=product]');
  const grid = document.querySelector('.products-grid, .products.wrapper, .products.list');
  const toolbar = document.querySelector('.toolbar-products .toolbar-amount');
  const mainContent = document.querySelector('.column.main, #maincontent');
  const b2b = document.querySelector('.b2b-login-required, .login-to-cart, [class*=b2b-restrict]');
  return {
    productCount: items.length,
    gridHtml: grid ? grid.outerHTML.substring(0, 500) : 'NO GRID',
    toolbarText: toolbar ? toolbar.textContent.trim() : 'NO TOOLBAR',
    mainHeight: mainContent ? Math.round(mainContent.getBoundingClientRect().height) : 0,
    b2bRestriction: b2b ? b2b.textContent.trim().substring(0, 200) : null,
    bodyClass: document.body.className.substring(0, 150)
  };
});
console.log('\n=== CATEGORY (bagageiros) ===');
console.log(JSON.stringify(catData, null, 2));

// PDP - CTA button
await page.goto('https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html', { waitUntil: 'networkidle', timeout: 45000 });
const pdpData = await page.evaluate(() => {
  const btn = document.querySelector('#product-addtocart-button, .action.tocart, button[class*=tocart]');
  const priceBox = document.querySelector('.product-info-price .price-box, .price-box');
  const h1 = document.querySelector('h1.page-title span, h1 .base');
  return {
    title: h1 ? h1.textContent.trim() : 'NO H1',
    btn: btn ? {
      text: btn.textContent.trim().substring(0, 50),
      display: getComputedStyle(btn).display,
      visibility: getComputedStyle(btn).visibility,
      opacity: getComputedStyle(btn).opacity,
      rect: { t: Math.round(btn.getBoundingClientRect().top), l: Math.round(btn.getBoundingClientRect().left), w: Math.round(btn.getBoundingClientRect().width), h: Math.round(btn.getBoundingClientRect().height) }
    } : 'NOT FOUND',
    price: priceBox ? { text: priceBox.textContent.trim().substring(0, 100), display: getComputedStyle(priceBox).display } : 'NOT FOUND'
  };
});
console.log('\n=== PDP ===');
console.log(JSON.stringify(pdpData, null, 2));

// B2B landing
await page.goto('https://awamotos.com/b2b', { waitUntil: 'networkidle', timeout: 45000 });
const b2bData = await page.evaluate(() => {
  const main = document.querySelector('.column.main, #maincontent');
  const h1 = document.querySelector('h1');
  const forms = document.querySelectorAll('form');
  const cards = document.querySelectorAll('[class*=card], [class*=benefit], .cms-content-important');
  return {
    h1: h1 ? h1.textContent.trim() : 'NO H1',
    mainHeight: main ? Math.round(main.getBoundingClientRect().height) : 0,
    formCount: forms.length,
    cardCount: cards.length,
    bodyClass: document.body.className.substring(0, 150)
  };
});
console.log('\n=== B2B LANDING ===');
console.log(JSON.stringify(b2bData, null, 2));

// Login page - check footer visibility issue
await page.goto('https://awamotos.com/customer/account/login/', { waitUntil: 'networkidle', timeout: 45000 });
const loginData = await page.evaluate(() => {
  const footer = document.querySelector('.page-footer, footer, .footer');
  const loginForm = document.querySelector('#login-form, form.form-login, [class*=login]');
  return {
    footerExists: !!footer,
    footerDisplay: footer ? getComputedStyle(footer).display : null,
    footerHeight: footer ? Math.round(footer.getBoundingClientRect().height) : 0,
    loginFormExists: !!loginForm,
    loginFormHeight: loginForm ? Math.round(loginForm.getBoundingClientRect().height) : 0
  };
});
console.log('\n=== LOGIN ===');
console.log(JSON.stringify(loginData, null, 2));

await browser.close();
