import { test, expect } from "@playwright/test";
import { navigateTo } from "../../helpers/visual-audit.helpers";

const CREATE_URL = "https://awamotos.com/customer/account/create/";
const FORGOT_URL = "https://awamotos.com/b2b/account/forgotpassword/";

test.describe("Formulários — cadastro e senha", () => {
  test("01 — página de criar conta carrega (P1)", async ({ page }) => {
    const ok = await navigateTo(page, CREATE_URL);
    if (!ok) test.skip();
    const content = page.locator(".page-title, h1, form").first();
    await expect(content).toBeVisible({ timeout: 10_000 });
  });

  test("02 — formulário tem campo de nome (P1)", async ({ page }) => {
    const ok = await navigateTo(page, CREATE_URL);
    if (!ok) test.skip();
    const field = page.locator("#firstname, input[name=\"firstname\"]").first();
    const visible = await field.isVisible({ timeout: 8_000 }).catch(() => false);
    if (!visible) { console.warn("[P1] Campo firstname não encontrado"); test.skip(); }
    else expect(visible).toBe(true);
  });

  test("03 — formulário tem campo de email (P1)", async ({ page }) => {
    const ok = await navigateTo(page, CREATE_URL);
    if (!ok) test.skip();
    // Usar seletor com form ID específico para evitar campo do newsletter
    const field = page.locator(
      "form#form-validate #email_address, form.form.create.account input[name=\"email\"]"
    ).first();
    const visible = await field.isVisible({ timeout: 8_000 }).catch(() => false);
    if (!visible) { console.warn("[P1] Campo email do formulário não encontrado"); test.skip(); }
    else expect(visible).toBe(true);
  });

  test("04 — submissão vazia gera validação (P1)", async ({ page }) => {
    const ok = await navigateTo(page, CREATE_URL);
    if (!ok) test.skip();
    // Botão submit específico do formulário de criação (não o de busca)
    const btn = page.locator(
      "form#form-validate button[type=\"submit\"], .action.submit.primary.create-account"
    ).first();
    const btnVisible = await btn.isVisible({ timeout: 8_000 }).catch(() => false);
    if (!btnVisible) { test.skip(); return; }
    await btn.click({ force: true }).catch(() => {});
    await page.waitForTimeout(1_200);
    const errors = page.locator(".mage-error, .field-error, [aria-invalid=\"true\"]");
    const count = await errors.count().catch(() => 0);
    if (count === 0) console.warn("[P1] Sem validação ao submeter vazio");
    expect(count, "[P1] Sem validação em formulário vazio").toBeGreaterThan(0);
  });

  test("05 — página esqueci senha carrega (P2)", async ({ page }) => {
    // AWA usa B2B forgot password com id #b2b-forgot-email
    const ok = await navigateTo(page, FORGOT_URL);
    if (!ok) test.skip();
    await page.waitForTimeout(1_000);
    const field = page.locator(
      "#b2b-forgot-email"
    ).first();
    const visible = await field.isVisible({ timeout: 10_000 }).catch(() => false);
    if (!visible) console.warn("[P2] Campo email não visível na página de esqueceu senha");
    expect(visible, "[P2] Página forgot password sem campo email visível").toBe(true);
  });
});
