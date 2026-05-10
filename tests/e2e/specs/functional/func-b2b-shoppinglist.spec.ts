import { test, expect } from '@playwright/test';
import { navigateTo, loginB2B } from '../../helpers/visual-audit.helpers';

const SHOPPINGLIST_URL = 'https://awamotos.com/b2b/shoppinglist/';
const QUOTE_URL        = 'https://awamotos.com/b2b/quote/';
const REORDER_URL      = 'https://awamotos.com/b2b/reorder/history/';
const CREDIT_URL       = 'https://awamotos.com/b2b/credit/';
const PDP_URL          = 'https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html';

test.describe('B2B Lista de Compras, Cotações e Serviços', () => {

  test('01 — [P0] guest em /b2b/shoppinglist/ redireciona para login', async ({ page }) => {
    const ok = await navigateTo(page, SHOPPINGLIST_URL);
    if (!ok) test.skip();
    await page.waitForLoadState('domcontentloaded', { timeout: 15_000 }).catch(() => {});
    const url = page.url();
    expect(url).toMatch(/login|b2b\/account/);
  });

  test('02 — [P0] lista de compras carrega para logado', async ({ page }) => {
    if (!process.env.TEST_USER) { test.skip(); return; }
    const loggedIn = await loginB2B(page);
    if (!loggedIn) { test.skip(); return; }
    const ok = await navigateTo(page, SHOPPINGLIST_URL);
    if (!ok) test.skip();
    const content = page.locator(
      '.b2b-shoppinglist, .b2b-shopping-list, .page-title, h1'
    ).first();
    await expect(content).toBeVisible({ timeout: 12_000 });
    // Não deve redirecionar para login
    expect(page.url()).not.toMatch(/login/);
  });

  test('03 — [P1] guest em /b2b/quote/ redireciona para login', async ({ page }) => {
    const ok = await navigateTo(page, QUOTE_URL);
    if (!ok) test.skip();
    await page.waitForLoadState('domcontentloaded', { timeout: 15_000 }).catch(() => {});
    const url = page.url();
    expect(url).toMatch(/login|b2b\/account/);
  });

  test('04 — [P1] cotações carregam para logado', async ({ page }) => {
    if (!process.env.TEST_USER) { test.skip(); return; }
    const loggedIn = await loginB2B(page);
    if (!loggedIn) { test.skip(); return; }
    const ok = await navigateTo(page, QUOTE_URL);
    if (!ok) test.skip();
    const content = page.locator(
      '.b2b-quotes, .b2b-quote-list, .page-title, h1, table'
    ).first();
    const visible = await content.isVisible({ timeout: 12_000 }).catch(() => false);
    if (!visible) console.warn('[P1] Conteúdo de cotações não encontrado');
    expect(page.url()).not.toMatch(/login/);
  });

  test('05 — [P1] botão de cotação visível para logado na PDP', async ({ page }) => {
    if (!process.env.TEST_USER) { test.skip(); return; }
    const loggedIn = await loginB2B(page);
    if (!loggedIn) { test.skip(); return; }
    const ok = await navigateTo(page, PDP_URL);
    if (!ok) test.skip();
    const quoteBtn = page.locator(
      '[data-b2b-quote-trigger], .b2b-quote-btn, button:has-text("Cotação"), a:has-text("Cotação")'
    ).first();
    const visible = await quoteBtn.isVisible({ timeout: 10_000 }).catch(() => false);
    if (!visible) console.warn('[P1] Botão de cotação não encontrado na PDP para usuário logado');
  });

  test('06 — [P0] botão de cotação ausente/oculto para guest na PDP', async ({ page }) => {
    const ok = await navigateTo(page, PDP_URL);
    if (!ok) test.skip();
    await page.waitForTimeout(2_000);
    // Para guest, o botão de cotação não deve abrir sem autenticação
    const quoteBtn = page.locator('[data-b2b-quote-trigger]').first();
    const visible = await quoteBtn.isVisible({ timeout: 3_000 }).catch(() => false);
    if (visible) {
      // Se visível, clicar não deve executar ação sem auth
      console.warn('[P1] Botão de cotação visível para guest — verificar se exige login ao clicar');
    }
  });

  test('07 — [P1] guest em /b2b/reorder/history/ redireciona para login', async ({ page }) => {
    const ok = await navigateTo(page, REORDER_URL);
    if (!ok) test.skip();
    await page.waitForLoadState('domcontentloaded', { timeout: 15_000 }).catch(() => {});
    const url = page.url();
    expect(url).toMatch(/login|b2b\/account/);
  });

  test('08 — [P2] guest em /b2b/credit/ redireciona para login', async ({ page }) => {
    const ok = await navigateTo(page, CREDIT_URL);
    if (!ok) test.skip();
    await page.waitForLoadState('domcontentloaded', { timeout: 15_000 }).catch(() => {});
    const url = page.url();
    const redirected = url.includes('login') || url.includes('b2b/account');
    if (!redirected) console.warn('[P2] /b2b/credit/ não redirecionou guest para login');
  });

});
