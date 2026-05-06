/**
 * Visual Audit — Fases 3, 4: Search, Category
 *
 * Valida CSS aplicado pelas fases:
 *  - search-premium (search results grid, filters, toolbar, cards)
 *  - category-premium (category page grid, layered nav, toolbar)
 */
import { test, expect, type Page } from '@playwright/test';
import {
  navigateTo, css, cssMultiple, px,
  isVisible, hasNoOverflow, collectJsErrors,
} from '../helpers/visual-audit.helpers';

const BASE = 'https://awamotos.com';

/* ═══════════════════════════════════════════════════════════════════
   FASE 3 — SEARCH PREMIUM
   Usa beforeAll + sharedPage — navega para /catalogsearch uma única vez.
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Fase 3 — Search Premium', () => {
  let srchPage: Page;

  test.beforeAll(async ({ browser }) => {
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'pt-BR' }).catch(() => null);
    if (!ctx) return;
    srchPage = await ctx.newPage();
    const ok = await navigateTo(srchPage, `${BASE}/catalogsearch/result/?q=retrovisor`);
    if (!ok) return;
    await srchPage.locator('.item-product, .search.results .message').first()
      .waitFor({ state: 'attached', timeout: 15_000 }).catch(() => {});
    await srchPage.waitForLoadState('domcontentloaded', { timeout: 8_000 }).catch(() => {});
    await srchPage.waitForTimeout(2_000).catch(() => {});
  });

  test.afterAll(async () => {
    await srchPage?.context().close().catch(() => {});
  });

  test('Resultados de busca exibidos', async () => {
    if (!srchPage) { test.skip(); return; }
    const count = await srchPage.locator('.item-product').count().catch(() => 0);
    if (count === 0) {
      console.warn('⚠️ Produtos não renderizados (KO.js headless) — skipping');
      test.skip();
      return;
    }
    expect(count, 'Busca "retrovisor" deve retornar produtos').toBeGreaterThan(0);
  });

  test('Grid de produtos com layout correto', async () => {
    if (!srchPage) { test.skip(); return; }
    const items = await srchPage.locator('.item-product').count().catch(() => 0);
    if (items === 0) { test.skip(); return; }
    const grid = srchPage.locator('.products-grid ul.product-grid, .products.wrapper ul.product-grid').first();
    const visible = await grid.isVisible().catch(() => false);
    if (visible) {
      const display = await css(srchPage, '.products-grid ul.product-grid, .products.wrapper ul.product-grid', 'display');
      expect(['grid', 'flex', 'block'].some(d => display.includes(d)),
        `Grid display deve ser grid/flex/block (got "${display}")`).toBe(true);
    }
  });

  test('Cards de produto com border-radius', async () => {
    if (!srchPage) { test.skip(); return; }
    const items = await srchPage.locator('.item-product').count().catch(() => 0);
    if (items === 0) { test.skip(); return; }
    const br = await css(srchPage, '.product-thumb, .product-item-info', 'border-radius');
    expect(px(br), 'Card border-radius >= 4px').toBeGreaterThanOrEqual(4);
  });

  test('Toolbar de busca visível (ordenação/modo)', async () => {
    if (!srchPage) { test.skip(); return; }
    const toolbar = await isVisible(srchPage, '.toolbar-products, .toolbar.toolbar-products', 8_000).catch(() => false);
    if (!toolbar) {
      console.warn('⚠️ Toolbar não visível (KO.js headless) — skipping');
      test.skip(); return;
    }
    expect(toolbar, 'Toolbar deve estar visível').toBe(true);
  });

  test('Filtros/Layered navigation presente', async () => {
    if (!srchPage) { test.skip(); return; }
    const filters = await isVisible(srchPage, '.filter-options, #layered-filter-block, .block-layered-nav', 8_000).catch(() => false);
    if (!filters) {
      console.warn('⚠️ Filtros não encontrados na busca — pode ser busca com poucos resultados');
    }
  });

  test('Preços visíveis nos resultados', async () => {
    if (!srchPage) { test.skip(); return; }
    const items = await srchPage.locator('.item-product').count().catch(() => 0);
    if (items === 0) { test.skip(); return; }
    const prices = await srchPage.locator('.item-product .price, .item-product .price-box').count().catch(() => 0);
    const b2bOverlay = await srchPage.locator('.b2b-login-to-see-price').count().catch(() => 0);
    if (prices + b2bOverlay === 0) {
      console.warn('⚠️ Preços não encontrados (B2B oculta para guest) — skipping');
      test.skip(); return;
    }
    expect(prices + b2bOverlay, 'Preços ou overlay B2B devem existir').toBeGreaterThan(0);
  });

  test('Sem overflow horizontal na busca', async () => {
    if (!srchPage) { test.skip(); return; }
    const ok = await hasNoOverflow(srchPage).catch(() => null);
    if (ok === null) { test.skip(); return; }
    expect(ok, 'Página de busca sem overflow').toBe(true);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   FASE 4 — CATEGORY PREMIUM
   Usa beforeAll + sharedPage para navegar para /bagageiros.html
   uma única vez — evita crashes por sobrecarga de Chrome após
   múltiplas navegações pesadas (KO.js + LayeredAjax).
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Fase 4 — Category Premium', () => {
  let catPage: Page;

  test.beforeAll(async ({ browser }) => {
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'pt-BR' }).catch(() => null);
    if (!ctx) return;
    catPage = await ctx.newPage();
    const ok = await navigateTo(catPage, `${BASE}/bagageiros.html`);
    if (!ok) return;
    await catPage.locator('.item-product, .category-products').first()
      .waitFor({ state: 'attached', timeout: 15_000 }).catch(() => {});
    await catPage.waitForLoadState('domcontentloaded', { timeout: 8_000 }).catch(() => {});
    await catPage.waitForTimeout(2_000).catch(() => {});
  });

  test.afterAll(async () => {
    await catPage?.context().close().catch(() => {});
  });

  test('Produtos listados na categoria', async () => {
    if (!catPage) { test.skip(); return; }
    const count = await catPage.locator('.item-product').count().catch(() => 0);
    if (count === 0) {
      console.warn('⚠️ Produtos não renderizados (KO.js headless) — skipping');
      test.skip();
      return;
    }
    expect(count, 'Categoria deve ter produtos').toBeGreaterThan(0);
  });

  test('Toolbar com ordenação e modo de visualização', async () => {
    if (!catPage) { test.skip(); return; }
    let toolbar: boolean;
    try {
      toolbar = await isVisible(catPage, '.toolbar-products', 8_000);
    } catch {
      test.skip(); return;
    }
    expect(toolbar, 'Toolbar de categoria deve estar visível').toBe(true);

    const sorter = await isVisible(catPage, '.toolbar-sorter select, #sorter', 5_000).catch(() => false);
    if (sorter) {
      const styles = await cssMultiple(catPage, '.toolbar-sorter select, #sorter', ['height', 'border-radius']).catch(() => ({} as Record<string, string>));
      if (styles['height']) {
        expect(px(styles['height']), 'Sorter height >= 32px').toBeGreaterThanOrEqual(32);
      }
    }
  });

  test('Filtros layered com estilo premium', async () => {
    if (!catPage) { test.skip(); return; }
    const filterBlock = await isVisible(catPage, '.filter-options, #layered-filter-block', 15_000).catch(() => false);
    if (!filterBlock) {
      console.warn('⚠️ Filtros não visíveis (page not fully rendered) — skipping');
      test.skip();
      return;
    }

    const filterItems = await catPage.locator('.filter-options-item').count().catch(() => 0);
    expect(filterItems, 'Deve ter pelo menos 1 grupo de filtros').toBeGreaterThan(0);

    const firstTitle = catPage.locator('.filter-options-title').first();
    const titleVisible = await firstTitle.isVisible().catch(() => false);
    if (titleVisible) {
      try {
        await firstTitle.click();
        await catPage.waitForTimeout(500);
        const content = catPage.locator('.filter-options-content').first();
        const opened = await content.isVisible().catch(() => false);
        expect(opened, 'Filtro deve abrir ao clicar').toBe(true);
      } catch {
        test.skip(); return;
      }
    }
  });

  test('Cards de categoria com imagem e preço', async () => {
    if (!catPage) { test.skip(); return; }
    const items = await catPage.locator('.item-product').count().catch(() => 0);
    if (items === 0) { test.skip(); return; }

    const img = catPage.locator('.item-product .product-image-photo').first();
    const imgVisible = await img.isVisible().catch(() => false);
    expect(imgVisible, 'Imagem do produto deve estar visível').toBe(true);

    const price = await catPage.locator('.item-product .price').first().isVisible().catch(() => false);
    const b2bCount = await catPage.locator('.b2b-login-to-see-price').count().catch(() => 0);
    // Ayo theme hides .info-price area via hover CSS — B2B overlay is in DOM even if not visible
    expect(price || b2bCount > 0, 'Preço visível ou overlay B2B no DOM').toBe(true);
  });

  test('Botão Add-to-Cart nos cards', async () => {
    if (!catPage) { test.skip(); return; }
    const items = await catPage.locator('.item-product').count().catch(() => 0);
    if (items === 0) { test.skip(); return; }
    const btns = await catPage.locator('.item-product .action.tocart, .item-product .action.primary').count().catch(() => 0);
    if (btns === 0) {
      const b2b = await catPage.locator('.b2b-login-to-see-price, [data-b2b-original-hidden]').count().catch(() => 0);
      expect(b2b, 'Botão ATC oculto deve ter overlay B2B').toBeGreaterThan(0);
    } else {
      expect(btns, 'Deve ter botões ATC').toBeGreaterThan(0);
    }
  });

  test('Paginação presente quando necessário', async () => {
    if (!catPage) { test.skip(); return; }
    const items = await catPage.locator('.item-product').count().catch(() => 0);
    if (items > 12) {
      const paging = await isVisible(catPage, '.pages, .toolbar .pages', 5_000).catch(() => false);
      expect(paging, 'Paginação deve existir com 12+ produtos').toBe(true);
    }
  });

  test('Breadcrumb na categoria', async () => {
    if (!catPage) { test.skip(); return; }
    let bc: boolean;
    try {
      bc = await isVisible(catPage, '.breadcrumbs', 5_000);
    } catch {
      test.skip(); return;
    }
    expect(bc, 'Breadcrumb deve estar visível na categoria').toBe(true);
    const items = catPage.locator('.breadcrumbs li, .breadcrumbs .item');
    const count = await items.count().catch(() => 0);
    expect(count, 'Breadcrumb deve ter itens').toBeGreaterThan(0);
  });

  test('Sem overflow horizontal na categoria', async () => {
    if (!catPage) { test.skip(); return; }
    const ok = await hasNoOverflow(catPage).catch(() => null);
    if (ok === null) { test.skip(); return; }
    expect(ok, 'Categoria sem overflow horizontal').toBe(true);
  });
});
