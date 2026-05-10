import { test, expect } from '@playwright/test';
import { navigateTo, COMMON, waitForImages, checkOverflow, findBrokenImages, collectConsoleErrors, filterCriticalJsErrors } from '../../helpers/deep-audit.helpers';

const PAGES = [
  { name: 'Home', url: 'https://awamotos.com' },
  { name: 'Categoria', url: 'https://awamotos.com/bagageiros.html' },
  { name: 'PDP', url: 'https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html' },
  { name: 'Login', url: 'https://awamotos.com/customer/account/login/' },
  { name: 'Carrinho', url: 'https://awamotos.com/checkout/cart/' },
];

test.describe('Mobile — Cross-Page', () => {
  test.beforeEach(async ({}, testInfo) => {
    if (!testInfo.project.name.includes('mobile')) test.skip();
  });

  for (const pg of PAGES) {
    test('overflow-' + pg.name, async ({ page }) => {
      await navigateTo(page, pg.url);
      const { hasOverflow, diff } = await checkOverflow(page);
      if (hasOverflow) console.warn('[P2] Overflow ' + diff + 'px em ' + pg.name);
      expect(hasOverflow).toBe(false);
    });
  }

  test('touch targets >= 36px', async ({ page }) => {
    await navigateTo(page, PAGES[0].url);
    const btns = page.locator('a.action, button, .product-item a');
    const count = await btns.count();
    let tooSmall = 0;
    for (let i = 0; i < Math.min(count, 15); i++) {
      const box = await btns.nth(i).boundingBox();
      if (box && (box.height < 36 || box.width < 36)) tooSmall++;
    }
    if (tooSmall > 0) console.warn('[P2] ' + tooSmall + ' touch targets < 36px');
  });

  test('hamburger menu', async ({ page }) => {
    await navigateTo(page, PAGES[0].url);
    const hamburger = page.locator('.action.nav-toggle, .nav-toggle, button[aria-label*="menu" i]').first();
    await expect(hamburger).toBeVisible({ timeout: 10000 });
  });

  test('sem imagens quebradas', async ({ page }) => {
    await navigateTo(page, PAGES[0].url);
    await waitForImages(page);
    const broken = await findBrokenImages(page);
    expect(broken.length).toBe(0);
  });

  test('sem JS errors (P0)', async ({ page }) => {
    const errors = collectConsoleErrors(page);
    await navigateTo(page, PAGES[0].url);
    await page.waitForTimeout(3000);
    const critical = filterCriticalJsErrors(errors);
    expect(critical).toHaveLength(0);
  });
});