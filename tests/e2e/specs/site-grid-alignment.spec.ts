import { test, expect } from '@playwright/test';

const BASE = process.env.HOME_SMOKE_URL?.replace(/\/?$/, '') || 'https://awamotos.com';

const PAGES = [
  { name: 'home', path: '/', bodyClass: 'cms-index-index', gridSel: '.awa-carousel-section .content-item-product' },
  { name: 'plp', path: '/carcacas.html', bodyClass: 'catalog-category-view', gridSel: '.products-grid .product-items' },
  { name: 'pdp', path: '/bagageiro-titan-150-09-13-modelo-preto-macico-3000.html', bodyClass: 'catalog-product-view', gridSel: '.main-detail > .row' },
  { name: 'cart', path: '/checkout/cart/', bodyClass: 'checkout-cart-index', gridSel: '.cart-container' },
] as const;

const VIEWPORTS = [
  { name: 'mobile', width: 390, height: 844 },
  { name: 'desktop', width: 1366, height: 768 },
] as const;

test.describe('Site-wide grid alignment', () => {
  test.describe.configure({ timeout: 120_000 });

  for (const pageDef of PAGES) {
    for (const vp of VIEWPORTS) {
      test(`${pageDef.name} @ ${vp.name}`, async ({ page }) => {
        await page.setViewportSize({ width: vp.width, height: vp.height });
        await page.goto(`${BASE}${pageDef.path}?grid-site=${Date.now()}`, {
          waitUntil: 'domcontentloaded',
          timeout: 90_000,
        });

        await page.waitForTimeout(2500);

        await page.evaluate(() => {
          if (typeof (window as Window & { __awaApplyGatedCSS?: () => void }).__awaApplyGatedCSS === 'function') {
            (window as Window & { __awaApplyGatedCSS?: () => void }).__awaApplyGatedCSS!();
          }
          document.querySelectorAll('link[rel="stylesheet"][media="print"]').forEach((link) => {
            (link as HTMLLinkElement).media = 'all';
          });
        });
        await page.waitForTimeout(2000);

        const metrics = await page.evaluate(
          ({ bodyClass, gridSel, pageName }) => {
            const containerSelectors: Record<string, string> = {
              home: '.content-top-home .top-home-content.awa-home-section > .container',
              plp: '.page-main.container, .columns .column.main',
              pdp: '.page-main.container, .main-detail',
              cart: '.page-main.container, .cart-container',
              checkout: '.page-main.container, .checkout-container, .opc-wrapper',
            };
            const overflow = {
              doc: document.documentElement.scrollWidth - document.documentElement.clientWidth,
              body: document.body.scrollWidth - document.body.clientWidth,
            };
            const alignLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).filter((l) =>
              (l as HTMLLinkElement).href.includes('align-grid-terminal')
            ).length;
            const token = getComputedStyle(document.body).getPropertyValue('--awa-grid-shell-max').trim();
            const containers = Array.from(
              document.querySelectorAll(containerSelectors[pageName] || '.page-main.container')
            )
              .slice(0, 6)
              .map((el) => Math.round((el as HTMLElement).getBoundingClientRect().left));
            const uniqueLefts = [...new Set(containers)];
            const spread = uniqueLefts.length >= 2 ? Math.max(...uniqueLefts) - Math.min(...uniqueLefts) : 0;

            const gridEl = document.querySelector(gridSel);
            let gridDisplay = 'missing';
            let cardHeights: number[] = [];
            if (gridEl) {
              gridDisplay = getComputedStyle(gridEl).display;
              const cards = Array.from(
                document.querySelectorAll('.item-product, .content-item-product, .product-item')
              )
                .filter((el) => {
                  const r = (el as HTMLElement).getBoundingClientRect();
                  return r.width > 40 && r.height > 40;
                })
                .slice(0, 4)
                .map((el) => Math.round((el as HTMLElement).getBoundingClientRect().height));
              cardHeights = cards;
            }

            const thumbs = Array.from(document.querySelectorAll('.product-thumb-link, .product-thumb'))
              .slice(0, 3)
              .map((el) => getComputedStyle(el as Element).aspectRatio);

            return {
              hasBodyClass: document.body.classList.contains(bodyClass),
              overflow,
              alignLinks,
              token,
              containerSpread: spread,
              uniqueLefts,
              gridDisplay,
              cardHeights,
              thumbAspectRatios: thumbs,
            };
          },
          { bodyClass: pageDef.bodyClass, gridSel: pageDef.gridSel, pageName: pageDef.name }
        );

        expect(metrics.hasBodyClass).toBe(true);
        expect(metrics.overflow.doc).toBeLessThanOrEqual(2);
        expect(metrics.overflow.body).toBeLessThanOrEqual(2);
        expect(metrics.alignLinks).toBeGreaterThanOrEqual(1);

        if (metrics.token) {
          expect(metrics.token).toMatch(/1440|1280|min\(100%/);
        }

        if (metrics.uniqueLefts.length >= 2) {
          expect(metrics.containerSpread).toBeLessThanOrEqual(32);
        }

        if (pageDef.name === 'home' || pageDef.name === 'plp') {
          const cardRoot =
            pageDef.name === 'home'
              ? page.locator('.awa-carousel-section').first()
              : page.locator('.products-grid .product-items, .products-grid').first();
          if (await cardRoot.count()) {
            const heights = await cardRoot.evaluate((root) =>
              Array.from(root.querySelectorAll('.content-item-product, .item-product'))
                .filter((el) => {
                  const r = el.getBoundingClientRect();
                  return r.width > 40 && r.height > 40 && r.top >= 0 && r.top < window.innerHeight;
                })
                .slice(0, 4)
                .map((el) => Math.round(el.getBoundingClientRect().height))
            );
            if (heights.length >= 2) {
              expect(Math.max(...heights) - Math.min(...heights)).toBeLessThanOrEqual(14);
            }
          }
          for (const ratio of metrics.thumbAspectRatios) {
            if (ratio && ratio !== 'auto') {
              expect(ratio).toMatch(/^1\s*\/\s*1$/);
            }
          }
        }

        if (pageDef.name === 'pdp' && vp.name === 'desktop') {
          expect(['grid', 'flex']).toContain(metrics.gridDisplay);
        }

        if (pageDef.name === 'cart' && vp.name === 'desktop' && metrics.gridDisplay !== 'missing') {
          expect(['grid', 'flex', 'block']).toContain(metrics.gridDisplay);
        }
      });
    }
  }
});
