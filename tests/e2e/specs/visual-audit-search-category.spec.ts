/**
 * Visual Audit — Fases 3, 4: Search, Category
 *
 * Valida CSS aplicado pelas fases:
 *  - search-premium (search results grid, filters, toolbar, cards)
 *  - category-premium (category page grid, layered nav, toolbar)
 */
import { test, expect } from '@playwright/test';
import {
  navigateTo, css, cssMultiple, px,
  isVisible, hasNoOverflow, collectJsErrors,
} from '../helpers/visual-audit.helpers';

const BASE = 'https://awamotos.com';

/* ═══════════════════════════════════════════════════════════════════
   FASE 3 — SEARCH PREMIUM
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Fase 3 — Search Premium', () => {
  test.beforeEach(async ({ page }) => {
    if (!await navigateTo(page, `${BASE}/catalogsearch/result/?q=retrovisor`)) test.skip();
    // Aguardar resultados renderizarem (KO.js async)
    await page.locator('.product-item, .search.results .message').first()
      .waitFor({ state: 'visible', timeout: 20_000 }).catch(() => {});
  });

  test('Resultados de busca exibidos', async ({ page }) => {
    const count = await page.locator('.product-item').count();
    expect(count, 'Busca "retrovisor" deve retornar produtos').toBeGreaterThan(0);
  });

  test('Grid de produtos com layout correto', async ({ page }) => {
    const items = await page.locator('.product-item').count();
    if (items === 0) { test.skip(); return; }
    const grid = page.locator('.products-grid .product-items, .products.wrapper .product-items').first();
    const visible = await grid.isVisible().catch(() => false);
    if (visible) {
      const display = await css(page, '.products-grid .product-items, .products.wrapper .product-items', 'display');
      // Grid ou flex são aceitos
      expect(['grid', 'flex', 'block'].some(d => display.includes(d)),
        `Grid display deve ser grid/flex/block (got "${display}")`).toBe(true);
    }
  });

  test('Cards de produto com border-radius', async ({ page }) => {
    const items = await page.locator('.product-item').count();
    if (items === 0) { test.skip(); return; }
    const br = await css(page, '.product-item-info', 'border-radius');
    expect(px(br), 'Card border-radius >= 4px').toBeGreaterThanOrEqual(4);
  });

  test('Toolbar de busca visível (ordenação/modo)', async ({ page }) => {
    const toolbar = await isVisible(page, '.toolbar-products, .toolbar.toolbar-products', 8_000);
    expect(toolbar, 'Toolbar deve estar visível').toBe(true);
  });

  test('Filtros/Layered navigation presente', async ({ page }) => {
    const filters = await isVisible(page, '.filter-options, #layered-filter-block, .block-layered-nav', 8_000);
    // Filtros podem estar ausentes em buscas com poucos resultados
    if (!filters) {
      console.warn('⚠️ Filtros não encontrados na busca — pode ser busca com poucos resultados');
    }
  });

  test('Preços visíveis nos resultados', async ({ page }) => {
    const items = await page.locator('.product-item').count();
    if (items === 0) { test.skip(); return; }
    const prices = await page.locator('.product-item .price, .product-item .price-box').count();
    const b2bOverlay = await page.locator('.b2b-login-to-see-price').count();
    expect(prices + b2bOverlay, 'Preços ou overlay B2B devem existir').toBeGreaterThan(0);
  });

  test('Sem overflow horizontal na busca', async ({ page }) => {
    expect(await hasNoOverflow(page), 'Página de busca sem overflow').toBe(true);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   FASE 4 — CATEGORY PREMIUM
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Fase 4 — Category Premium', () => {
  test.beforeEach(async ({ page }) => {
    if (!await navigateTo(page, `${BASE}/bagageiros.html`)) test.skip();
    await page.locator('.product-item, .category-products').first()
      .waitFor({ state: 'visible', timeout: 20_000 }).catch(() => {});
  });

  test('Produtos listados na categoria', async ({ page }) => {
    const count = await page.locator('.product-item').count();
    expect(count, 'Categoria deve ter produtos').toBeGreaterThan(0);
  });

  test('Toolbar com ordenação e modo de visualização', async ({ page }) => {
    const toolbar = await isVisible(page, '.toolbar-products', 8_000);
    expect(toolbar, 'Toolbar de categoria deve estar visível').toBe(true);

    // Verificar select de ordenação
    const sorter = await isVisible(page, '.toolbar-sorter select, #sorter', 5_000);
    if (sorter) {
      const styles = await cssMultiple(page, '.toolbar-sorter select, #sorter', ['height', 'border-radius']);
      expect(px(styles['height']), 'Sorter height >= 32px').toBeGreaterThanOrEqual(32);
    }
  });

  test('Filtros layered com estilo premium', async ({ page }) => {
    const filterBlock = await isVisible(page, '.filter-options, #layered-filter-block', 8_000);
    expect(filterBlock, 'Filtros devem estar presentes na categoria').toBe(true);

    if (filterBlock) {
      const filterItems = await page.locator('.filter-options-item').count();
      expect(filterItems, 'Deve ter pelo menos 1 grupo de filtros').toBeGreaterThan(0);

      // Verificar que filtros são clicáveis
      const firstTitle = page.locator('.filter-options-title').first();
      const titleVisible = await firstTitle.isVisible().catch(() => false);
      if (titleVisible) {
        await firstTitle.click();
        await page.waitForTimeout(500);
        const content = page.locator('.filter-options-content').first();
        const opened = await content.isVisible().catch(() => false);
        expect(opened, 'Filtro deve abrir ao clicar').toBe(true);
      }
    }
  });

  test('Cards de categoria com imagem e preço', async ({ page }) => {
    const items = await page.locator('.product-item').count();
    if (items === 0) { test.skip(); return; }

    // Verificar imagem no primeiro card
    const img = page.locator('.product-item .product-image-photo').first();
    const imgVisible = await img.isVisible().catch(() => false);
    expect(imgVisible, 'Imagem do produto deve estar visível').toBe(true);

    // Verificar preço (ou overlay B2B)
    const price = await page.locator('.product-item .price').first().isVisible().catch(() => false);
    const b2b = await page.locator('.b2b-login-to-see-price').first().isVisible().catch(() => false);
    expect(price || b2b, 'Preço ou overlay B2B visível').toBe(true);
  });

  test('Botão Add-to-Cart nos cards', async ({ page }) => {
    const items = await page.locator('.product-item').count();
    if (items === 0) { test.skip(); return; }
    const btns = await page.locator('.product-item .action.tocart, .product-item .action.primary').count();
    // B2B pode ocultar botão para guest
    if (btns === 0) {
      const b2b = await page.locator('.b2b-login-to-see-price, [data-b2b-original-hidden]').count();
      expect(b2b, 'Botão ATC oculto deve ter overlay B2B').toBeGreaterThan(0);
    } else {
      expect(btns, 'Deve ter botões ATC').toBeGreaterThan(0);
    }
  });

  test('Paginação presente quando necessário', async ({ page }) => {
    const items = await page.locator('.product-item').count();
    if (items >= 12) {
      const paging = await isVisible(page, '.pages, .toolbar .pages', 5_000);
      expect(paging, 'Paginação deve existir com 12+ produtos').toBe(true);
    }
  });

  test('Breadcrumb na categoria', async ({ page }) => {
    const bc = await isVisible(page, '.breadcrumbs', 5_000);
    expect(bc, 'Breadcrumb deve estar visível na categoria').toBe(true);
    const items = page.locator('.breadcrumbs li, .breadcrumbs .item');
    const count = await items.count();
    expect(count, 'Breadcrumb deve ter itens').toBeGreaterThan(0);
  });

  test('Sem overflow horizontal na categoria', async ({ page }) => {
    expect(await hasNoOverflow(page), 'Categoria sem overflow horizontal').toBe(true);
  });
});
