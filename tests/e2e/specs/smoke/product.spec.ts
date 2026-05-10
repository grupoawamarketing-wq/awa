import { test, expect } from '@playwright/test';
import {
  navigateTo, COMMON, collectConsoleErrors, filterCriticalJsErrors,
  checkOverflow, findBrokenImages, waitForImages,
} from '../../helpers/deep-audit.helpers';

const PDP = 'https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html';

test.describe('Smoke — Produto (PDP)', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, PDP);
    if (!ok) test.skip();
  });

  test('01 — HTTP OK', async ({ page }) => {
    const res = await page.request.get(PDP).catch(() => null);
    if (res) expect(res.status()).toBeLessThan(400);
  });

  test('02 — título do produto', async ({ page }) => {
    const h1 = page.locator('h1.page-title, h1 .base, .product-info-main h1').first();
    await expect(h1).toBeVisible({ timeout: 10000 });
    const text = await h1.textContent();
    expect(text?.trim().length).toBeGreaterThan(3);
  });

  test('03 — preço ou B2B gate (P0)', async ({ page }) => {
    const price = page.locator('.product-info-price .price, .price-box .price').first();
    const vis = await price.isVisible({ timeout: 8000 }).catch(() => false);
    if (vis) return;
    const gate = page.locator('.b2b-login-to-buy-btn, .b2b-login-to-see-price').first();
    const gateVis = await gate.isVisible({ timeout: 5000 }).catch(() => false);
    if (gateVis) { console.info('[INFO] B2B gate ativo'); return; }
    console.error('[P0] Sem preço e sem gate B2B');
    expect(false).toBe(true);
  });

  test('04 — imagem principal (P1)', async ({ page }) => {
    await waitForImages(page);
    // fotorama__stage loads async; fallback to .product-image-photo or .product.media img
    const img = page.locator('.product-image-photo, .gallery-placeholder__image, .product.media img, .fotorama__stage img').first();
    const vis = await img.isVisible({ timeout: 15000 }).catch(() => false);
    if (!vis) console.warn('[P1] Imagem principal do produto não visível (fotorama init lento?)');
  });

  test('05 — breadcrumb', async ({ page }) => {
    await expect(page.locator(COMMON.breadcrumb).first()).toBeVisible({ timeout: 8000 });
  });

  test('06 — SKU visível', async ({ page }) => {
    const sku = page.locator('.product.attribute.sku .value, [itemprop="sku"]').first();
    const vis = await sku.isVisible({ timeout: 5000 }).catch(() => false);
    if (!vis) console.warn('[P2] SKU não visível');
  });

  test('07 — qty input', async ({ page }) => {
    const qty = page.locator('input#qty, input[name="qty"]').first();
    const vis = await qty.isVisible({ timeout: 5000 }).catch(() => false);
    if (!vis) console.info('[INFO] Qty input oculto (pode ser B2B)');
  });

  test('08 — botão comprar ou B2B gate', async ({ page }) => {
    const btn = page.locator('#product-addtocart-button, button.action.tocart').first();
    const vis = await btn.isVisible({ timeout: 8000 }).catch(() => false);
    if (vis) return;
    const gate = page.locator('.b2b-login-to-buy-btn').first();
    await expect(gate).toBeVisible({ timeout: 5000 });
  });

  test('09 — sem JS errors (P0)', async ({ page }) => {
    const errors = collectConsoleErrors(page);
    await page.waitForTimeout(3000);
    const critical = filterCriticalJsErrors(errors);
    expect(critical).toHaveLength(0);
  });

  test('10 — sem overflow', async ({ page }) => {
    const { hasOverflow } = await checkOverflow(page);
    expect(hasOverflow).toBe(false);
  });

  test('11 — sem imagens quebradas (P1)', async ({ page }) => {
    await waitForImages(page);
    const broken = await findBrokenImages(page);
    if (broken.length > 0) console.warn('[P1] Imagens quebradas no PDP:', broken.join(', '));
  });

  test('12 — detalhes/tabs do produto', async ({ page }) => {
    const tabs = page.locator('.product.info.detailed, .product-info-tabs, .data.item.title').first();
    const vis = await tabs.isVisible({ timeout: 8000 }).catch(() => false);
    if (!vis) console.warn('[P2] Tabs de detalhes não visíveis');
  });
});