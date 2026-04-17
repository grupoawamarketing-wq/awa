/**
 * Visual Audit — Fase 8: Cart, Checkout, 404, Account
 *
 * Valida CSS aplicado pelas fases:
 *  - cart-premium (cart table, qty inputs, summary, botão checkout)
 *  - checkout-premium (container, inputs, place order button)
 *  - noroute-premium (404 card, CTA, centralized layout)
 *  - account-premium (customer account area)
 */
import { test, expect } from '@playwright/test';
import {
  navigateTo, loginB2B, css, cssMultiple, px,
  isVisible, hasNoOverflow, collectJsErrors, assertMinHeight,
} from '../helpers/visual-audit.helpers';

const BASE = 'https://awamotos.com';
const PDP_URL = `${BASE}/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html`;

/* ═══════════════════════════════════════════════════════════════════
   FASE 8A — CART PREMIUM
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Fase 8 — Cart Premium', () => {
  test.beforeEach(async ({ page }) => {
    if (!await navigateTo(page, `${BASE}/checkout/cart/`)) test.skip();
  });

  test('Página de carrinho carrega corretamente', async ({ page }) => {
    // Pode estar vazio ou com produtos
    const body = await page.locator('body.checkout-cart-index').count();
    expect(body, 'Body class checkout-cart-index presente').toBe(1);
  });

  test('Título do carrinho visível', async ({ page }) => {
    const title = await isVisible(page, '.page-title, h1', 8_000);
    expect(title, 'Título do carrinho deve estar visível').toBe(true);
  });

  test('Sem overflow horizontal no carrinho', async ({ page }) => {
    expect(await hasNoOverflow(page), 'Carrinho sem overflow').toBe(true);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   FASE 8B — CHECKOUT PREMIUM
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Fase 8 — Checkout Premium', () => {
  test.beforeEach(async ({ page }) => {
    if (!await navigateTo(page, `${BASE}/checkout/`)) test.skip();
    // Checkout redireciona para cart se vazio — verificar URL
    const url = page.url();
    if (url.includes('/cart') && !url.includes('/checkout')) {
      test.skip();
    }
    await page.waitForTimeout(3_000); // KO.js rendering
  });

  test('Checkout container com max-width correto', async ({ page }) => {
    const url = page.url();
    if (!url.includes('/checkout') && !url.includes('expresscheckout')) { test.skip(); return; }

    const maxW = await css(page, '.checkout-index-index .page-main, .page-main', 'max-width');
    if (maxW && maxW !== 'none') {
      const val = px(maxW);
      expect(val, 'Checkout container max-width <= 1400px').toBeLessThanOrEqual(1400);
      expect(val, 'Checkout container max-width >= 960px').toBeGreaterThanOrEqual(960);
    }
  });

  test('Checkout inputs com estilo premium', async ({ page }) => {
    const url = page.url();
    if (!url.includes('/checkout') && !url.includes('expresscheckout')) { test.skip(); return; }

    const input = page.locator('.checkout-shipping-address input[type="text"], input[name="street[0]"]').first();
    const visible = await input.isVisible().catch(() => false);
    if (!visible) { test.skip(); return; }

    const styles = await cssMultiple(page, '.checkout-shipping-address input[type="text"], input[name="street[0]"]', [
      'height', 'border-radius',
    ]);
    expect(px(styles['height']), 'Checkout input height >= 40px').toBeGreaterThanOrEqual(40);
  });

  test('Sem overflow horizontal no checkout', async ({ page }) => {
    expect(await hasNoOverflow(page), 'Checkout sem overflow').toBe(true);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   FASE 8C — 404 PREMIUM (NO-ROUTE)
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Fase 8 — 404 Premium', () => {
  test.beforeEach(async ({ page }) => {
    // Navegar diretamente para a página CMS no-route
    if (!await navigateTo(page, `${BASE}/no-route`)) test.skip();
  });

  test('Página 404 carrega com body class correto', async ({ page }) => {
    const hasCmsClass = await page.evaluate(() => {
      return document.body.classList.contains('cms-no-route') ||
             document.body.classList.contains('cms-noroute-index');
    });
    expect(hasCmsClass, 'Body deve ter class cms-no-route ou cms-noroute-index').toBe(true);
  });

  test('Card 404 centralizado com max-width', async ({ page }) => {
    const mainCol = page.locator('.column.main').first();
    await expect(mainCol).toBeVisible({ timeout: 8_000 });
    const box = await mainCol.boundingBox();
    expect(box, 'Column.main deve ter bounding box').toBeTruthy();
    // Deve estar centralizado (margem esquerda > 0 em desktop)
    const viewportWidth = await page.evaluate(() => window.innerWidth);
    if (viewportWidth >= 1024) {
      expect(box!.x, 'Card 404 deve ter margem esquerda (centralizado)').toBeGreaterThan(20);
    }
  });

  test('Título 404 com estilo premium', async ({ page }) => {
    const title = page.locator('.page-title, h1, .awa-404-page h1, .awa-404-page__title').first();
    await expect(title).toBeVisible({ timeout: 8_000 });
    const styles = await cssMultiple(page, '.page-title, h1', ['font-size', 'font-weight']);
    expect(px(styles['font-size']), 'Título 404 font-size >= 20px').toBeGreaterThanOrEqual(20);
    const weight = parseInt(styles['font-weight']) || 0;
    expect(weight, 'Título 404 font-weight >= 600').toBeGreaterThanOrEqual(600);
  });

  test('CTAs visíveis na página 404', async ({ page }) => {
    const cta = page.locator('.awa-404-page a, .column.main a.action, .column.main a.primary, .column.main .action.primary').first();
    const visible = await cta.isVisible().catch(() => false);
    if (visible) {
      const styles = await cssMultiple(page, '.awa-404-page a, .column.main a.action', ['height', 'border-radius']);
      if (styles['height']) {
        expect(px(styles['height']), 'CTA height >= 36px').toBeGreaterThanOrEqual(36);
      }
    } else {
      console.warn('⚠️ CTA não encontrado na página 404');
    }
  });

  test('Sem overflow horizontal na 404', async ({ page }) => {
    expect(await hasNoOverflow(page), '404 sem overflow').toBe(true);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   ACCOUNT PREMIUM
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Account Premium', () => {
  test.beforeEach(async ({ page }) => {
    if (!await navigateTo(page, `${BASE}/customer/account/`)) test.skip();
    // Pode redirecionar para login — verificar
    const url = page.url();
    if (url.includes('/login')) {
      // Tentar login
      const loggedIn = await loginB2B(page);
      if (!loggedIn) { test.skip(); return; }
      await navigateTo(page, `${BASE}/customer/account/`);
    }
  });

  test('Área de conta carrega com sidebar', async ({ page }) => {
    const sidebar = await isVisible(page, '.account-nav, .block-collapsible-nav, .sidebar-main', 10_000);
    // Sidebar pode não existir em mobile — soft check
    const title = await isVisible(page, '.page-title, h1', 8_000);
    expect(sidebar || title, 'Account deve ter sidebar ou título').toBe(true);
  });

  test('Sem overflow horizontal na conta', async ({ page }) => {
    expect(await hasNoOverflow(page), 'Account sem overflow').toBe(true);
  });
});
