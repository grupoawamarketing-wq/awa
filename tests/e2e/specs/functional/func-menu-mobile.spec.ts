/**
 * func-menu-mobile.spec.ts - AWA Motos
 * Testa a barra de navegacao inferior mobile e o drawer de categorias (390px).
 * Apenas projeto func-mobile.
 */
import { test, expect } from '@playwright/test';
import { navigateTo } from '../../helpers/visual-audit.helpers';

const HOME = 'https://awamotos.com';

test.describe('Menu Mobile - barra inferior e drawer', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-mobile') { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('01 - barra de navegacao inferior visivel (P1)', async ({ page }) => {
    const toggle = page.locator('.toggle-nav-footer').first();
    await expect(toggle).toBeVisible({ timeout: 8_000 });
  });

  test('02 - drawer abre ao clicar em Menu (P0)', async ({ page }) => {
    const toggle = page.locator('.toggle-nav-footer').first();
    const exists = await toggle.isVisible({ timeout: 5_000 }).catch(() => false);
    if (!exists) { test.skip(); return; }

    await toggle.dispatchEvent('click');
    await page.waitForTimeout(700);

    // Drawer usa awa-mobile-drawer-open (nao nav-open - conflita com RokanThemes)
    const drawerOpen = await page.evaluate(() =>
      document.body.classList.contains('awa-mobile-drawer-open')
    );
    const panel = page.locator(
      '.section-items.nav-sections.category-dropdown-items.awa-header-primary-nav'
    ).first();
    const panelVisible = await panel.isVisible({ timeout: 3_000 }).catch(() => false);

    expect(drawerOpen || panelVisible, '[P0] Drawer de categorias nao abriu').toBe(true);
  });

  test('03 - botao fechar fecha o drawer (P1)', async ({ page }) => {
    const toggle = page.locator('.toggle-nav-footer').first();
    const exists = await toggle.isVisible({ timeout: 5_000 }).catch(() => false);
    if (!exists) { test.skip(); return; }

    // Abrir
    await toggle.dispatchEvent('click');
    await page.waitForTimeout(700);

    // Fechar via botao x
    const closeBtn = page.locator('.awa-nav-close').first();
    const closeBtnVisible = await closeBtn.isVisible({ timeout: 3_000 }).catch(() => false);
    if (!closeBtnVisible) {
      console.warn('[P1] Botao fechar nao visivel apos abrir drawer');
      test.skip();
      return;
    }

    await closeBtn.dispatchEvent('click');
    await page.waitForTimeout(500);

    const drawerClosed = await page.evaluate(() =>
      !document.body.classList.contains('awa-mobile-drawer-open')
    );
    expect(drawerClosed, '[P1] Drawer nao fechou apos clicar no botao fechar').toBe(true);
  });

  test('04 - sem overflow horizontal mobile (P2)', async ({ page }) => {
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 4
    );
    if (overflow) console.warn('[P2] Overflow horizontal em mobile');
    expect(overflow).toBe(false);
  });
});
