/**
 * AWA Home Impeccable Audit — runtime probe
 */
import { chromium } from 'playwright';
import fs from 'fs';

const URL = 'https://awamotos.com/';
const LOG_PATH = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.impeccable/audit/home-probe-2026-06-16.ndjson';
const VIEWPORTS = [
  { w: 390, h: 844, id: 'mobile-390' },
  { w: 768, h: 1024, id: 'tablet-768' },
  { w: 1366, h: 768, id: 'desktop-1366' },
  { w: 1920, h: 1080, id: 'desktop-1920' },
];

fs.mkdirSync('/home/jessessh/htdocs/srv1113343.hstgr.cloud/.impeccable/audit', { recursive: true });
if (fs.existsSync(LOG_PATH)) fs.unlinkSync(LOG_PATH);

function log(entry) {
  fs.appendFileSync(LOG_PATH, JSON.stringify({ ...entry, timestamp: Date.now() }) + '\n');
}

function probePage() {
  const rect = (sel) => {
    const el = document.querySelector(sel);
    return el ? el.getBoundingClientRect() : null;
  };
  const cs = (sel, prop) => {
    const el = document.querySelector(sel);
    return el ? getComputedStyle(el)[prop] : null;
  };

  const hidden = document.querySelector('.wrapper_slider.hidden-xs');
  const visible = document.querySelector('.wrapper_slider.visible-xs');
  const activeSlider = hidden && getComputedStyle(hidden).display !== 'none' ? 'hidden-xs' : 'visible-xs';
  const heroImg = document.querySelector(
    activeSlider === 'hidden-xs'
      ? '.wrapper_slider.hidden-xs .banner_item_bg img'
      : '.wrapper_slider.visible-xs .banner_item_bg img'
  );

  const header = document.querySelector('.awa-site-header, #header');
  const headerH = header ? header.getBoundingClientRect().height : 0;

  const touchTargets = [...document.querySelectorAll('.awa-site-header a, .awa-site-header button, .minicart-wrapper')]
    .slice(0, 8)
    .map((el) => {
      const r = el.getBoundingClientRect();
      return { w: r.width, h: r.height, ok: r.width >= 44 && r.height >= 44 };
    });

  const benefits = document.querySelector('.awa-benefits-bar .awa-benefits-container, .velaServicesInner--home5');
  const benefitItems = benefits ? benefits.querySelectorAll('.awa-benefit-item, .velaServices, .item').length : 0;

  const catCards = [...document.querySelectorAll('.awa-category-carousel .awa-category-card, .awa-category-carousel__track .category-item, .top-home-content--category-carousel .category-item')];
  const catHeights = catCards.map((c) => c.getBoundingClientRect().height).filter((h) => h > 0);
  const catIcons = catCards.slice(0, 8).map((c) => {
    const img = c.querySelector('img');
    if (!img) return { loaded: false, src: null };
    return { loaded: img.complete && img.naturalWidth > 0, src: img.src?.slice(-40), w: img.naturalWidth, h: img.naturalHeight };
  });

  const shelves = [...document.querySelectorAll('.awa-carousel-section, .top-home-content.awa-carousel-section')];
  const shelfData = shelves.slice(0, 6).map((s) => {
    const title = s.querySelector('.awa-section-header__title, h2, .awa-shelf__title')?.textContent?.trim()?.slice(0, 40);
    const cards = [...s.querySelectorAll('.product-item, .item-product')];
    const heights = cards.map((c) => c.getBoundingClientRect().height).filter((h) => h > 0);
    const brokenImgs = cards.filter((c) => {
      const img = c.querySelector('img.product-image-photo, .product-thumb img');
      return img && (!img.complete || img.naturalWidth === 0 || img.src.includes('placeholder'));
    }).length;
    const ctaVisible = cards.some((c) => {
      const btn = c.querySelector('.b2b-login-to-see-price, .price-label a, .tocart');
      return btn && btn.getBoundingClientRect().height >= 30;
    });
    return {
      title,
      cardCount: cards.length,
      heightMin: heights.length ? Math.min(...heights) : 0,
      heightMax: heights.length ? Math.max(...heights) : 0,
      heightSpread: heights.length ? Math.max(...heights) - Math.min(...heights) : 0,
      brokenImgs,
      ctaVisible,
    };
  });

  const shellSelectors = [
    '.awa-header-inner',
    '.content-top-home .top-home-content.awa-home-section > .container',
    '.content-top-home .awa-carousel-section > .container',
    '.page_footer .footer-container',
  ];
  const shellWidths = shellSelectors.map((sel) => {
    const el = document.querySelector(sel);
    if (!el) return { sel, width: null };
    return { sel, width: Math.round(el.getBoundingClientRect().width) };
  });

  const footer = document.querySelector('.page_footer, .page-footer');
  const footerBorder = footer ? getComputedStyle(footer).borderTopWidth : null;

  return {
    global: {
      scrollOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
      scrollWidth: document.documentElement.scrollWidth,
      innerWidth: window.innerWidth,
    },
    header: { height: Math.round(headerH), touchTargets },
    hero: {
      activeSlider,
      hiddenDisplay: hidden ? getComputedStyle(hidden).display : null,
      visibleDisplay: visible ? getComputedStyle(visible).display : null,
      imgH: heroImg ? Math.round(heroImg.getBoundingClientRect().height) : 0,
      imgW: heroImg ? Math.round(heroImg.getBoundingClientRect().width) : 0,
      objectFit: heroImg ? getComputedStyle(heroImg).objectFit : null,
    },
    benefits: { itemCount: benefitItems, height: benefits ? Math.round(benefits.getBoundingClientRect().height) : 0 },
    categories: {
      cardCount: catCards.length,
      heightSpread: catHeights.length ? Math.max(...catHeights) - Math.min(...catHeights) : 0,
      icons: catIcons,
    },
    shelves: shelfData,
    containers: { shellWidths, viewport: window.innerWidth },
    footer: { exists: !!footer, borderTop: footerBorder },
  };
}

const browser = await chromium.launch({ headless: true });
const consoleErrors = [];
const failed404 = [];

for (const vp of VIEWPORTS) {
  const page = await browser.newPage({ viewport: { width: vp.w, height: vp.h } });
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push({ vp: vp.id, text: msg.text().slice(0, 200) });
  });
  page.on('response', (resp) => {
    if (resp.status() === 404 && /\\.(css|js|png|jpg|webp|woff)/i.test(resp.url())) {
      failed404.push({ vp: vp.id, url: resp.url().slice(-80) });
    }
  });

  await page.goto(URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(4000);

  const data = await page.evaluate(probePage);
  log({ viewport: vp.id, w: vp.w, section: 'full-page', data });

  await page.screenshot({ path: `/tmp/awa-home-${vp.id}-full.png`, fullPage: false });
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight * 0.5));
  await page.waitForTimeout(500);
  await page.screenshot({ path: `/tmp/awa-home-${vp.id}-mid.png`, fullPage: false });

  await page.close();
}

await browser.close();

const summary = {
  consoleErrors: consoleErrors.slice(0, 20),
  failed404: [...new Map(failed404.map((x) => [x.url, x])).values()].slice(0, 20),
};
log({ section: 'summary', data: summary });
console.log(JSON.stringify(summary, null, 2));
console.log('Log:', LOG_PATH);
