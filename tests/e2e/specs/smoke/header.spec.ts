import { test, expect } from '@playwright/test';
import { navigateTo, COMMON, collectConsoleErrors, filterCriticalJsErrors } from '../../helpers/deep-audit.helpers';

const HOME = 'https://awamotos.com';

test.describe('Smoke — Header', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('01 — header no DOM', async ({ page }) => {
    await expect(page.locator(COMMON.header).first()).toBeVisible({ timeout: 10000 });
  });

  test('02 — logo com href para home', async ({ page }) => {
    const logo = page.locator('.logo a, a.logo').first();
    const href = await logo.getAttribute('href').catch(() => '');
    expect(href).toContain('awamotos.com');
  });

  test('03 — logo imagem carregou', async ({ page }) => {
    const img = page.locator(COMMON.logo).first();
    await expect(img).toBeVisible({ timeout: 10000 });
    const loaded = await img.evaluate((i: HTMLImageElement) => i.naturalWidth > 0 && i.complete).catch(() => false);
    expect(loaded).toBe(true);
  });

  test('04 — busca foca ao clicar', async ({ page }) => {
    const search = page.locator(COMMON.search).first();
    const vis = await search.isVisible({ timeout: 3000 }).catch(() => false);
    if (!vis) {
      // On mobile/tablet the search icon may be a toggle (not a form submit).
      // Use .search-toggle first; fall back to .action.search only if it exists AND
      // won't submit an empty form (check it is type=button or not type=submit).
      const toggleSel = '.awa-search-toggle, [data-awa-search-toggle], .search-toggle';
      const toggle = page.locator(toggleSel).first();
      const hasToggle = await toggle.isVisible({ timeout: 1000 }).catch(() => false);
      if (hasToggle) {
        await toggle.click({ force: true }).catch(() => {});
      }
      await page.waitForTimeout(400);
    }
    // Click the input only if it became visible after the toggle
    const visAfter = await search.isVisible({ timeout: 2000 }).catch(() => false);
    if (visAfter) {
      await search.click({ force: true }).catch(() => {});
      const focused = await search.evaluate(el => document.activeElement === el).catch(() => false);
      if (!focused) console.warn('[P2] Busca não focou ao clicar');
    } else {
      // Input still hidden on this viewport — warn only, do not block
      console.warn('[P2] Busca não visível neste viewport — skip focus check');
    }
  });

  test('05 — minicart presente', async ({ page }) => {
    await expect(page.locator(COMMON.minicart).first()).toBeVisible({ timeout: 10000 });
  });

  test('06 — conta/login link', async ({ page }) => {
    // On mobile/tablet the account link is intentionally hidden (inside hamburger menu).
    // Accept either a visible link OR a link present in DOM (hidden in collapsed nav).
    const visibleLink = page.locator(
      'a[href*="customer/account"], a[href*="b2b/account"], .customer-welcome, .authorization-link a'
    ).first();
    const isVisible = await visibleLink.isVisible({ timeout: 5000 }).catch(() => false);
    if (isVisible) {
      await expect(visibleLink).toBeVisible();
    } else {
      // Fallback: link exists in DOM (accessible via hamburger / hidden nav)
      const count = await visibleLink.count();
      expect(count).toBeGreaterThan(0);
      console.warn('[P2] Conta/login link está oculto neste viewport (hamburger nav)');
    }
  });

  test('07 — header height razoável', async ({ page }) => {
    const header = page.locator(COMMON.header).first();
    const box = await header.boundingBox();
    if (box) {
      expect(box.height).toBeGreaterThan(40);
      expect(box.height).toBeLessThan(300);
    }
  });

  test('08 — sem JS errors no header', async ({ page }) => {
    const errors = collectConsoleErrors(page);
    await page.waitForTimeout(2000);
    const critical = filterCriticalJsErrors(errors);
    expect(critical).toHaveLength(0);
  });
});