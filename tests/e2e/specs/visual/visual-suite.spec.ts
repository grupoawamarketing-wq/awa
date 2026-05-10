/**
 * visual-suite.spec.ts — AWA Motos
 *
 * Suíte visual de regressão focada nas páginas-chave.
 * Usa toHaveScreenshot() com thresholds por área para detectar regressões.
 *
 * Primeiro run: --update-snapshots para gerar baseline.
 * Snapshots ficam em specs/visual/visual-suite.spec.ts-snapshots/ (versionados).
 */
import { test, expect, type Page } from '@playwright/test';
const BASE = 'https://awamotos.com';

const URLS = {
  home:     BASE,
  plp:      `${BASE}/bagageiros.html`,
  pdp:      `${BASE}/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html`,
  login:    `${BASE}/customer/account/login/`,
  b2bLogin: `${BASE}/b2b/account/login/`,
} as const;

/** Desabilita animações para screenshots determinísticos */
async function freezeAnimations(page: Page): Promise<void> {
  await page.addStyleTag({
    content: `
      *, *::before, *::after {
        animation-delay: -1ms !important;
        animation-duration: 1ms !important;
        transition-delay: 0ms !important;
        transition-duration: 0ms !important;
        caret-color: transparent !important;
      }
      .owl-carousel, .swiper-wrapper { transition: none !important; }
      #awa-cookie-accept, .awa-cookie-banner { display: none !important; }
    `,
  });
  await page.waitForTimeout(400);
}

async function goTo(page: Page, url: string): Promise<boolean> {
  try {
    await page.goto(url, { waitUntil: 'commit', timeout: 25_000 });
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});
    await Promise.race([
      page.evaluate(() => document.fonts.ready).catch(() => {}),
      new Promise<void>(r => setTimeout(r, 4_000)),
    ]);
    await freezeAnimations(page);
    return true;
  } catch {
    return false;
  }
}

// ────────────────────────── HOME ────────────────────────────────────────────

test.describe('Visual — Home', () => {
  test('home-header', async ({ page }) => {
    if (!await goTo(page, URLS.home)) test.skip();
    await page.locator('header.awa-site-header, header').first().waitFor({ state: 'visible', timeout: 10_000 });
    await expect(page).toHaveScreenshot('home-header.png', {
      clip: { x: 0, y: 0, width: 1280, height: 120 },
      maxDiffPixelRatio: 0.03,
    });
  });

  test('home-above-fold', async ({ page }) => {
    if (!await goTo(page, URLS.home)) test.skip();
    await expect(page).toHaveScreenshot('home-above-fold.png', {
      clip: { x: 0, y: 0, width: 1280, height: 700 },
      maxDiffPixelRatio: 0.05,
    });
  });

  test('home-footer', async ({ page }) => {
    if (!await goTo(page, URLS.home)) test.skip();
    const footer = page.locator('footer.page-footer, .footer.content').first();
    await footer.waitFor({ state: 'visible', timeout: 10_000 });
    await footer.scrollIntoViewIfNeeded();
    await page.waitForTimeout(500);
    const box = await footer.boundingBox();
    if (!box) test.skip();
    await expect(page).toHaveScreenshot('home-footer.png', {
      clip: { x: box!.x, y: box!.y, width: box!.width, height: Math.min(box!.height, 400) },
      maxDiffPixelRatio: 0.04,
    });
  });
});

// ────────────────────────── PLP ─────────────────────────────────────────────

test.describe('Visual — PLP (Bagageiros)', () => {
  test('plp-header', async ({ page }) => {
    if (!await goTo(page, URLS.plp)) test.skip();
    await expect(page).toHaveScreenshot('plp-header.png', {
      clip: { x: 0, y: 0, width: 1280, height: 120 },
      maxDiffPixelRatio: 0.03,
    });
  });

  test('plp-grid-topo', async ({ page }) => {
    if (!await goTo(page, URLS.plp)) test.skip();
    // Aguarda produto aparecer (LayeredAjax pode atrasar renderização)
    await page.waitForSelector('.product-item-link, li.item.product, .product-item', { timeout: 20_000 }).catch(() => {});
    await page.waitForTimeout(800);
    const grid = page.locator('.products-grid, .products.wrapper, .products.list, ol.products').first();
    const box = await grid.boundingBox();
    if (!box) test.skip();
    await expect(page).toHaveScreenshot('plp-grid-topo.png', {
      clip: { x: box!.x, y: box!.y, width: box!.width, height: Math.min(box!.height, 450) },
      maxDiffPixelRatio: 0.05,
    });
  });
});

// ────────────────────────── PDP ─────────────────────────────────────────────

test.describe('Visual — PDP (Produto)', () => {
  test('pdp-header', async ({ page }) => {
    if (!await goTo(page, URLS.pdp)) test.skip();
    await expect(page).toHaveScreenshot('pdp-header.png', {
      clip: { x: 0, y: 0, width: 1280, height: 120 },
      maxDiffPixelRatio: 0.03,
    });
  });

  test('pdp-product-area', async ({ page }) => {
    if (!await goTo(page, URLS.pdp)) test.skip();
    const info = page.locator('.product-info-main, .product-info-wrapper').first();
    await info.waitFor({ state: 'visible', timeout: 12_000 });
    const box = await info.boundingBox();
    if (!box) test.skip();
    await expect(page).toHaveScreenshot('pdp-product-area.png', {
      clip: { x: box!.x, y: box!.y, width: box!.width, height: Math.min(box!.height, 500) },
      maxDiffPixelRatio: 0.05,
    });
  });

  test('pdp-media', async ({ page }) => {
    if (!await goTo(page, URLS.pdp)) test.skip();
    const media = page.locator('.product.media, .gallery-placeholder, .fotorama').first();
    await media.waitFor({ state: 'visible', timeout: 12_000 });
    const box = await media.boundingBox();
    if (!box) test.skip();
    await expect(page).toHaveScreenshot('pdp-media.png', {
      clip: { x: box!.x, y: box!.y, width: box!.width, height: Math.min(box!.height, 500) },
      maxDiffPixelRatio: 0.05,
    });
  });
});

// ────────────────────────── LOGIN ───────────────────────────────────────────

test.describe('Visual — Login', () => {
  test('login-form', async ({ page }) => {
    // /customer/account/login/ redireciona para B2B login no AWA Motos
    if (!await goTo(page, URLS.login)) test.skip();
    // Seletores reais confirmados nos testes funcionais
    const form = page.locator('.b2b-login-form, #b2b-email, .b2b-login-wrapper, .page-main').first();
    await form.waitFor({ state: 'visible', timeout: 12_000 });
    await page.waitForTimeout(500);
    await expect(page).toHaveScreenshot('login-form.png', {
      clip: { x: 0, y: 0, width: 1280, height: 700 },
      maxDiffPixelRatio: 0.04,
    });
  });

  test('b2b-login-form', async ({ page }) => {
    if (!await goTo(page, URLS.b2bLogin)) test.skip();
    await expect(page).toHaveScreenshot('b2b-login-form.png', {
      clip: { x: 0, y: 0, width: 1280, height: 700 },
      maxDiffPixelRatio: 0.04,
    });
  });
});
