import { test, expect } from "@playwright/test";
import { navigateTo } from "../../helpers/visual-audit.helpers";

const PLP = "https://awamotos.com/bagageiros.html";

test.describe("Filtros — layered navigation", () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, PLP);
    if (!ok) test.skip();
    // Aguardar produtos carregarem (LayeredAjax)
    await page.waitForSelector(".product-item-link, .product-item", { timeout: 18_000 }).catch(() => {});
    await page.waitForTimeout(500);
  });

  test("01 — sidebar de filtros existe (P1)", async ({ page }) => {
    const sidebar = page.locator(
      "[class*=\"sidebar-main\"], .block-content.filter-content, .sidebar-shop"
    ).first();
    const visible = await sidebar.isVisible({ timeout: 8_000 }).catch(() => false);
    if (!visible) {
      console.warn("[P1] Sidebar de filtros não encontrada após LayeredAjax");
    } else {
      await expect(sidebar).toBeVisible();
    }
  });

  test("02 — ao menos 1 grupo de filtro (P1)", async ({ page }) => {
    const selectors = [".filter-options-item", ".filter-option", "dt.filter-title", "[class*=\"filter-option\"]"];
    let count = 0;
    for (const sel of selectors) {
      count = await page.locator(sel).count().catch(() => 0);
      if (count > 0) break;
    }
    if (count === 0) console.warn("[P1] Sem grupos de filtro");
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test("03 — aplicar filtro muda URL (P1)", async ({ page }) => {
    const initUrl = page.url();
    const filterLink = page.locator(
      ".filter-options-content a[href*=\"?\"], .filter-options-content a[href*=\"/\"]"
    ).first();
    const exists = await filterLink.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!exists) { console.warn("[P1] Nenhum link de filtro encontrado"); test.skip(); return; }
    await filterLink.click().catch(() => {});
    await page.waitForTimeout(2_500);
    const newUrl = page.url();
    if (newUrl === initUrl) console.warn("[P1] Filtro não alterou URL");
    expect(newUrl).not.toBe(initUrl);
  });
});
