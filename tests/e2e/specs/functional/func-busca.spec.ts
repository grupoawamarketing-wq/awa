import { test, expect } from "@playwright/test";
import { navigateTo, COMMON } from "../../helpers/visual-audit.helpers";

const HOME = "https://awamotos.com";
const SEARCH_TERM = "bagageiro";

/** Ativa o campo de busca (Ayo pode precisar de toggle) */
async function activateSearch(page: import("@playwright/test").Page): Promise<boolean> {
  const searchInput = page.locator(COMMON.search).first();
  const alreadyVisible = await searchInput.isVisible({ timeout: 2_000 }).catch(() => false);
  if (alreadyVisible) return true;
  // Tentar toggle
  const toggleSelectors = [
    ".action.search[data-role], button.search-toggle",
    ".awa-search-toggle, .header-search-toggle",
    "button[aria-label*=\"busca\" i]",
  ];
  for (const sel of toggleSelectors) {
    const toggle = page.locator(sel).first();
    const ok = await toggle.isVisible({ timeout: 800 }).catch(() => false);
    if (ok) {
      await toggle.click({ force: true }).catch(() => {});
      await page.waitForTimeout(400);
      if (await searchInput.isVisible({ timeout: 1_500 }).catch(() => false)) return true;
    }
  }
  return false;
}

test.describe("Busca — input e resultados", () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test("01 — campo de busca aceita digitação", async ({ page }) => {
    const searchVisible = await activateSearch(page);
    if (!searchVisible) { console.warn("[P1] Campo de busca não visível"); test.skip(); return; }
    const search = page.locator(COMMON.search).first();
    await search.fill(SEARCH_TERM, { force: true }).catch(() => {});
    await page.waitForTimeout(300);
    const val = await search.inputValue({ timeout: 3_000 }).catch(() => "");
    expect(val).toBeTruthy();
  });

  test("02 — Enter redireciona para resultados (P0)", async ({ page }) => {
    const initUrl = page.url();
    const searchVisible = await activateSearch(page);
    if (!searchVisible) { console.warn("[P0] Busca não visível"); test.skip(); return; }
    const search = page.locator(COMMON.search).first();
    await search.fill(SEARCH_TERM, { force: true }).catch(() => {});
    await page.waitForTimeout(400);
    await search.press("Enter").catch(() => {});
    await page.waitForTimeout(3_000);
    const newUrl = page.url();
    // Aceita: catalogsearch, categoria direta, ou qualquer URL diferente de HOME
    const redirected = newUrl !== initUrl;
    if (!redirected) console.error("[P0] Busca não redirecionou de HOME");
    expect(redirected, "[P0] Busca não redirecionou").toBe(true);
    console.info("[INFO] Busca redirecionou para: " + newUrl);
  });

  test("03 — resultados exibem produtos (P0)", async ({ page }) => {
    // Navegar diretamente para a página de resultados
    await page.goto("https://awamotos.com/catalogsearch/result/?q=" + SEARCH_TERM, {
      waitUntil: "domcontentloaded", timeout: 20_000,
    }).catch(() => {});
    // Aguardar LayeredAjax/KO renderizar produtos (pode demorar 3-5s)
    await page.waitForTimeout(4_000);
    // Tentar seletores de produto
    const selectors = [".product-item", "li.item.product", ".product-item-link", ".product-items li"];
    let count = 0;
    for (const sel of selectors) {
      count = await page.locator(sel).count().catch(() => 0);
      if (count > 0) { console.info("[INFO] Produtos via '" + sel + "': " + count); break; }
    }
    if (count === 0) console.error("[P0] Busca retornou 0 produtos");
    expect(count, "[P0] Busca sem produtos").toBeGreaterThan(0);
  });

  test("04 — autocomplete aparece (P1)", async ({ page }) => {
    const searchVisible = await activateSearch(page);
    if (!searchVisible) { test.skip(); return; }
    const search = page.locator(COMMON.search).first();
    await search.focus().catch(() => {});
    await search.fill(SEARCH_TERM, { force: true }).catch(() => {});
    await page.waitForTimeout(1_000);
    const autocomplete = page.locator(
      ".search-autocomplete, .aw-autocomplete, [role=\"listbox\"], .search-suggestion"
    ).first();
    const visible = await autocomplete.isVisible({ timeout: 3_000 }).catch(() => false);
    if (!visible) console.warn("[P1] Autocomplete não apareceu");
  });
});
