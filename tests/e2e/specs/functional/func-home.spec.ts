import { test, expect } from '@playwright/test';
import { navigateTo, dismissCookie, COMMON } from '../../helpers/visual-audit.helpers';

const HOME = 'https://awamotos.com';

test.describe('Home — carregamento e estrutura', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('01 — título contém AWA', async ({ page }) => {
    await expect(page).toHaveTitle(/AWA/i);
  });

  test('02 — header visível', async ({ page }) => {
    await expect(page.locator(COMMON.header).first()).toBeVisible({ timeout: 10_000 });
  });

  test('03 — logo carregado', async ({ page }) => {
    const logo = page.locator(COMMON.logo).first();
    await expect(logo).toBeVisible({ timeout: 10_000 });
    const src = await logo.getAttribute('src');
    expect(src).toBeTruthy();
  });

  test('04 — busca visível', async ({ page }) => {
    await expect(page.locator(COMMON.search).first()).toBeVisible({ timeout: 10_000 });
  });

  test('05 — minicart presente', async ({ page }) => {
    const mc = page.locator('.mini-cart-wrapper, .awa-header-minicart, .awa-header-cart, .action.showcart, [data-awa-header-cart]').first();
    const mcVisible = await mc.isVisible({ timeout: 10_000 }).catch(() => false);
    if (!mcVisible) console.warn('[P1] Minicart não visível — KO pode não ter inicializado');
    expect(mcVisible, '[P1] Minicart ausente').toBe(true);
  });

  test('06 — grid de produtos existe', async ({ page }) => {
    const grid = page.locator('.product-items, .products-grid, .product-item').first();
    await expect(grid).toBeVisible({ timeout: 15_000 });
  });

  test('07 — rodapé presente', async ({ page }) => {
    await expect(page.locator(COMMON.footer).first()).toBeVisible({ timeout: 10_000 });
  });

  test('08 — sem erros JS críticos (P0)', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.waitForTimeout(2_000);
    const critical = errors.filter(e => /require is not defined|Cannot read prop|TypeError/i.test(e));
    if (critical.length > 0) console.error('[P0] Erros JS:', critical.join(' | '));
    expect(critical).toHaveLength(0);
  });

  test('09 — sem overflow horizontal (P2)', async ({ page }) => {
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 4
    );
    if (overflow) console.warn('[P2] Overflow horizontal na home');
    expect(overflow, 'Overflow horizontal').toBe(false);
  });
});
