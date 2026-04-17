import { chromium } from 'playwright';

const browser = await chromium.launch({
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage']
});
const page = await browser.newPage({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1 });
await page.route('**/*', route => {
  const type = route.request().resourceType();
  if (type === 'font' || type === 'media') {
    return route.abort();
  }
  return route.continue();
});
await page.goto('https://awamotos.com', { waitUntil: 'domcontentloaded', timeout: 30000 });
await page.waitForTimeout(3000);

const data = await page.evaluate(() => {
  const HEADER_ROW_SELECTOR = '.awa-main-header__inner[data-awa-header-row], .wp-header[data-awa-header-row]';
  const r = {};
  const navBarInner = document.querySelector('.awa-nav-bar__inner');
  if (navBarInner) {
    const cs = getComputedStyle(navBarInner);
    r.navBarInner = { w: navBarInner.offsetWidth, maxW: cs.maxWidth, padL: cs.paddingLeft, padR: cs.paddingRight };
  }
  const cats = document.querySelector('.awa-header-categories');
  if (cats) r.cats = { w: cats.offsetWidth, l: Math.round(cats.getBoundingClientRect().left), pad: getComputedStyle(cats).padding };
  const primaryNav = document.querySelector('.awa-header-primary-nav');
  if (primaryNav) r.primaryNav = { w: primaryNav.offsetWidth, l: Math.round(primaryNav.getBoundingClientRect().left) };
  const locale = document.querySelector('.awa-header-locale');
  if (locale) r.locale = { w: locale.offsetWidth };
  const wpHeader = document.querySelector(HEADER_ROW_SELECTOR);
  if (wpHeader) {
    const cs = getComputedStyle(wpHeader);
    r.wpHeader = { w: wpHeader.offsetWidth, maxW: cs.maxWidth, gridCols: cs.gridTemplateColumns, l: Math.round(wpHeader.getBoundingClientRect().left) };
  }
  const logo = document.querySelector(`${HEADER_ROW_SELECTOR} .logo`);
  if (logo) r.logo = { l: Math.round(logo.getBoundingClientRect().left) };
  const dept = document.querySelector('.our_categories.title-category-dropdown');
  if (dept) {
    const dRect = dept.getBoundingClientRect();
    r.dept = { w: dept.offsetWidth, l: Math.round(dRect.left), r: Math.round(dRect.right) };
  }
  const navLinks = document.querySelectorAll('.main-nav-list li.level0 > a');
  r.links = [];
  navLinks.forEach(a => {
    const rect = a.getBoundingClientRect();
    r.links.push({ text: a.textContent.trim(), l: Math.round(rect.left), w: Math.round(rect.width), pad: getComputedStyle(a).padding });
  });
  const colSticky = document.querySelector('.col-sticky-logo');
  if (colSticky) r.colSticky = { w: colSticky.offsetWidth, display: getComputedStyle(colSticky).display };
  const logoSticky = document.querySelector('.logo-sticky');
  if (logoSticky) r.logoSticky = { w: logoSticky.offsetWidth, display: getComputedStyle(logoSticky).display };
  if (r.logo && r.dept) r.alignLogoToDept = r.dept.l - r.logo.l;
  // Check vertical alignment
  const navBar = document.querySelector('.awa-nav-bar');
  if (navBar) r.navBar = { h: navBar.offsetHeight, borderTop: getComputedStyle(navBar).borderTopWidth };
  const chs = document.querySelector('.container-header-sticky');
  if (chs) r.containerSticky = { display: getComputedStyle(chs).display, w: chs.offsetWidth };
  return r;
});

console.log(JSON.stringify(data, null, 2));

await page.screenshot({ path: '/tmp/header-audit.png', clip: { x: 0, y: 0, width: 1920, height: 200 } });
console.log('Screenshot saved to /tmp/header-audit.png');

await browser.close();
