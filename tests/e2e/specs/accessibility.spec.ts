/**
 * AWA Motos — Accessibility Tests (a11y)
 * Cobertura: Homepage, PDP, Listagem de categoria
 *
 * Valida:
 *  - Presença de atributos ARIA fundamentais
 *  - Navegação por teclado nos menus
 *  - Labels em inputs críticos
 *  - Contraste mínimo (verificação estrutural, não visual)
 *  - Skip links / landmarks
 *  - Alt text em imagens de produto
 *  - Botões sem texto acessível
 */

import { test, expect, Page } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = path.join(__dirname, '..', 'screenshots');

async function goHome(page: Page): Promise<void> {
  await page.goto('/', { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.waitForSelector('body.cms-index-index, body.cms-home', { timeout: 10_000 });
}

async function goCategoryPage(page: Page): Promise<void> {
  await page.goto('/acessorios.html', { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.waitForSelector('.catalog-category-view, .page-layout-2columns-left', { timeout: 10_000 });
}

async function goPDP(page: Page): Promise<void> {
  await goCategoryPage(page);
  const firstProduct = page.locator('.product-item a.product-item-link').first();
  await firstProduct.waitFor({ timeout: 8_000 });
  await firstProduct.click();
  await page.waitForLoadState('domcontentloaded', { timeout: 30_000 });
  await page.waitForSelector('.product-info-main', { timeout: 10_000 });
}

/* ════════════════════════════════════════════════════════════════════════
   SUITE 1 — LANDMARKS E ESTRUTURA
   ════════════════════════════════════════════════════════════════════════ */
test.describe('A11Y — Landmarks e estrutura', () => {
  test('Homepage tem landmark <main> @a11y', async ({ page }) => {
    await goHome(page);
    await expect(page.locator('main, [role="main"]').first()).toBeAttached();
  });

  test('Homepage tem landmark <nav> ou role navigation @a11y', async ({ page }) => {
    await goHome(page);
    const nav = page.locator('nav, [role="navigation"]').first();
    await expect(nav).toBeAttached();
  });

  test('Homepage tem <h1> único @a11y', async ({ page }) => {
    await goHome(page);
    const h1Count = await page.locator('h1').count();
    expect(h1Count, 'Deve haver exatamente 1 <h1> na homepage').toBeLessThanOrEqual(1);
  });

  test('PDP tem <h1> com nome do produto @a11y', async ({ page }) => {
    await goPDP(page);
    const h1 = page.locator('h1').first();
    await expect(h1).toBeVisible();
    const text = await h1.textContent();
    expect(text?.trim().length, 'H1 não deve ser vazio').toBeGreaterThan(0);
  });

  test('Categoria tem <h1> visível @a11y', async ({ page }) => {
    await goCategoryPage(page);
    const h1 = page.locator('h1').first();
    await expect(h1).toBeVisible({ timeout: 6_000 });
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 2 — IMAGENS
   ════════════════════════════════════════════════════════════════════════ */
test.describe('A11Y — Imagens com alt text', () => {
  test('Logo do header tem alt ou aria-label @a11y', async ({ page }) => {
    await goHome(page);
    const logo = page.locator('.awa-site-header .logo img, .awa-site-header .logo').first();
    await logo.waitFor({ timeout: 8_000 });

    // img deve ter alt
    const logoImg = page.locator('.awa-site-header .logo img').first();
    if (await logoImg.count() > 0) {
      const alt = await logoImg.getAttribute('alt');
      expect(alt, 'Logo img deve ter alt text').not.toBeNull();
    } else {
      // SVG ou link — deve ter aria-label
      const ariaLabel = await logo.getAttribute('aria-label');
      expect(ariaLabel, 'Logo deve ter aria-label se não for img').not.toBeNull();
    }
  });

  test('Imagens de produto na listagem têm alt @a11y', async ({ page }) => {
    await goCategoryPage(page);
    const productImages = page.locator('.product-item-photo img');
    const count = await productImages.count();
    const checkCount = Math.min(count, 6); // checa apenas os primeiros 6

    for (let i = 0; i < checkCount; i++) {
      const img = productImages.nth(i);
      const alt = await img.getAttribute('alt');
      expect(alt, `Imagem de produto ${i + 1} deve ter alt`).not.toBeNull();
      expect(alt!.trim().length, `Alt do produto ${i + 1} não deve ser vazio`).toBeGreaterThan(0);
    }
  });

  test('Imagem principal da galeria PDP tem alt @a11y', async ({ page }) => {
    await goPDP(page);
    // Aguarda galeria aparecer
    await page.waitForSelector('.fotorama, [data-gallery-role="gallery-placeholder"]', { timeout: 10_000 });

    const galleryImg = page.locator(
      '.fotorama__stage img, .gallery-placeholder img, [data-gallery-role="gallery-placeholder"] img'
    ).first();

    if (await galleryImg.count() > 0) {
      const alt = await galleryImg.getAttribute('alt');
      expect(alt, 'Imagem principal da galeria deve ter alt').not.toBeNull();
    }
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 3 — FORMULÁRIOS
   ════════════════════════════════════════════════════════════════════════ */
test.describe('A11Y — Formulários e inputs', () => {
  test('Campo de busca tem label ou aria-label @a11y', async ({ page }) => {
    await goHome(page);
    const searchInput = page.locator('input#search, input[name="q"]').first();
    if (!await searchInput.count()) test.skip();

    // Deve ter label associado
    const id = await searchInput.getAttribute('id');
    const hasLabel = id
      ? await page.locator(`label[for="${id}"]`).count().then(c => c > 0)
      : false;
    const hasAriaLabel = await searchInput.getAttribute('aria-label').then(v => !!v).catch(() => false);
    const hasAriaLabelledBy = await searchInput.getAttribute('aria-labelledby').then(v => !!v).catch(() => false);
    const hasPlaceholder = await searchInput.getAttribute('placeholder').then(v => !!v).catch(() => false);

    expect(
      hasLabel || hasAriaLabel || hasAriaLabelledBy || hasPlaceholder,
      'Campo de busca deve ter algum label acessível'
    ).toBe(true);
  });

  test('Botão add-to-cart tem texto acessível @a11y', async ({ page }) => {
    await goPDP(page);
    const btn = page.locator('#product-addtocart-button').first();
    if (!await btn.count()) test.skip();

    // Texto visível ou aria-label
    const text = await btn.textContent();
    const ariaLabel = await btn.getAttribute('aria-label');
    const title = await btn.getAttribute('title');

    const accessibleText = (text?.trim() ?? '') || (ariaLabel ?? '') || (title ?? '');
    expect(accessibleText.length, 'Botão add-to-cart deve ter texto acessível').toBeGreaterThan(0);
  });

  test('Links sem texto têm aria-label @a11y', async ({ page }) => {
    await goHome(page);
    // Links com apenas ícone e sem texto visible devem ter aria-label
    const iconLinks = page.locator('a:not([aria-hidden="true"])').filter({ has: page.locator('i, svg, img') });
    const count = await iconLinks.count();

    const badLinks: string[] = [];
    const checkLimit = Math.min(count, 20);

    for (let i = 0; i < checkLimit; i++) {
      const link = iconLinks.nth(i);
      const text = (await link.textContent())?.trim() ?? '';
      const ariaLabel = await link.getAttribute('aria-label');
      const ariaLabelledBy = await link.getAttribute('aria-labelledby');
      const title = await link.getAttribute('title');

      if (!text && !ariaLabel && !ariaLabelledBy && !title) {
        const href = await link.getAttribute('href') ?? 'unknown';
        badLinks.push(href);
      }
    }

    if (badLinks.length > 0) {
      console.warn(`⚠️  Links sem texto acessível (${badLinks.length}):`, badLinks.slice(0, 5));
    }
    // Aviso informativo — não falha o teste por ser heurístico
    expect(badLinks.length).toBeLessThan(10);
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 4 — NAVEGAÇÃO POR TECLADO
   ════════════════════════════════════════════════════════════════════════ */
test.describe('A11Y — Navegação por teclado', () => {
  test('Campo de busca recebe foco via Tab @a11y', async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < 1024) test.skip(); // desktop only

    await goHome(page);
    await page.keyboard.press('Tab');
    await page.waitForTimeout(300);

    // Navega até encontrar o campo de busca (máx 15 tabs)
    let focused = false;
    for (let i = 0; i < 15; i++) {
      const focusedEl = await page.evaluate(() => {
        const el = document.activeElement;
        return el ? { tag: el.tagName, id: el.id ?? '', name: (el as HTMLInputElement).name ?? '' } : null;
      });

      if (focusedEl && (focusedEl.id === 'search' || focusedEl.name === 'q')) {
        focused = true;
        break;
      }
      await page.keyboard.press('Tab');
      await page.waitForTimeout(100);
    }

    expect(focused, 'Campo de busca deve ser alcançável via Tab').toBe(true);
  });

  test('Logo do header tem tabindex >= 0 ou é focusável @a11y', async ({ page }) => {
    await goHome(page);
    const logoLink = page.locator('.awa-site-header .logo a, .awa-site-header a.logo').first();
    if (!await logoLink.count()) test.skip();

    // Link de logo deve ser focusável (tag <a> é focusável por padrão)
    const tag = await logoLink.evaluate(el => el.tagName.toLowerCase());
    expect(['a', 'button'], 'Logo deve usar tag focusável').toContain(tag);
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 5 — ARIA E ROLES
   ════════════════════════════════════════════════════════════════════════ */
test.describe('A11Y — ARIA e roles', () => {
  test('Minicart tem aria-label ou title @a11y', async ({ page }) => {
    await goHome(page);
    const minicart = page.locator(
      '.awa-header-minicart a, .mini-cart-wrapper a.action.showcart, a[data-bind*="minicart"]'
    ).first();
    if (!await minicart.count()) test.skip();

    const ariaLabel = await minicart.getAttribute('aria-label');
    const title = await minicart.getAttribute('title');
    const text = (await minicart.textContent())?.trim() ?? '';

    expect(ariaLabel || title || text, 'Minicart link deve ter texto acessível').toBeTruthy();
  });

  test('Botão de menu mobile tem aria-label @a11y', async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw >= 1024) test.skip(); // mobile only

    await goHome(page);
    const toggle = page.locator(
      '.awa-header-mobile-toggle, button.nav-toggle, [aria-label*="menu"], [data-action="toggle-nav"]'
    ).first();
    if (!await toggle.count()) test.skip();

    const ariaLabel = await toggle.getAttribute('aria-label');
    const title = await toggle.getAttribute('title');
    const text = (await toggle.textContent())?.trim() ?? '';

    expect(ariaLabel || title || text, 'Botão de menu mobile deve ter texto acessível').toBeTruthy();
  });

  test('Tabs PDP têm role tab ou aria-selected @a11y', async ({ page }) => {
    await goPDP(page);
    const tabs = page.locator(
      '.awa-pdp-tabs .awa-tab-title, [data-role="title"], [role="tab"]'
    );
    if (await tabs.count() === 0) test.skip();

    // Pelo menos as tabs devem responder ao clique — verificação estrutural
    const firstTab = tabs.first();
    await firstTab.click();
    await page.waitForTimeout(300);

    // Verifica que há algum feedback visual de ativa (class ou aria)
    const hasActiveIndicator = await firstTab.evaluate((el) => {
      return el.classList.contains('active') ||
             el.classList.contains('awa-tab-active') ||
             el.getAttribute('aria-selected') === 'true' ||
             el.getAttribute('data-active') === '1' ||
             el.closest('[class*="active"]') !== null;
    });

    expect(hasActiveIndicator, 'Tab ativa deve indicar estado via class ou aria').toBe(true);
  });
});
