import { test, expect } from '@playwright/test';

const BASE = 'https://awamotos.com';

const AUDIT_PAGES = [
  { name: 'home', url: `${BASE}/`, containers: '.content-top-home .top-home-content.awa-home-section > .container' },
  { name: 'plp', url: `${BASE}/carcacas.html`, containers: '.page-main.container, .columns .column.main' },
  { name: 'pdp', url: `${BASE}/bagageiro-titan-150-09-13-modelo-preto-macico-3000.html`, containers: '.page-main.container, .main-detail' },
  { name: 'cart', url: `${BASE}/checkout/cart/`, containers: '.page-main.container, .cart-container' },
];

for (const vp of [
  { label: 'mobile', width: 390, height: 844 },
  { label: 'desktop', width: 1366, height: 768 },
]) {
  test(`deep audit ${vp.label}`, async ({ page }) => {
    test.setTimeout(180_000);
    await page.setViewportSize({ width: vp.width, height: vp.height });

    const report: Record<string, unknown> = {};

    for (const p of AUDIT_PAGES) {
      await page.goto(`${p.url}?deep=${Date.now()}`, { waitUntil: 'domcontentloaded', timeout: 90_000 });
      await page.waitForTimeout(2000);
      await page.evaluate(() => {
        (window as Window & { __awaApplyGatedCSS?: () => void }).__awaApplyGatedCSS?.();
      });
      await page.waitForTimeout(1500);

      report[p.name] = await page.evaluate((sel) => {
        const overflow = document.documentElement.scrollWidth - document.documentElement.clientWidth;
        const alignLinks = document.querySelectorAll('link[href*="align-grid-terminal"]').length;
        const els = Array.from(document.querySelectorAll(sel)).slice(0, 6);
        const lefts = els.map((el) => Math.round(el.getBoundingClientRect().left));
        const spread = lefts.length >= 2 ? Math.max(...lefts) - Math.min(...lefts) : 0;
        const token = getComputedStyle(document.body).getPropertyValue('--awa-grid-shell-max').trim();

        let cardDiff = 0;
        const carousel = document.querySelector('.awa-carousel-section, .products-grid .product-items');
        if (carousel) {
          const hs = Array.from(carousel.querySelectorAll('.content-item-product, .item-product'))
            .filter((el) => {
              const r = el.getBoundingClientRect();
              return r.width > 40 && r.height > 40;
            })
            .slice(0, 4)
            .map((el) => Math.round(el.getBoundingClientRect().height));
          if (hs.length >= 2) cardDiff = Math.max(...hs) - Math.min(...hs);
        }

        const header = document.querySelector('.content-top-home .awa-section-header');
        return {
          overflow,
          alignLinks,
          token,
          lefts: [...new Set(lefts)],
          spread,
          cardDiff,
          sectionHeaderPad: header ? getComputedStyle(header).paddingInline : null,
        };
      }, p.containers);
    }

    console.log(`\n=== DEEP AUDIT ${vp.label} ===\n${JSON.stringify(report, null, 2)}`);

    for (const [name, m] of Object.entries(report)) {
      const data = m as { overflow: number; alignLinks: number; spread: number; cardDiff: number };
      expect(data.overflow, `${name} overflow`).toBeLessThanOrEqual(2);
      expect(data.alignLinks, `${name} align-grid`).toBeGreaterThanOrEqual(1);
      if (data.spread > 0) expect(data.spread, `${name} container spread`).toBeLessThanOrEqual(32);
      if (name === 'home' || name === 'plp') {
        if (data.cardDiff > 0) expect(data.cardDiff, `${name} card heights`).toBeLessThanOrEqual(16);
      }
    }
  });
}
