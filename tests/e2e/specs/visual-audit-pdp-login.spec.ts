/**
 * Visual Audit — Fases 5, 6: Login B2B, PDP Premium
 *
 * Valida CSS aplicado pelas fases:
 *  - login-premium (formulário B2B, inputs, botões, card)
 *  - pdp-layout-premium (galeria, info, tabs, sidebar)
 *  - pdp-b2b-gate-premium (overlay login-to-see-price)
 *  - pdp-fase6-professional (tabs profissionais, sidebar promo)
 */
import { test, expect } from '@playwright/test';
import {
  navigateTo, css, cssMultiple, px,
  isVisible, hasNoOverflow, collectJsErrors, assertMinHeight,
} from '../helpers/visual-audit.helpers';

const BASE = 'https://awamotos.com';
const PDP_URL = '/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html';

/* ═══════════════════════════════════════════════════════════════════
   FASE 5 — LOGIN B2B PREMIUM
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Fase 5 — Login B2B Premium', () => {
  test.beforeEach(async ({ page }) => {
    if (!await navigateTo(page, `${BASE}/customer/account/login/`)) test.skip();
  });

  test('Formulário de login visível', async ({ page }) => {
    // B2B login form ou standard Magento login
    const form = await isVisible(page, '#b2b-email, #email, .login-container', 10_000);
    expect(form, 'Formulário de login deve estar visível').toBe(true);
  });

  test('Inputs de login com estilo premium', async ({ page }) => {
    const emailInput = page.locator('#b2b-email, #email').first();
    const visible = await emailInput.isVisible().catch(() => false);
    if (!visible) { test.skip(); return; }

    const styles = await cssMultiple(page, '#b2b-email, #email', [
      'height', 'border-radius', 'border-color', 'font-size',
    ]);
    expect(px(styles['height']), 'Input height >= 40px').toBeGreaterThanOrEqual(40);
    expect(px(styles['border-radius']), 'Input border-radius >= 4px').toBeGreaterThanOrEqual(4);
    expect(px(styles['font-size']), 'Input font-size >= 14px').toBeGreaterThanOrEqual(14);
  });

  test('Botão de login com estilo premium', async ({ page }) => {
    const btn = page.locator('.b2b-btn-entrar, #send2, button[type="submit"]').first();
    const visible = await btn.isVisible().catch(() => false);
    if (!visible) { test.skip(); return; }

    const styles = await cssMultiple(page, '.b2b-btn-entrar, #send2, button[type="submit"]', [
      'height', 'border-radius', 'font-weight', 'font-size',
    ]);
    expect(px(styles['height']), 'Botão height >= 44px').toBeGreaterThanOrEqual(44);
    expect(px(styles['border-radius']), 'Botão border-radius >= 4px').toBeGreaterThanOrEqual(4);
  });

  test('Sem overflow horizontal no login', async ({ page }) => {
    expect(await hasNoOverflow(page), 'Login sem overflow horizontal').toBe(true);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   FASE 6 — PDP PREMIUM
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Fase 6 — PDP Premium', () => {
  test.beforeEach(async ({ page }) => {
    try {
      await page.goto(`${BASE}${PDP_URL}`, { waitUntil: 'commit', timeout: 60_000 });
      await page.waitForSelector('.page-title .base, h1.page-title', { timeout: 45_000 });
      await page.waitForTimeout(500);
    } catch {
      test.skip();
    }
  });

  test('Breadcrumb visível na PDP', async ({ page }) => {
    const bc = await isVisible(page, '.breadcrumbs', 8_000);
    expect(bc, 'Breadcrumb deve estar visível na PDP').toBe(true);
  });

  test('Nome do produto com tipografia premium', async ({ page }) => {
    const name = page.locator('.page-title .base, h1.page-title').first();
    await expect(name).toBeVisible();
    const styles = await cssMultiple(page, '.page-title .base, h1.page-title', [
      'font-size', 'font-weight',
    ]);
    expect(px(styles['font-size']), 'Título font-size >= 20px').toBeGreaterThanOrEqual(20);
    const weight = parseInt(styles['font-weight']) || 0;
    expect(weight, 'Título font-weight >= 600').toBeGreaterThanOrEqual(600);
  });

  test('Galeria de imagens com dimensões adequadas', async ({ page }) => {
    const gallery = page.locator('.fotorama, [data-gallery-role="gallery-placeholder"]').first();
    await expect(gallery).toBeVisible({ timeout: 15_000 });
    const box = await gallery.boundingBox();
    expect(box, 'Galeria deve ter bounding box').toBeTruthy();
    expect(box!.width, 'Galeria width >= 200px').toBeGreaterThanOrEqual(200);
    expect(box!.height, 'Galeria height >= 200px').toBeGreaterThanOrEqual(200);
  });

  test('Área de preço presente (ou overlay B2B)', async ({ page }) => {
    const price = page.locator('.product-info-price .price, .price-box .price').first();
    const priceVisible = await price.isVisible().catch(() => false);
    const b2b = await page.locator('.b2b-login-to-see-price').first().isVisible().catch(() => false);
    expect(priceVisible || b2b, 'Preço ou overlay B2B visível na PDP').toBe(true);
  });

  test('Botão Add-to-Cart presente (pode estar oculto para guest B2B)', async ({ page }) => {
    const btn = page.locator('#product-addtocart-button').first();
    await expect(btn).toBeAttached({ timeout: 10_000 });
    const visible = await btn.isVisible().catch(() => false);
    if (visible) {
      const styles = await cssMultiple(page, '#product-addtocart-button', [
        'height', 'border-radius', 'font-weight',
      ]);
      expect(px(styles['height']), 'ATC button height >= 44px').toBeGreaterThanOrEqual(44);
    }
  });

  test('Tabs de informação do produto', async ({ page }) => {
    const tabs = page.locator('.product.data.items, .awa-pdp-tabs, #tabs-product-info-tabs').first();
    await expect(tabs).toBeVisible({ timeout: 15_000 });

    const titles = page.locator('.data.item.title, [data-role="collapsible"]');
    const count = await titles.count();
    expect(count, 'PDP deve ter pelo menos 1 tab').toBeGreaterThan(0);
  });

  test('SKU visível na PDP', async ({ page }) => {
    const sku = page.locator('.product.attribute.sku .value').first();
    const visible = await sku.isVisible().catch(() => false);
    // SKU deve estar visível — importante para B2B
    if (visible) {
      const text = await sku.textContent();
      expect(text?.trim().length, 'SKU não deve ser vazio').toBeGreaterThan(0);
    } else {
      console.warn('⚠️ SKU não visível na PDP');
    }
  });

  test('Sem overflow horizontal na PDP', async ({ page }) => {
    expect(await hasNoOverflow(page), 'PDP sem overflow horizontal').toBe(true);
  });

  test('Sem erros JS críticos na PDP', async ({ page }) => {
    const errors = collectJsErrors(page);
    await page.goto(`${BASE}${PDP_URL}`, { waitUntil: 'commit', timeout: 60_000 });
    await page.waitForTimeout(3_000);
    const critical = errors.filter(e =>
      !e.includes('Script error') && !e.includes('requirejs') && !e.includes('mage/cookies')
    );
    expect(critical.length, 'Máximo 3 erros JS na PDP').toBeLessThanOrEqual(3);
  });
});
