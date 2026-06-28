import { test, expect } from '@playwright/test';

const PDP_URL =
  process.env.PDP_IMPECCABLE_URL ||
  'https://awamotos.com/bauletos/bauleto-awa-modelo-proos-41-litros-azul-410-az.html';

async function unlockDeferredCss(page: import('@playwright/test').Page): Promise<void> {
  await page.evaluate(() => {
    if (typeof (window as Window & { __awaApplyGatedCSS?: () => void }).__awaApplyGatedCSS === 'function') {
      (window as Window & { __awaApplyGatedCSS?: () => void }).__awaApplyGatedCSS!();
    }
    document.querySelectorAll('link[rel="stylesheet"][media="print"]').forEach((link) => {
      (link as HTMLLinkElement).media = 'all';
    });
  });
  await page.waitForTimeout(800);
}

test.describe('PDP impeccable layout', () => {
  test.describe.configure({ timeout: 90_000 });

  test.beforeEach(async ({ page }) => {
    await page.goto(`${PDP_URL}?pdp-impeccable=${Date.now()}`, {
      waitUntil: 'domcontentloaded',
      timeout: 90_000,
    });
    await page.waitForSelector('.product-info-main', { timeout: 30_000 });
    await unlockDeferredCss(page);
  });

  for (const viewport of [
    { name: 'mobile', width: 390, height: 844 },
    { name: 'desktop', width: 1366, height: 900 },
  ]) {
    test(`${viewport.name} — attr-product sem AI tell`, async ({ page }) => {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });

      const attr = await page.evaluate(() => {
        const el = document.querySelector('.product-info-main .attr-product');
        if (!el) {
          return null;
        }
        const cs = getComputedStyle(el);
        return {
          boxShadow: cs.boxShadow,
          borderWidth: parseFloat(cs.borderTopWidth) || 0,
        };
      });

      expect(attr).not.toBeNull();
      expect(attr!.boxShadow === 'none' || attr!.boxShadow === '').toBe(true);
      expect(attr!.borderWidth).toBeLessThanOrEqual(1);
    });

    test(`${viewport.name} — Source Sans 3 no page-wrapper`, async ({ page }) => {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });

      const fontFamily = await page.evaluate(() => {
        const wrapper = document.querySelector('.page-wrapper');
        return wrapper ? getComputedStyle(wrapper).fontFamily : '';
      });

      expect(fontFamily.toLowerCase()).toMatch(/source sans/);
    });

    test(`${viewport.name} — sr-only não expande botão de busca`, async ({ page }) => {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });

      const metrics = await page.evaluate(() => {
        const btn = document.querySelector('button.action.search');
        const sr = btn?.querySelector('.awa-sr-only');
        if (!btn || !sr) {
          return null;
        }
        const btnRect = btn.getBoundingClientRect();
        const srRect = sr.getBoundingClientRect();
        const srCs = getComputedStyle(sr);
        return {
          btnWidth: btnRect.width,
          srWidth: srRect.width,
          srDisplay: srCs.display,
        };
      });

      expect(metrics).not.toBeNull();
      expect(metrics!.srDisplay).toBe('none');
      expect(metrics!.srWidth).toBeLessThanOrEqual(metrics!.btnWidth + 1);
    });

    test(`${viewport.name} — promo bar ≥ 95% do header-content`, async ({ page }) => {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });

      const metrics = await page.evaluate(() => {
        const promo = document.querySelector('.top-header, .awa-b2b-promo-bar')?.getBoundingClientRect();
        const headerContent = document.querySelector('.header-content')?.getBoundingClientRect();
        return {
          promoWidth: promo?.width ?? 0,
          headerContentWidth: headerContent?.width ?? 0,
        };
      });

      if (metrics.headerContentWidth > 0) {
        expect(metrics.promoWidth).toBeGreaterThanOrEqual(metrics.headerContentWidth * 0.95);
      }
    });
  }

  test('desktop — carrossel relacionados padding-block ≥ 8px', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 900 });
    await page.waitForSelector('.awa-carousel-card-slot', { timeout: 30_000 }).catch(() => {});

    const paddingBlock = await page.evaluate(() => {
      const slot = document.querySelector('.item-product.awa-carousel-card-slot');
      if (!slot) {
        return null;
      }
      const cs = getComputedStyle(slot);
      const top = parseFloat(cs.paddingTop) || 0;
      const bottom = parseFloat(cs.paddingBottom) || 0;
      return Math.max(top, bottom);
    });

    if (paddingBlock !== null) {
      expect(paddingBlock).toBeGreaterThanOrEqual(8);
    }
  });

  test('align-grid carrega versão pdp-impeccable', async ({ page }) => {
    const hrefs = await page.evaluate(() =>
      [...document.querySelectorAll('link[rel="stylesheet"]')]
        .map((l) => (l as HTMLLinkElement).href)
        .filter((h) => /align-grid-terminal/.test(h)),
    );

    expect(hrefs.some((h) => h.includes('20260621-footer-axis'))).toBe(true);
  });
});
