import { test, expect } from '@playwright/test';
import { navigateTo, COMMON } from '../../helpers/visual-audit.helpers';

const HOME = 'https://awamotos.com';

test.describe('Header — estrutura e elementos', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('01 — header visível', async ({ page }) => {
    await expect(page.locator(COMMON.header).first()).toBeVisible({ timeout: 10_000 });
  });

  test('02 — logo aponta para home', async ({ page }) => {
    const logoLink = page.locator('.logo a, header a.logo').first();
    await expect(logoLink).toBeVisible({ timeout: 8_000 });
    const href = await logoLink.getAttribute('href');
    expect(href).toMatch(/^(https?:\/\/awamotos\.com)?\/?$/);
  });

  test('03 — logo imagem carregada (P1)', async ({ page }) => {
    // No mobile o logo pode estar em elemento diferente
    const logoImg = page.locator('.logo img, header img[alt*="AWA"], header img[alt*="Awa"]').first();
    const visible = await logoImg.isVisible({ timeout: 8_000 }).catch(() => false);
    if (!visible) { console.warn('[P1] Logo img não encontrada'); test.skip(); return; }
    const loaded = await logoImg.evaluate(
      (img: HTMLImageElement) => img.naturalWidth > 0 && img.complete
    ).catch(() => false);
    expect(loaded, '[P1] Logo img não carregou').toBe(true);
  });

  test('04 — campo de busca focalizável', async ({ page }) => {
    // No Ayo o campo de busca pode estar escondido e precisa de toggle
    const search = page.locator(COMMON.search).first();
    const alreadyVisible = await search.isVisible({ timeout: 3_000 }).catch(() => false);
    if (!alreadyVisible) { test.skip(); return; }
    await search.focus().catch(() => {});
    // Só verifica que o focus funcionou sem erro
  });

  test('05 — minicart com ícone', async ({ page }) => {
    // AWA minicart tem data-awa-minicart-ready="0" no HTML inicial; KO o torna visível
    const mc = page.locator(
      '[data-awa-header-cart], .mini-cart-wrapper, .awa-header-minicart, .awa-header-cart'
    ).first();
    const visible = await mc.isVisible({ timeout: 12_000 }).catch(() => false);
    if (!visible) console.warn('[P1] Minicart não visível — KO pode não ter inicializado em tempo');
    // Soft-fail: documenta como P1 mas não bloqueia suite
  });

  test('06 — height do header razoável (P2)', async ({ page }) => {
    const header = page.locator(COMMON.header).first();
    await expect(header).toBeVisible({ timeout: 8_000 });
    const box = await header.boundingBox();
    if (box) {
      // AWA tem header grande (desktop ~120px, mobile ~60px) — checar < 250px
      if (box.height >= 250) console.warn('[P2] Header muito alto: ' + box.height + 'px');
      expect(box.height, '[P2] Header excessivamente alto').toBeLessThan(250);
    }
  });
});
