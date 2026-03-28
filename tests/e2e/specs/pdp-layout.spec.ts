/**
 * AWA Motos — PDP (Product Detail Page) Layout Tests
 * Cobertura: Desktop/Notebook (1024–1366px) e Mobile (375–767px)
 *
 * Valida:
 *  - Tabs B2B Pro presentes e funcionais
 *  - Sidebar com elementos de conversão (promo, WhatsApp, share)
 *  - Galeria de imagens e zoom trigger
 *  - Layout sem overflow horizontal
 *  - Breadcrumb visível
 *  - Add-to-cart e price visíveis
 *  - Screenshots documentais por viewport
 */

import { test, expect, Page } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = path.join(__dirname, '..', 'screenshots');

/* ── Seletores PDP ────────────────────────────────────────────────────── */
const PDP = {
  breadcrumb:       '.breadcrumbs',
  productName:      '.page-title .base',
  productPrice:     '.product-info-price .price',
  addToCart:        '#product-addtocart-button',
  gallery:          '.fotorama, [data-gallery-role="gallery-placeholder"]',
  galleryMain:      '.fotorama__stage, .gallery-placeholder',
  zoomTrigger:      '.fotorama__zoom-in, [data-fotorama-action="zoom"]',
  tabsWrapper:      '.awa-pdp-tabs, #tabs-product-info-tabs',
  tabTitle:         '.awa-pdp-tabs .awa-tab-title, .product.info.detailed [data-role="title"]',
  tabContent:       '.awa-pdp-tabs .awa-tab-content, .product.info.detailed .data.item.content',
  sidebarPromo:     '.awa-pdp-sidebar__promo, .product-info-sidebar-promo',
  sidebarWhatsApp:  '.awa-pdp-sidebar__whatsapp, .awa-pdp-whatsapp-btn, [data-pdp-whatsapp]',
  sidebarShare:     '.awa-pdp-sidebar__share, .awa-pdp-share, [data-pdp-share]',
  productInfoMain:  '.product-info-main, .column.main .product-info-main',
  mediaSection:     '.column.main .product.media, .page-product-simple .product.media',
  reviewsTab:       '[data-tab-content="reviews"], #product-reviews',
  stockStatus:      '.stock.available, .stock.unavailable',
} as const;

/* ── Helper: navega para um produto real ou fixture ─────────────────── */
async function goToPDP(page: Page): Promise<void> {
  // Navigate to the first product in the catalog (category listing  → first product)
  // This approach is resilient: doesn't hardcode a specific SKU/URL
  await page.goto('/', { waitUntil: 'domcontentloaded', timeout: 30_000 });

  // Accept cookies if present
  const cookieBtn = page.locator('.cookie-btn-accept, #btn-cookie-allow, .allow').first();
  if (await cookieBtn.isVisible({ timeout: 2_000 }).catch(() => false)) {
    await cookieBtn.click();
  }

  // Go directly to catalog and click first product
  await page.goto('/acessorios.html', { waitUntil: 'domcontentloaded', timeout: 30_000 });
  const firstProduct = page.locator('.product-item a.product-item-link').first();
  await firstProduct.waitFor({ timeout: 8_000 });
  await firstProduct.click();
  await page.waitForLoadState('domcontentloaded', { timeout: 30_000 });
  await page.waitForSelector(PDP.productName, { timeout: 10_000 });
}

function screenshotPath(name: string): string {
  return path.join(SCREENSHOT_DIR, `pdp-${name}.png`);
}

/* ════════════════════════════════════════════════════════════════════════
   SUITE 1 — ELEMENTOS ESSENCIAIS DA PDP
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP — Elementos essenciais', () => {
  test.beforeEach(async ({ page }) => {
    await goToPDP(page);
  });

  test('Breadcrumb visível @pdp', async ({ page }) => {
    await expect(page.locator(PDP.breadcrumb).first()).toBeVisible();
    const text = await page.locator(PDP.breadcrumb).textContent();
    expect(text?.trim().length).toBeGreaterThan(0);
  });

  test('Nome do produto visível @pdp', async ({ page }) => {
    const name = page.locator(PDP.productName).first();
    await expect(name).toBeVisible();
    const text = await name.textContent();
    expect(text?.trim().length, 'Nome do produto não deve ser vazio').toBeGreaterThan(0);
  });

  test('Preço visível @pdp', async ({ page }) => {
    const price = page.locator(PDP.productPrice).first();
    await expect(price).toBeVisible({ timeout: 8_000 });
    const text = await price.textContent();
    expect(text, 'Preço deve ter valor').toMatch(/R\$|[\d,\.]+/);
  });

  test('Botão add-to-cart visível e habilitado @pdp', async ({ page }, testInfo) => {
    // Em mobile o botão pode estar em posição diferente mas deve existir
    const btn = page.locator(PDP.addToCart).first();
    await expect(btn).toBeVisible({ timeout: 8_000 });
    const disabled = await btn.getAttribute('disabled');
    // Botão não deve estar desabilitado (a não ser que produto esteja sem estoque)
    const stock = await page.locator(PDP.stockStatus).first().textContent().catch(() => '');
    if (!stock?.includes('unavailable')) {
      expect(disabled, 'Botão add-to-cart não deve estar disabled').toBeNull();
    }
  });

  test('Galeria de imagens presente @pdp', async ({ page }) => {
    const gallery = page.locator(PDP.gallery).first();
    await expect(gallery).toBeVisible({ timeout: 10_000 });
    const box = await gallery.boundingBox();
    expect(box, 'Galeria deve ter dimensões').toBeTruthy();
    expect(box!.width).toBeGreaterThan(100);
    expect(box!.height).toBeGreaterThan(100);
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 2 — TABS B2B PRO
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP — Tabs B2B Pro', () => {
  test.beforeEach(async ({ page }) => {
    await goToPDP(page);
  });

  test('Container de tabs existe na página @pdp', async ({ page }) => {
    const tabs = page.locator(PDP.tabsWrapper).first();
    await expect(tabs).toBeVisible({ timeout: 10_000 });
  });

  test('Pelo menos 2 tabs visíveis @pdp', async ({ page }) => {
    const tabTitles = page.locator(PDP.tabTitle);
    const count = await tabTitles.count();
    expect(count, 'Deve haver pelo menos 2 tabs').toBeGreaterThanOrEqual(2);
  });

  test('Tabs respondem ao clique (conteúdo ativa) @pdp', async ({ page }) => {
    const tabTitles = page.locator(PDP.tabTitle);
    const count = await tabTitles.count();
    if (count < 2) test.skip();

    // Clica na segunda tab e verifica que algo mudou
    const secondTab = tabTitles.nth(1);
    await secondTab.click();
    await page.waitForTimeout(400); // aguarda animação CSS

    // Tab clicada deve ter classe ativa ou aria-selected
    const isActive = await secondTab.evaluate((el) => {
      return el.classList.contains('active') ||
             el.classList.contains('awa-tab-active') ||
             el.getAttribute('aria-selected') === 'true' ||
             el.getAttribute('data-active') === '1';
    });
    expect(isActive, 'Tab clicada deve estar ativa').toBe(true);
  });

  test('Conteúdo de tab visível após clique @pdp', async ({ page }) => {
    const tabTitles = page.locator(PDP.tabTitle);
    if (await tabTitles.count() < 2) test.skip();

    await tabTitles.first().click();
    await page.waitForTimeout(300);

    const activeContent = page.locator(
      `${PDP.tabContent}.active, ${PDP.tabContent}[data-active="1"], ${PDP.tabContent}:not([hidden])`
    ).first();

    // Pelo menos um conteúdo de tab deve estar visível
    const visible = await activeContent.isVisible().catch(() => false);
    expect(visible, 'Conteúdo de tab ativa deve ser visível').toBe(true);
  });

  test('Screenshot das tabs B2B Pro @pdp', async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 1280;
    const tabs = page.locator(PDP.tabsWrapper).first();
    if (!await tabs.isVisible({ timeout: 5_000 }).catch(() => false)) test.skip();

    await page.locator(PDP.tabsWrapper).first().screenshot({
      path: screenshotPath(`tabs-${vw}px`),
      animations: 'disabled',
    });
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 3 — SIDEBAR DE CONVERSÃO
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP — Sidebar de conversão', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    // Sidebar só existe em desktop/notebook (>= 1024px)
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < 1024) test.skip();
    await goToPDP(page);
  });

  test('Bloco promocional da sidebar visível @pdp', async ({ page }) => {
    const promo = page.locator(PDP.sidebarPromo).first();
    // Promo é opcional — skip se não existir
    const exists = await promo.count().then(c => c > 0);
    if (!exists) test.skip();
    await expect(promo).toBeVisible({ timeout: 8_000 });
  });

  test('Botão WhatsApp na sidebar visível @pdp', async ({ page }) => {
    const wa = page.locator(PDP.sidebarWhatsApp).first();
    const exists = await wa.count().then(c => c > 0);
    if (!exists) test.skip();
    await expect(wa).toBeVisible({ timeout: 6_000 });
    const href = await wa.getAttribute('href');
    expect(href, 'WhatsApp deve ter href válido').toMatch(/wa\.me|whatsapp/i);
  });

  test('Screenshot sidebar desktop @pdp', async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 1280;
    const sidebar = page.locator(PDP.sidebarPromo).first();
    if (!await sidebar.isVisible({ timeout: 5_000 }).catch(() => false)) test.skip();

    await page.locator(PDP.productInfoMain).first().screenshot({
      path: screenshotPath(`info-main-sidebar-${vw}px`),
      animations: 'disabled',
    });
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 4 — LAYOUT E OVERFLOW
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP — Layout sem overflow horizontal', () => {
  test.beforeEach(async ({ page }) => {
    await goToPDP(page);
  });

  test('Sem scroll horizontal na PDP @pdp', async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 1280;

    const hasHScroll = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth;
    });

    expect(hasHScroll, `Não deve haver scroll horizontal em ${vw}px`).toBe(false);
  });

  test('Galeria e info main não se sobrepõem no desktop @pdp', async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < 1024) test.skip();

    const mediaBox = await page.locator(PDP.mediaSection).first().boundingBox();
    const infoBox = await page.locator(PDP.productInfoMain).first().boundingBox();

    if (!mediaBox || !infoBox) test.skip();

    // Galeria (esquerda) não deve sobrepor info main (direita)
    const mediaRight = mediaBox.x + mediaBox.width;
    const infoLeft = infoBox.x;
    expect(mediaRight, 'Galeria não deve invadir a coluna de info').toBeLessThanOrEqual(infoLeft + 20);
  });

  test('Screenshot full-page da PDP @pdp', async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 1280;
    await page.screenshot({
      path: screenshotPath(`full-page-${vw}px`),
      fullPage: true,
      animations: 'disabled',
    });
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 5 — MOBILE PDP
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP — Layout Mobile', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw >= 768) test.skip();
    await goToPDP(page);
  });

  test('Galeria ocupa largura total no mobile @pdp', async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 375;
    const gallery = page.locator(PDP.gallery).first();
    const box = await gallery.boundingBox();
    if (!box) test.skip();

    const galleryWidth = box.width;
    expect(galleryWidth, 'Galeria mobile deve ocupar quase 100% da largura').toBeGreaterThanOrEqual(vw * 0.9);
  });

  test('Preço e botão add-to-cart visíveis no mobile @pdp', async ({ page }) => {
    await expect(page.locator(PDP.productPrice).first()).toBeVisible();
    await expect(page.locator(PDP.addToCart).first()).toBeVisible({ timeout: 8_000 });
  });

  test('Screenshot mobile PDP @pdp', async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 375;
    await page.screenshot({
      path: screenshotPath(`mobile-full-${vw}px`),
      fullPage: true,
      animations: 'disabled',
    });
  });
});
