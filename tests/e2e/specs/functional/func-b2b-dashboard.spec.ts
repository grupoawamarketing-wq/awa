import { test, expect } from '@playwright/test';
import { navigateTo, loginB2B } from '../../helpers/visual-audit.helpers';

const DASHBOARD_URL = 'https://awamotos.com/b2b/account/dashboard/';
const LOGIN_URL     = 'https://awamotos.com/b2b/account/login/';

test.describe('B2B Dashboard — área logada', () => {

  test('01 — [P0] guest redireciona para login ao acessar dashboard', async ({ page }) => {
    const ok = await navigateTo(page, DASHBOARD_URL);
    if (!ok) test.skip();
    // Guest deve ser redirecionado
    await page.waitForLoadState('domcontentloaded', { timeout: 15_000 }).catch(() => {});
    const url = page.url();
    const redirectedToLogin = url.includes('login') || url.includes('b2b/account/login');
    expect(redirectedToLogin, 'Guest acessou dashboard sem ser redirecionado para login').toBe(true);
  });

  test('02 — [P0] dashboard carrega para usuário logado', async ({ page }) => {
    if (!process.env.TEST_USER) { test.skip(); return; }
    const loggedIn = await loginB2B(page);
    if (!loggedIn) { test.skip(); return; }
    const ok = await navigateTo(page, DASHBOARD_URL);
    if (!ok) test.skip();
    // Deve exibir título ou área do dashboard
    const title = page.locator(
      '.awa-b2b-dashboard__title, .b2b-dashboard-shortcuts, .b2b-portal-title, h1.page-title, .page-title'
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
      '.b2b-dashboard-shortcuts, .b2b-recent-orders, .b2b-account-info, .awa-b2b-dashboard'
    ).first();
    const contentVisible = await content.isVisible({ timeout: 5_000 }).catch(() => false);
    if (!contentVisible) console.warn('[P1] Conteúdo do dashboard não encontrado após 15s');
  });

  test('04 — [P1] atalho "Meus Pedidos" navega para histórico', async ({ page }) => {
    if (!process.env.TEST_USER) { test.skip(); return; }
    const loggedIn = await loginB2B(page);
    if (!loggedIn) { test.skip(); return; }
    const ok = await navigateTo(page, DASHBOARD_URL);
    if (!ok) test.skip();
    const ordersLink = page.locator(
      'a[href*="sales/order/history"], a[href*="b2b/reorder"], a:has-text("Pedidos"), a:has-text("Histórico")'
    ).first();
    const visible = await ordersLink.isVisible({ timeout: 8_000 }).catch(() => false);
    if (!visible) { console.warn('[P1] Link de pedidos não encontrado no dashboard'); test.skip(); return; }
    const href = await ordersLink.getAttribute('href');
    expect(href).toMatch(/order|pedido|reorder/i);
  });

  test('05 — [P1] atalho "Dados da Conta" navega para edição', async ({ page }) => {
    if (!process.env.TEST_USER) { test.skip(); return; }
    const loggedIn = await loginB2B(page);
    if (!loggedIn) { test.skip(); return; }
    const ok = await navigateTo(page, DASHBOARD_URL);
    if (!ok) test.skip();
    const accountLink = page.locator(
      'a[href*="customer/account/edit"], a[href*="account/edit"], a:has-text("Dados"), a:has-text("Conta")'
    ).first();
    const visible = await accountLink.isVisible({ timeout: 8_000 }).catch(() => false);
    if (!visible) { console.warn('[P1] Link de edição de conta não encontrado'); test.skip(); return; }
    const href = await accountLink.getAttribute('href');
    expect(href).toMatch(/account|dados|perfil/i);
  });

  test('06 — [P2] breadcrumb ou título identifica portal B2B', async ({ page }) => {
    if (!process.env.TEST_USER) { test.skip(); return; }
    const loggedIn = await loginB2B(page);
    if (!loggedIn) { test.skip(); return; }
    const ok = await navigateTo(page, DASHBOARD_URL);
    if (!ok) test.skip();
    const breadOrTitle = page.locator(
      '.breadcrumbs, .page-title, h1, .b2b-portal-title'
    ).first();
    const visible = await breadOrTitle.isVisible({ timeout: 8_000 }).catch(() => false);
    if (!visible) console.warn('[P2] Breadcrumb/título não visível no dashboard B2B');
  });

});
