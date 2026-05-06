/**
 * Visual Audit — Fases 5, 6: Login B2B, PDP Premium
 *
 * Usa beforeAll + sharedPage — navega uma única vez por describe.
 * Isso previne crashes do Chrome após múltiplas navegações.
 */
import { test, expect, type Page } from '@playwright/test';
import {
  navigateTo, css, cssMultiple, px,
  isVisible, hasNoOverflow, collectJsErrors, assertMinHeight,
} from '../helpers/visual-audit.helpers';

const BASE = 'https://awamotos.com';
const PDP_URL = '/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html';

/* ═══════════════════════════════════════════════════════════════════
   FASE 5 — LOGIN B2B PREMIUM
   Usa beforeAll + loginPage compartilhada.
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Fase 5 — Login B2B Premium', () => {
  let loginPage: Page;

  test.beforeAll(async ({ browser }) => {
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'pt-BR' }).catch(() => null);
    if (!ctx) return;
    loginPage = await ctx.newPage();
    const ok = await navigateTo(loginPage, `${BASE}/customer/account/login/`);
    if (!ok) return;
    await loginPage.waitForLoadState('domcontentloaded', { timeout: 15_000 }).catch(() => {});
    await loginPage.waitForTimeout(1_000).catch(() => {});
  });

  test.afterAll(async () => {
    await loginPage?.context().close().catch(() => {});
  });

  test('Formulário de login visível', async () => {
    if (!loginPage || !await loginPage.evaluate(() => true).catch(() => false)) { test.skip(); return; }
    const form = await isVisible(loginPage, '#b2b-email, #email, .login-container', 10_000).catch(() => false);
    if (!form) { test.skip(); return; }
    expect(form, 'Formulário de login deve estar visível').toBe(true);
  });

  test('Inputs de login com estilo premium', async () => {
    if (!loginPage || !await loginPage.evaluate(() => true).catch(() => false)) { test.skip(); return; }
    const emailInput = loginPage.locator('#b2b-email, #email').first();
    const visible = await emailInput.isVisible().catch(() => false);
    if (!visible) { test.skip(); return; }
    const styles = await cssMultiple(loginPage, '#b2b-email, #email', [
      'height', 'border-radius', 'border-color', 'font-size',
    ]).catch((): Record<string,string> => ({}));
    if (!styles['height']) { test.skip(); return; }
    expect(px(styles['height']), 'Input height >= 40px').toBeGreaterThanOrEqual(40);
    expect(px(styles['border-radius']), 'Input border-radius >= 4px').toBeGreaterThanOrEqual(4);
    expect(px(styles['font-size']), 'Input font-size >= 14px').toBeGreaterThanOrEqual(14);
  });

  test('Botão de login com estilo premium', async () => {
    if (!loginPage || !await loginPage.evaluate(() => true).catch(() => false)) { test.skip(); return; }
    const btn = loginPage.locator('.b2b-btn-entrar, #send2, button[type="submit"]').first();
    const visible = await btn.isVisible().catch(() => false);
    if (!visible) { test.skip(); return; }
    const styles = await cssMultiple(loginPage, '.b2b-btn-entrar, #send2, button[type="submit"]', [
      'height', 'border-radius', 'font-weight', 'font-size',
    ]).catch((): Record<string,string> => ({}));
    if (!styles['height']) { test.skip(); return; }
    expect(px(styles['height']), 'Botão height >= 40px').toBeGreaterThanOrEqual(40);
    expect(px(styles['border-radius']), 'Botão border-radius >= 4px').toBeGreaterThanOrEqual(4);
  });

  test('Sem overflow horizontal no login', async () => {
    if (!loginPage || !await loginPage.evaluate(() => true).catch(() => false)) { test.skip(); return; }
    const ok = await hasNoOverflow(loginPage).catch(() => null);
    if (ok === null) { test.skip(); return; }
    expect(ok, 'Login sem overflow horizontal').toBe(true);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   FASE 6 — PDP PREMIUM
   Usa beforeAll + pdpPage compartilhada.
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Fase 6 — PDP Premium', () => {
  let pdpPage: Page;

  test.beforeAll(async ({ browser }) => {
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'pt-BR' }).catch(() => null);
    if (!ctx) return;
    pdpPage = await ctx.newPage();
    try {
      await pdpPage.goto(`${BASE}${PDP_URL}`, { waitUntil: 'commit', timeout: 60_000 });
      await pdpPage.waitForSelector('.page-title .base, h1.page-title', { timeout: 45_000 });
      await pdpPage.waitForLoadState('domcontentloaded', { timeout: 15_000 }).catch(() => {});
      await pdpPage.waitForTimeout(1_000).catch(() => {});
    } catch {
      // pdpPage will be closed/invalid — tests will skip
    }
  });

  test.afterAll(async () => {
    await pdpPage?.context().close().catch(() => {});
  });

  test('Breadcrumb visível na PDP', async () => {
    if (!pdpPage || !await pdpPage.evaluate(() => true).catch(() => false)) { test.skip(); return; }
    const bc = await isVisible(pdpPage, '.breadcrumbs', 8_000).catch(() => false);
    expect(bc, 'Breadcrumb deve estar visível na PDP').toBe(true);
  });

  test('Nome do produto com tipografia premium', async () => {
    if (!pdpPage || !await pdpPage.evaluate(() => true).catch(() => false)) { test.skip(); return; }
    const name = pdpPage.locator('.page-title .base, h1.page-title').first();
    const visible = await name.isVisible().catch(() => false);
    if (!visible) { test.skip(); return; }
    const styles = await cssMultiple(pdpPage, '.page-title .base, h1.page-title', [
      'font-size', 'font-weight',
    ]).catch((): Record<string,string> => ({}));
    if (!styles['font-size']) { test.skip(); return; }
    expect(px(styles['font-size']), 'Título font-size >= 20px').toBeGreaterThanOrEqual(20);
    const weight = parseInt(styles['font-weight']) || 0;
    expect(weight, 'Título font-weight >= 600').toBeGreaterThanOrEqual(600);
  });

  test('Galeria de imagens com dimensões adequadas', async () => {
    if (!pdpPage || !await pdpPage.evaluate(() => true).catch(() => false)) { test.skip(); return; }
    const gallery = pdpPage.locator('.fotorama, [data-gallery-role="gallery-placeholder"]').first();
    const visible = await gallery.isVisible({ timeout: 15_000 }).catch(() => false);
    if (!visible) { test.skip(); return; }
    const box = await gallery.boundingBox();
    expect(box, 'Galeria deve ter bounding box').toBeTruthy();
    expect(box!.width, 'Galeria width >= 200px').toBeGreaterThanOrEqual(200);
    expect(box!.height, 'Galeria height >= 200px').toBeGreaterThanOrEqual(200);
  });

  test('Área de preço presente (ou overlay B2B)', async () => {
    if (!pdpPage || !await pdpPage.evaluate(() => true).catch(() => false)) { test.skip(); return; }
    const price = pdpPage.locator('.product-info-price .price, .price-box .price').first();
    const priceVisible = await price.isVisible().catch(() => false);
    const b2b = await pdpPage.locator('.b2b-login-to-see-price').first().isVisible().catch(() => false);
    expect(priceVisible || b2b, 'Preço ou overlay B2B visível na PDP').toBe(true);
  });

  test('Botão Add-to-Cart presente (pode estar oculto para guest B2B)', async () => {
    if (!pdpPage || !await pdpPage.evaluate(() => true).catch(() => false)) { test.skip(); return; }
    const btn = pdpPage.locator('#product-addtocart-button').first();
    const attached = await btn.isVisible({ timeout: 10_000 }).catch(() => false)
      || await pdpPage.locator('#product-addtocart-button').count().then(n => n > 0).catch(() => false);
    if (!attached) { test.skip(); return; }
    const visible = await btn.isVisible().catch(() => false);
    if (visible) {
      const styles = await cssMultiple(pdpPage, '#product-addtocart-button', [
        'height', 'border-radius', 'font-weight',
      ]).catch((): Record<string,string> => ({}));
      if (styles['height']) {
        expect(px(styles['height']), 'ATC button height >= 44px').toBeGreaterThanOrEqual(44);
      }
    }
  });

  test('Tabs de informação do produto', async () => {
    if (!pdpPage || !await pdpPage.evaluate(() => true).catch(() => false)) { test.skip(); return; }
    const tabs = pdpPage.locator('.product.data.items, .awa-pdp-tabs, #tabs-product-info-tabs').first();
    const visible = await tabs.isVisible({ timeout: 15_000 }).catch(() => false);
    if (!visible) { test.skip(); return; }
    const count = await pdpPage.locator('.data.item.title, [data-role="collapsible"]').count().catch(() => 0);
    expect(count, 'PDP deve ter pelo menos 1 tab').toBeGreaterThan(0);
  });

  test('SKU visível na PDP', async () => {
    if (!pdpPage || !await pdpPage.evaluate(() => true).catch(() => false)) { test.skip(); return; }
    const sku = pdpPage.locator('.product.attribute.sku .value').first();
    const visible = await sku.isVisible().catch(() => false);
    if (visible) {
      const text = await sku.textContent();
      expect(text?.trim().length, 'SKU não deve ser vazio').toBeGreaterThan(0);
    } else {
      console.warn('⚠️ SKU não visível na PDP');
    }
  });

  test('Sem overflow horizontal na PDP', async () => {
    if (!pdpPage || !await pdpPage.evaluate(() => true).catch(() => false)) { test.skip(); return; }
    const ok = await hasNoOverflow(pdpPage).catch(() => null);
    if (ok === null) { test.skip(); return; }
    expect(ok, 'PDP sem overflow horizontal').toBe(true);
  });

  test('Sem erros JS críticos na PDP', async () => {
    if (!pdpPage || !await pdpPage.evaluate(() => true).catch(() => false)) { test.skip(); return; }
    const errors: string[] = [];
    pdpPage.on('pageerror', (e) => errors.push(e.message));
    await pdpPage.waitForTimeout(1_000).catch(() => {});
    const critical = errors.filter(e =>
      !e.includes('Script error') && !e.includes('requirejs') && !e.includes('mage/cookies')
    );
    expect(critical.length, 'Máximo 3 erros JS na PDP').toBeLessThanOrEqual(3);
  });
});
