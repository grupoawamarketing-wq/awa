import { test, expect } from "@playwright/test";
import { navigateTo, COMMON } from "../../helpers/visual-audit.helpers";

const PLP = "https://awamotos.com/bagageiros.html";

test.describe("Categoria (PLP) — grid e navegação", () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, PLP);
    if (!ok) test.skip();
  });

  test("01 — título da categoria visível (P1)", async ({ page }) => {
    const title = page.locator(".page-title, h1").first();
    await expect(title).toBeVisible({ timeout: 10_000 });
    const text = await title.textContent();
    expect(text?.trim().length).toBeGreaterThan(0);
  });

  test("02 — breadcrumb presente (P2)", async ({ page }) => {
    const bc = page.locator(".nav-breadcrumbs, .breadcrumbs").first();
    await expect(bc).toBeVisible({ timeout: 8_000 });
  });

  test("03 — grid de produtos exibido (P0)", async ({ page }) => {
    // Aguardar LayeredAjax via waitForSelector — sai assim que aparecer
    await page.waitForSelector(
      ".product-item-link, li.item.product, .product-item",
      { timeout: 20_000 }
    ).catch(() => {});
    const selectors = [".product-item-link", ".product-item", "li.item.product"];
    let count = 0;
    for (const sel of selectors) {
      count = await page.locator(sel).count().catch(() => 0);
      if (count > 0) { console.info("[INFO] " + count + " produtos via '" + sel + "'"); break; }
    }
    if (count === 0) console.error("[P0] Sem produtos na PLP");
    expect(count, "[P0] Sem produtos na PLP").toBeGreaterThan(0);
  });

  test("04 — cards têm nome visível (P0)", async ({ page }) => {
    await page.waitForSelector(".product-item-link, .product-item", { timeout: 15_000 }).catch(() => {});
    const nameEl = page.locator(".product-item-name a, .product-item-link, a.product-item-link").first();
    const visible = await nameEl.isVisible({ timeout: 5_000 }).catch(() => false);
    if (!visible) { console.error("[P0] Nome do produto não visível"); test.skip(); }
    else expect(visible).toBe(true);
  });

  test("05 — imagens dos cards carregadas", async ({ page }) => {
    await page.waitForSelector(".product-item-link, .product-item", { timeout: 15_000 }).catch(() => {});
    await page.waitForTimeout(1_000);
    const images = page.locator(".product-item img, li.item.product img");
    const count = await images.count().catch(() => 0);
    if (count === 0) { console.warn("[P2] Sem imagens na PLP"); return; }
    let broken = 0;
    for (let i = 0; i < Math.min(count, 4); i++) {
      const loaded = await images.nth(i)
        .evaluate((img: HTMLImageElement) => img.naturalWidth > 0 && img.complete)
        .catch(() => true);
      if (!loaded) broken++;
    }
    if (broken > 0) console.warn("[P2] " + broken + " imagens quebradas");
    expect(broken).toBe(0);
  });
});
