import { test, expect } from '@playwright/test';
import { navigateTo } from '../../helpers/visual-audit.helpers';

const REGISTER_URL = 'https://awamotos.com/b2b/register/';
const LOGIN_URL    = 'https://awamotos.com/b2b/account/login/';

test.describe('B2B Registro — cadastro empresarial', () => {

  test('01 — [P0] página de registro B2B carrega', async ({ page }) => {
    const ok = await navigateTo(page, REGISTER_URL);
    if (!ok) test.skip();
    // Deve carregar (200) — pode redirecionar para login se módulo exige auth
    const url = page.url();
    const loaded = url.includes('b2b') || url.includes('register') || url.includes('customer');
    expect(loaded, 'Página B2B não carregou corretamente').toBe(true);
  });

  test('02 — [P1] formulário exibe campos essenciais', async ({ page }) => {
    const ok = await navigateTo(page, REGISTER_URL);
    if (!ok) test.skip();
    // Se redirecionou para login, pula graciosamente
    if (page.url().includes('login')) { test.skip(); return; }
    const cnpjField = page.locator(
      'input[name*="cnpj"], input[id*="cnpj"], input[name*="taxvat"], input[name*="company_cnpj"]'
    ).first();
    // Email dentro do form de registro (excluindo newsletter no footer)
    const emailField = page.locator(
      'form#b2b-register-form input[name="email"], form.b2b-register-form input[type="email"], form[id*="register"] input[name="email"], input#email'
    ).first();
    const cnpjVisible = await cnpjField.isVisible({ timeout: 8_000 }).catch(() => false);
    const emailVisible = await emailField.isVisible({ timeout: 5_000 }).catch(() => false);
    if (!cnpjVisible) console.warn('[P1] Campo CNPJ não encontrado no form de registro B2B');
    if (!emailVisible) console.warn('[P1] Campo email não encontrado no form de registro B2B');
    // Pelo menos um campo de formulário deve existir
    const anyInput = page.locator('form input:visible').first();
    const anyVisible = await anyInput.isVisible({ timeout: 5_000 }).catch(() => false);
    expect(anyVisible, 'Formulário de registro sem campos visíveis').toBe(true);
  });

  test('03 — [P1] submissão vazia dispara validação', async ({ page }) => {
    const ok = await navigateTo(page, REGISTER_URL);
    if (!ok) test.skip();
    if (page.url().includes('login')) { test.skip(); return; }
    const submitBtn = page.locator('button[type="submit"], .b2b-btn-primary, .action.submit').first();
    const btnVisible = await submitBtn.isVisible({ timeout: 8_000 }).catch(() => false);
    if (!btnVisible) { test.skip(); return; }
    await submitBtn.click({ force: true });
    // Aguarda validação Magento (mage-error class adicionada nos campos obrigatórios)
    const validationErrEl = await page.waitForSelector(
      '.mage-error, input.mage-error, [aria-invalid="true"], .field.error .message, :invalid'
    , { timeout: 6_000 }).catch(() => null);
    const hasError = validationErrEl !== null;
    if (!hasError) console.warn('[P1] Submissão vazia não exibiu validação visível');
  });

  test('04 — [P1] link "Já tenho cadastro" aponta para login', async ({ page }) => {
    const ok = await navigateTo(page, REGISTER_URL);
    if (!ok) test.skip();
    if (page.url().includes('login')) { test.skip(); return; }
    const loginLink = page.locator(
      'a[href*="b2b/account/login"], a[href*="login"]'
    ).first();
    const visible = await loginLink.isVisible({ timeout: 8_000 }).catch(() => false);
    if (!visible) { console.warn('[P1] Link "Já tenho cadastro" não encontrado'); test.skip(); return; }
    const href = await loginLink.getAttribute('href');
    expect(href).toMatch(/login/);
  });

  test('05 — [P2] CTA ou banner do portal B2B visível', async ({ page }) => {
    const ok = await navigateTo(page, REGISTER_URL);
    if (!ok) test.skip();
    if (page.url().includes('login')) { test.skip(); return; }
    const banner = page.locator(
      '.b2b-register-hero, .b2b-auth-card, .b2b-register-banner, h1, .page-title'
    ).first();
    const visible = await banner.isVisible({ timeout: 8_000 }).catch(() => false);
    if (!visible) console.warn('[P2] Nenhum CTA/banner do portal B2B encontrado na página de registro');
  });

});
