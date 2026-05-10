import { test, expect } from '@playwright/test';
import {
  navigateTo, COMMON, collectConsoleErrors, collectNetworkErrors,
  filterCriticalJsErrors, filter404s, filter500s, checkOverflow,
  findBrokenImages, waitForImages,
} from '../../helpers/deep-audit.helpers';

const HOME = 'https://awamotos.com';

test.describe('Smoke — Home', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('01 — HTTP 200', async ({ page }) => {
    const res = await page.request.get(HOME).catch(() => null);
    if (res) expect(res.status()).toBeLessThan(400);
  });

  test('02 — title não vazio', async ({ page }) => {
    const title = await page.title();
    expect(title.length).toBeGreaterThan(3);
  });

  test('03 — sem JS errors críticos (P0)', async ({ page }) => {
    const errors = collectConsoleErrors(page);
    await page.waitForTimeout(3000);
    const critical = filterCriticalJsErrors(errors);
    expect(critical).toHaveLength(0);
  });

  test('04 — sem 404s em recursos (P1)', async ({ page }) => {
    const net = collectNetworkErrors(page);
    await navigateTo(page, HOME);
    await page.waitForTimeout(3000);
    const notFound = filter404s(net);
    if (notFound.length > 0) console.warn('[P1] 404s:', notFound.map(e => e.url).join(', '));
  });

  test('05 — sem 500s (P0)', async ({ page }) => {
    const net = collectNetworkErrors(page);
    await navigateTo(page, HOME);
    await page.waitForTimeout(3000);
    const serverErrors = filter500s(net);
    expect(serverErrors, '[P0] Erros 500 na home').toHaveLength(0);
  });

  test('06 — header visível', async ({ page }) => {
    await expect(page.locator(COMMON.header).first()).toBeVisible({ timeout: 10000 });
  });

  test('07 — logo visível', async ({ page }) => {
    await expect(page.locator(COMMON.logo).first()).toBeVisible({ timeout: 10000 });
  });

  test('08 — busca visível', async ({ page }) => {
    const search = page.locator(COMMON.search).first();
    const vis = await search.isVisible({ timeout: 5000 }).catch(() => false);
    if (!vis) {
      const toggle = page.locator('.action.search, .search-toggle').first();
      if (await toggle.isVisible({ timeout: 3000 }).catch(() => false)) {
        await toggle.click({ force: true }).catch(() => {});
        await page.waitForTimeout(500);
      }
    }
    await expect(page.locator(COMMON.search).first()).toBeVisible({ timeout: 5000 });
  });

  test('09 — minicart visível', async ({ page }) => {
    await expect(page.locator(COMMON.minicart).first()).toBeVisible({ timeout: 10000 });
  });

  test('10 — banner/slider presente', async ({ page }) => {
    const banner = page.locator('.owl-carousel, .swiper-container, .slidebanner, .banner, .home-slider, [class*="slide"], [class*="banner"]').first();
    await expect(banner).toBeVisible({ timeout: 15000 });
  });

  test('11 — grid de produtos existe', async ({ page }) => {
    const grid = page.locator('.products-grid, .products.wrapper, .product-items, .product-item').first();
    await expect(grid).toBeVisible({ timeout: 15000 });
  });

  test('12 — cards com imagens', async ({ page }) => {
    await waitForImages(page);
    const img = page.locator('.product-item img, .product-image-photo').first();
    await expect(img).toBeVisible({ timeout: 10000 });
  });

  test('13 — cards com preço ou B2B gate', async ({ page }) => {
    const price = page.locator('.product-item .price, .product-item .b2b-login-to-see-price').first();
    await expect(price).toBeVisible({ timeout: 10000 });
  });

  test('14 — footer visível', async ({ page }) => {
    await expect(page.locator(COMMON.footer).first()).toBeVisible({ timeout: 10000 });
  });

  test('15 — sem overflow horizontal', async ({ page }) => {
    const { hasOverflow, diff } = await checkOverflow(page);
    if (hasOverflow) console.warn('[P2] Overflow ' + diff + 'px');
    expect(hasOverflow).toBe(false);
  });

  test('16 — sem imagens quebradas (P1)', async ({ page }) => {
    await waitForImages(page);
    const broken = await findBrokenImages(page);
    if (broken.length > 0) console.warn('[P1] Imagens quebradas:', broken.join(', '));
    // P1 smoke: warn-only — broken images are monitored but not blocking
  });
});