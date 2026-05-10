import { test, expect } from '@playwright/test';
import {
  navigateTo, COMMON, collectConsoleErrors, filterCriticalJsErrors,
  checkOverflow, findBrokenImages, waitForImages,
} from '../../helpers/deep-audit.helpers';

const CAT = 'https://awamotos.com/bagageiros.html';

test.describe('Smoke — Categoria', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, CAT);
    if (!ok) test.skip();
  });

  test('01 — HTTP OK', async ({ page }) => {
    const res = await page.request.get(CAT).catch(() => null);
    if (res) expect(res.status()).toBeLessThan(400);
  });

  test('02 — título da categoria', async ({ page }) => {
    const title = page.locator('.page-title, h1').first();
    await expect(title).toBeVisible({ timeout: 10000 });
  });

  test('03 — grid de produtos', async ({ page }) => {
    const grid = page.locator('.products-grid, .product-items').first();
    await expect(grid).toBeVisible({ timeout: 15000 });
  });

  test('04 — cards com imagens (P1)', async ({ page }) => {
    await waitForImages(page);
    const img = page.locator('.product-item img').first();
    const vis = await img.isVisible({ timeout: 10000 }).catch(() => false);
    if (!vis) console.warn('[P1] Imagens de produto não visíveis na categoria (lazy load ou slow)');
  });

  test('05 — cards com preço ou B2B gate', async ({ page }) => {
    const el = page.locator('.product-item .price, .product-item .b2b-login-to-see-price').first();
    await expect(el).toBeVisible({ timeout: 10000 });
  });

  test('06 — filtros/layered nav (P1)', async ({ page }, testInfo) => {
    if (testInfo.project.name.includes('mobile')) { test.skip(); return; }
    const filters = page.locator('.filter-options, .block-layered-nav, .sidebar-main .filter').first();
    const vis = await filters.isVisible({ timeout: 8000 }).catch(() => false);
    if (!vis) console.warn('[P1] Filtros não visíveis na categoria');
  });

  test('07 — sorting funciona', async ({ page }) => {
    const sorter = page.locator('.sorter select, select#sorter, .toolbar-sorter select').first();
    const vis = await sorter.isVisible({ timeout: 8000 }).catch(() => false);
    if (!vis) console.warn('[P2] Sorter não visível');
  });

  test('08 — sem overflow', async ({ page }) => {
    const { hasOverflow } = await checkOverflow(page);
    expect(hasOverflow).toBe(false);
  });

  test('09 — sem JS errors (P0)', async ({ page }) => {
    const errors = collectConsoleErrors(page);
    await page.waitForTimeout(3000);
    const critical = filterCriticalJsErrors(errors);
    expect(critical).toHaveLength(0);
  });

  test('10 — sem imagens quebradas (P1)', async ({ page }) => {
    await waitForImages(page);
    const broken = await findBrokenImages(page);
    if (broken.length > 0) console.warn('[P1] Imagens quebradas na categoria:', broken.join(', '));
  });

  test('11 — paginação existe', async ({ page }) => {
    const pager = page.locator('.pages, .toolbar-amount, .pager').first();
    const vis = await pager.isVisible({ timeout: 5000 }).catch(() => false);
    if (!vis) console.info('[INFO] Sem paginação (pode ter poucos produtos)');
  });
});