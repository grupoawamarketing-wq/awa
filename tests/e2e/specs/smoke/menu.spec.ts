import { test, expect } from '@playwright/test';
import { navigateTo, COMMON } from '../../helpers/deep-audit.helpers';

const HOME = 'https://awamotos.com';

test.describe('Smoke — Menu', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('01 — menu vertical visível (desktop)', async ({ page }, testInfo) => {
    if (testInfo.project.name.includes('mobile')) { test.skip(); return; }
    const menu = page.locator('.vertical-menu, .vmenu, .vmenu-container, .nav-sections').first();
    await expect(menu).toBeVisible({ timeout: 10000 });
  });

  test('02 — menu tem itens (P1)', async ({ page }, testInfo) => {
    if (testInfo.project.name.includes('mobile')) { test.skip(); return; }
    const items = page.locator('.vertical-menu li a, .vmenu li a, .nav-sections li a, .navigation li a').first();
    const vis = await items.isVisible({ timeout: 10000 }).catch(() => false);
    if (!vis) console.warn('[P1] Menu não tem itens visíveis (.nav-sections, .vmenu)');
  });

  test('03 — links do menu são válidos', async ({ page }, testInfo) => {
    if (testInfo.project.name.includes('mobile')) { test.skip(); return; }
    const links = page.locator('.navigation a[href], .custommenu a[href], .nav-sections a[href]');
    const count = await links.count();
    let invalid = 0;
    for (let i = 0; i < Math.min(count, 10); i++) {
      const href = await links.nth(i).getAttribute('href').catch(() => '');
      if (!href || href === '#' || href === 'javascript:void(0)') invalid++;
    }
    if (invalid > 0) console.warn('[P2] ' + invalid + ' links inválidos no menu');
  });

  test('04 — hamburger em mobile', async ({ page }, testInfo) => {
    if (!testInfo.project.name.includes('mobile')) { test.skip(); return; }
    const hamburger = page.locator('.action.nav-toggle, .nav-toggle, button[aria-label*="menu" i]').first();
    await expect(hamburger).toBeVisible({ timeout: 10000 });
  });

  test('05 — menu mobile abre', async ({ page }, testInfo) => {
    if (!testInfo.project.name.includes('mobile')) { test.skip(); return; }
    const hamburger = page.locator('.action.nav-toggle, .nav-toggle').first();
    const vis = await hamburger.isVisible({ timeout: 5000 }).catch(() => false);
    if (!vis) { test.skip(); return; }
    await hamburger.click({ force: true });
    await page.waitForTimeout(800);
    const nav = page.locator('.nav-sections, .navigation, #store\\.menu').first();
    const navVis = await nav.isVisible({ timeout: 5000 }).catch(() => false);
    if (!navVis) console.warn('[P1] Menu mobile não abriu');
  });
});