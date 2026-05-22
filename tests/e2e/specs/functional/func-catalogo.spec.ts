import { test, expect } from '@playwright/test';
import {
  navigateTo,
  checkOverflow,
  collectConsoleErrors,
  filterCriticalJsErrors,
} from '../../helpers/deep-audit.helpers';

const CATALOGO_PDF = 'https://awamotos.com/catalogo/';
const CATALOGO_REVISTA = 'https://awamotos.com/catalogo/revista/';

test.describe('Catálogo digital — PDF e revista', () => {
  test('01 — /catalogo/ responde HTTP 200', async ({ page }) => {
    const res = await page.request.get(CATALOGO_PDF);
    expect(res.status()).toBe(200);
  });

  test('02 — visualizador PDF com H1 único e menu lateral', async ({ page }) => {
    const ok = await navigateTo(page, CATALOGO_PDF);
    if (!ok) test.skip();

    await expect(page.locator('.awa-catalog-nav')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('.awa-catalog-nav__link.is-active')).toContainText(/PDF/i);
    await expect(page.locator('.awa-catalogo-page__frame')).toBeVisible();
    await expect(page.locator('.page-title-wrapper, .page-title')).toHaveCount(0);
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('#awa-catalogo-title')).toBeVisible();
  });

  test('03 — CTAs do hero visíveis (desktop)', async ({ page }) => {
    const ok = await navigateTo(page, CATALOGO_PDF);
    if (!ok) test.skip();

    await expect(page.locator('.awa-catalogo-page__download')).toBeVisible();
    await expect(page.locator('.awa-catalogo-page__revista')).toBeVisible();
    await expect(page.locator('.awa-catalogo-page__b2b')).toBeVisible();
  });

  test('04 — mobile: nav pills, institucional colapsável e sem overflow', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    const ok = await navigateTo(page, CATALOGO_PDF);
    if (!ok) test.skip();

    const modeList = page.locator('.awa-catalog-nav__section:first-child .awa-catalog-nav__list');
    await expect(modeList).toBeVisible();

    const institutional = page.locator('details.awa-catalog-nav__section--institutional');
    await expect(institutional).toBeVisible();
    await expect(institutional).not.toHaveAttribute('open', '');

    const ctas = page.locator('.awa-catalogo-page__cta');
    await expect(ctas).toHaveCount(3);

    const { hasOverflow } = await checkOverflow(page);
    expect(hasOverflow).toBe(false);
  });

  test('05 — /catalogo/revista/ carrega flipbook com 32 páginas', async ({ page }) => {
    const ok = await navigateTo(page, CATALOGO_REVISTA);
    if (!ok) test.skip();

    await expect(page.locator('.awa-catalog-revista-header__title')).toBeVisible();
    await expect(page.locator('#awa-catalog-counter')).toContainText('/ 32', { timeout: 60000 });
    await expect(page.locator('#awa-catalog-flipbook-fallback')).toHaveClass(/is-hidden/);
    await expect(page.locator('#awa-catalog-flipbook-stage')).not.toHaveClass(/is-hidden/);
  });

  test('06 — flipbook avança página com botão next', async ({ page }) => {
    const ok = await navigateTo(page, CATALOGO_REVISTA);
    if (!ok) test.skip();

    const counter = page.locator('#awa-catalog-counter');
    await expect(counter).toContainText('1 / 32', { timeout: 60000 });
    await page.locator('#awa-catalog-next').click();
    await expect(counter).toContainText('2 / 32', { timeout: 10000 });
  });

  test('07 — sem erros JS críticos nas páginas do catálogo', async ({ page }) => {
    const errors = collectConsoleErrors(page);

    for (const url of [CATALOGO_PDF, CATALOGO_REVISTA]) {
      const ok = await navigateTo(page, url);
      if (!ok) continue;
      await page.waitForTimeout(4000);
    }

    const critical = filterCriticalJsErrors(errors);
    expect(critical).toHaveLength(0);
  });
});
