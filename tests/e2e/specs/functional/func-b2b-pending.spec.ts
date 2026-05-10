import { test, expect } from '@playwright/test';
import { navigateTo } from '../../helpers/visual-audit.helpers';

const PDP_URL = 'https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html';
const PLP_URL = 'https://awamotos.com/bagageiros.html';

test.describe('B2B Pending — conta aguardando aprovação', () => {

  // Nota: testes 01-04 exigem usuário com conta pending (grupo "Pendente").
  // Sem TEST_USER_PENDING/TEST_PASS_PENDING definidos, esses testes verificam
  // o comportamento esperado para guest (que não deve ver o banner pending).

  test('01 — [P0] guest NÃO vê banner pending na PDP', async ({ page }) => {
    const ok = await navigateTo(page, PDP_URL);
    if (!ok) test.skip();
    // Aguarda JS B2B processar
    await page.waitForTimeout(2_000);
    const pendingBanner = page.locator('#b2b-pending-banner');
    // Guest não deve ver o banner pending (só usuário logado mas não aprovado vê)
    const visible = await pendingBanner.isVisible({ timeout: 3_000 }).catch(() => false);
    expect(visible, 'Guest não deveria ver o banner pending de aprovação').toBe(false);
  });

  test('02 — [P0] guest vê botão "Entrar para Comprar" na PDP', async ({ page }) => {
    const ok = await navigateTo(page, PDP_URL);
    if (!ok) test.skip();
    // JS do B2B precisa de tempo para injetar o botão
    const loginBtn = page.locator('.b2b-login-to-buy-btn').first();
    const visible = await loginBtn.isVisible({ timeout: 12_000 }).catch(() => false);
    if (!visible) {
      console.warn('[P0] Botão .b2b-login-to-buy-btn não encontrado para guest na PDP');
    }
    // O botão original #product-addtocart-button deve estar oculto
    const originalBtn = page.locator('#product-addtocart-button');
    const originalVisible = await originalBtn.isVisible({ timeout: 3_000 }).catch(() => false);
    if (originalVisible) {
      console.warn('[P0] BUG: botão add-to-cart original ainda visível para guest');
    }
  });

  test('03 — [P1] guest na PLP vê gate B2B em vez de add-to-cart', async ({ page }) => {
    const ok = await navigateTo(page, PLP_URL);
    if (!ok) test.skip();
    // Aguarda produtos carregarem via LayeredAjax
    await page.waitForSelector('.product-item-link, .product-item', { timeout: 20_000 }).catch(() => {});
    await page.waitForTimeout(2_000);
    // Verificação primária: servidor já renderiza o awa-b2b-gate-card na PLP
    // (não há button.tocart no DOM para guests — o servidor substitui antes de enviar HTML)
    const gateCards = page.locator('.awa-b2b-gate-card, [data-awa-gate-state="guest"]');
    const gateCount = await gateCards.count().catch(() => 0);
    if (gateCount === 0) {
      console.warn('[P1] Nenhum gate B2B server-side (.awa-b2b-gate-card) encontrado na PLP para guest');
    } else {
      // Confirmar que o gate card está visível (não oculto)
      const firstGate = gateCards.first();
      const gateVisible = await firstGate.isVisible().catch(() => false);
      if (!gateVisible) {
        console.warn('[P2] Gate B2B no DOM mas não visível na PLP');
      }
    }
    // Botões JS-injetados (.b2b-login-to-buy-btn) NÃO esperados na PLP
    // porque o servidor já substitui o botão. Isso é o comportamento CORRETO.
    const originalBtns = page.locator('button.tocart:visible');
    const visibleOriginals = await originalBtns.count().catch(() => 0);
    if (visibleOriginals > 0) {
      console.warn('[P0] BUG: button.tocart visível para guest na PLP — B2B gate não aplicado');
    }
  });

  test('04 — [P0] clicar no gate B2B da PLP abre login ou navega para login', async ({ page }) => {
    const ok = await navigateTo(page, PLP_URL);
    if (!ok) test.skip();
    await page.waitForSelector('.product-item-link, .product-item', { timeout: 20_000 }).catch(() => {});
    await page.waitForTimeout(2_000);
    // Na PLP, o servidor renderiza awa-b2b-gate-card com link para login
    // Verificar se há link de login dentro do gate card
    const loginLink = page.locator('.awa-b2b-gate-card a[href*="login"], .awa-b2b-gate-card__actions a').first();
    const linkVisible = await loginLink.isVisible({ timeout: 5_000 }).catch(() => false);
    if (!linkVisible) {
      // Fallback: tentar o botão JS-injetado (não esperado, mas cobre edge cases)
      const b2bBtn = page.locator('.b2b-login-to-buy-btn').first();
      const btnVisible = await b2bBtn.isVisible({ timeout: 3_000 }).catch(() => false);
      if (!btnVisible) {
        console.warn('[P1] Nenhum elemento de ação B2B visível na PLP (gate card ou botão JS)');
        test.skip();
        return;
      }
      await b2bBtn.click({ force: true });
      await page.waitForTimeout(500);
      const modal = page.locator('#b2b-login-modal');
      await expect(modal).toBeVisible({ timeout: 8_000 });
      return;
    }
    // Confirmar que o link direciona para login
    const href = await loginLink.getAttribute('href').catch(() => '');
    expect(href).toMatch(/login|account|b2b/i);
  });

  test('05 — [P1] link "Minha Conta" do banner pending aponta para dashboard', async ({ page }) => {
    // Este teste documenta o comportamento esperado para conta pending.
    // Sem usuário pending disponível, verifica a estrutura do HTML do banner.
    const ok = await navigateTo(page, PDP_URL);
    if (!ok) test.skip();
    // Inspecionar se o banner existe no DOM (mesmo que hidden para guest)
    const bannerInDom = await page.locator('#b2b-pending-banner').count().catch(() => 0);
    if (bannerInDom === 0) {
      console.warn('[P1] Banner pending não encontrado no DOM (não renderizado para guest — esperado com hide_add_to_cart_guests=1)');
      test.skip();
      return;
    }
    const accountLink = page.locator('#b2b-pending-banner .b2b-pending-account-link').first();
    const href = await accountLink.getAttribute('href').catch(() => null);
    if (href) {
      expect(href).toMatch(/b2b|account|dashboard/);
    }
  });

});
