import { test, expect } from '@playwright/test';
import { navigateTo, loginB2B } from '../../helpers/visual-audit.helpers';

const DASHBOARD_URL = 'https://awamotos.com/b2b/account/dashboard/';
const LOGIN_URL     = 'https://awamotos.com/b2b/account/login/';

test.describe('B2B Dashboard — área logada', () => {

  test('01 — [P0] guest redireciona para login ao acessar dashboard', async ({ page }) => {
    await page.goto(DASHBOARD_URL, { waitUntil: 'domcontentloaded', timeout: 20_000 });
    await page.waitForURL(/login/i, { timeout: 15_000 });
    await expect(page).not.toHaveURL(/b2b\/account\/dashboard\/?$/);
  });

  test('02 — [P0] dashboard carrega para usuário logado', async ({ page }) => {
    if (!process.env.TEST_USER) { test.skip(); return; }
    const loggedIn = await loginB2B(page);
    if (!loggedIn) { test.skip(); return; }
    const ok = await navigateTo(page, DASHBOARD_URL);
    if (!ok) test.skip();
    // Deve exibir área principal do dashboard (h1.page-title do layout Magento fica oculto no B2B)
    const title = page.locator(
      '.b2b-dashboard, .b2b-dashboard-header h1, main[aria-label*="Painel B2B"]'
    ).first();
    await expect(title).toBeVisible({ timeout: 12_000 });
  });

  test('03 — [P0] seções async removem skeleton em ≤15s', async ({ page }) => {
    if (!process.env.TEST_USER) { test.skip(); return; }
    const loggedIn = await loginB2B(page);
    if (!loggedIn) { test.skip(); return; }
    const ok = await navigateTo(page, DASHBOARD_URL);
    if (!ok) test.skip();
    // Espera skeleton desaparecer
    const skeleton = page.locator('.b2b-loading, .b2b-skeleton, .awa-loading').first();
    const hasSkeleton = await skeleton.isVisible({ timeout: 2_000 }).catch(() => false);
    if (hasSkeleton) {
      await expect(skeleton).not.toBeVisible({ timeout: 15_000 });
    }
    // Dashboard deve ter conteúdo visível após carregamento
    const content = page.locator(
      '.b2b-dashboard, .b2b-section, .actions-grid, .b2b-summary-cards'
    ).first();
    await expect(content).toBeVisible({ timeout: 12_000 });
  });

  test('04 — [P1] atalho "Meus Pedidos" navega para histórico', async ({ page }) => {
    if (!process.env.TEST_USER) { test.skip(); return; }
    const loggedIn = await loginB2B(page);
    if (!loggedIn) { test.skip(); return; }
    const ok = await navigateTo(page, DASHBOARD_URL);
    if (!ok) test.skip();

    const ordersLink = page.locator('.actions-grid a.action-item').filter({ hasText: 'Meus Pedidos' }).first();
    await ordersLink.scrollIntoViewIfNeeded();
    await expect(ordersLink).toBeVisible({ timeout: 12_000 });
    const href = await ordersLink.getAttribute('href');
    expect(href).toMatch(/order|pedido|reorder|sales/i);
  });

  test('05 — [P1] atalho "Meus Dados" navega para edição', async ({ page }) => {
    if (!process.env.TEST_USER) { test.skip(); return; }
    const loggedIn = await loginB2B(page);
    if (!loggedIn) { test.skip(); return; }
    const ok = await navigateTo(page, DASHBOARD_URL);
    if (!ok) test.skip();

    const accountLink = page.locator('.actions-grid a.action-item').filter({ hasText: 'Meus Dados' }).first();
    await accountLink.scrollIntoViewIfNeeded();
    await expect(accountLink).toBeVisible({ timeout: 12_000 });
    const href = await accountLink.getAttribute('href');
    expect(href).toMatch(/customer\/account\/edit|account\/edit/i);
  });

  test('06 — [P2] breadcrumb ou título identifica portal B2B', async ({ page }) => {
    if (!process.env.TEST_USER) { test.skip(); return; }
    const loggedIn = await loginB2B(page);
    if (!loggedIn) { test.skip(); return; }
    const ok = await navigateTo(page, DASHBOARD_URL);
    if (!ok) test.skip();

    const breadOrTitle = page.locator(
      '.b2b-dashboard-header h1, main[aria-label*="Painel B2B"], .b2b-dashboard'
    ).first();
    await breadOrTitle.scrollIntoViewIfNeeded();
    await expect(breadOrTitle).toBeVisible({ timeout: 12_000 });
  });

});
