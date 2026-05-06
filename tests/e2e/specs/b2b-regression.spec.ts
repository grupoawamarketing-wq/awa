/**
 * PLANO DE REGRESSÃO B2B — AWA Motos
 *
 * Cobre as 4 áreas de risco identificadas na auditoria de impacto:
 *   ÁREA 1 — Preço/render de produto (PDP, PLP, Busca)
 *   ÁREA 2 — Auth redirects (plugins LoginRedirect, CreateRedirect, etc.)
 *   ÁREA 3 — Checkout/cart blocking (B2B approval gates)
 *   ÁREA 4 — Order event fan-out (smoke — pages/API que disparam observers)
 *
 * Pré-requisitos:
 *   TEST_USER         — email de cliente B2B aprovado
 *   TEST_PASS         — senha do cliente acima
 *   TEST_PENDING_USER — email de cliente B2B PENDENTE de aprovação
 *   TEST_PENDING_PASS — senha do cliente pendente
 *
 * Execução:
 *   cd tests/e2e
 *   TEST_USER=… TEST_PASS=… npm run test:b2b-regression
 */
import { test, Page } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const BASE_URL      = 'https://awamotos.com';
const TEST_EMAIL    = process.env.TEST_USER         ?? '';
const TEST_PASS     = process.env.TEST_PASS         ?? '';
const PENDING_EMAIL = process.env.TEST_PENDING_USER ?? '';
const PENDING_PASS  = process.env.TEST_PENDING_PASS ?? '';

const PRODUCT_URL  = `${BASE_URL}/ret-biz-100-cr-redondo-universal-2220.html`;
const CATEGORY_URL = `${BASE_URL}/bagageiros.html`;

const SS_DIR               = path.join(__dirname, '..', 'screenshots', 'b2b-regression');
const AUTH_STATE_APPROVED  = path.join(__dirname, '..', '.auth-approved.json');
const AUTH_STATE_PENDING   = path.join(__dirname, '..', '.auth-pending.json');

interface Issue {
  area: string;
  test: string;
  severity: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW';
  description: string;
}
const issues: Issue[] = [];

function addIssue(area: string, testName: string, severity: Issue['severity'], description: string): void {
  console.error(`[ISSUE:${severity}] [${area}] ${testName}: ${description}`);
  issues.push({ area, test: testName, severity, description });
}

function ss(page: Page, name: string): Promise<void> {
  fs.mkdirSync(SS_DIR, { recursive: true });
  return page
    .screenshot({ path: path.join(SS_DIR, `${name}.png`), fullPage: false })
    .then(() => console.log(`📸 ${name}.png`))
    .catch((e: Error) => console.warn(`Screenshot ${name}.png falhou: ${e.message}`));
}

async function waitReady(page: Page, timeout = 25_000): Promise<void> {
  await page.waitForLoadState('domcontentloaded', { timeout }).catch(() => {});
  await page.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => {});
}

async function loginAs(page: Page, email: string, pass: string, authFile: string): Promise<boolean> {
  if (fs.existsSync(authFile)) {
    const saved = JSON.parse(fs.readFileSync(authFile, 'utf8'));
    await page.context().addCookies(saved.cookies ?? []);
    const resp = await page.request
      .get(`${BASE_URL}/customer/account/`, { maxRedirects: 0 })
      .catch(() => null);
    if (resp && resp.status() < 302) return true;
  }

  await page.goto(`${BASE_URL}/b2b/account/login/`, { waitUntil: 'domcontentloaded', timeout: 45_000 });
  await page.locator('#b2b-email').fill(email);
  await page.locator('#b2b-pass').fill(pass);
  await page.locator('.b2b-btn-entrar').click({ force: true });
  await page.waitForLoadState('domcontentloaded', { timeout: 30_000 }).catch(() => {});
  await page.waitForTimeout(1500);

  const url = page.url();
  const loggedIn = url.includes('/b2b/account/dashboard') || url.includes('/b2b/account/');
  if (loggedIn) {
    const cookies = await page.context().cookies();
    fs.writeFileSync(authFile, JSON.stringify({ cookies }), 'utf8');
  }
  return loggedIn;
}

// ---------------------------------------------------------------------------
// ÁREA 1 — Preço / render de produto
// ---------------------------------------------------------------------------
test.describe('ÁREA 1 — Preço e render de produto', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  test('R1.1 | Guest — PDP: sem preço R$0,00 e sem double-wrap FinalPriceBox', async ({ page }) => {
    await page.goto(PRODUCT_URL, { waitUntil: 'domcontentloaded', timeout: 45_000 });
    await waitReady(page);
    await ss(page, 'r1-1-pdp-guest');

    const priceVisible = await page.locator('.price-box .price').first().isVisible().catch(() => false);
    const msgVisible   = await page.locator(
      '.b2b-price-replacement, .b2b-login-to-see-price, [data-b2b-price-msg], .b2b-price-message'
    ).first().isVisible().catch(() => false);

    if (!priceVisible && !msgVisible) {
      addIssue('ÁREA1', 'R1.1', 'HIGH', 'Nem preço nem mensagem B2B visível para guest — estado indeterminado');
    }

    const priceTexts = await page.locator('.price-box .price').allTextContents().catch(() => [] as string[]);
    const zeroPrice  = priceTexts.filter(t => t.includes('0,00'));
    if (zeroPrice.length) {
      addIssue('ÁREA1', 'R1.1', 'CRITICAL', `Preço R$0,00 exibido para guest: ${zeroPrice.join(', ')}`);
    }

    const doubleWrap = await page.evaluate(() => {
      const outer = document.querySelector('.price-final_price');
      return outer ? !!outer.querySelector('.price-final_price') : false;
    });
    if (doubleWrap) {
      addIssue('ÁREA1', 'R1.1', 'HIGH', 'FinalPriceBox double-wrap detectado — dois .price-final_price aninhados (colisão HideFinalPricePlugin + HidePricePlugin)');
    }

    console.log(`R1.1 PDP guest: priceVisible=${priceVisible} msgVisible=${msgVisible} zeroPrice=${zeroPrice.length} doubleWrap=${doubleWrap}`);
  });

  test('R1.2 | B2B aprovado — PDP: preço ERP visível e botão add-to-cart presente', async ({ page }) => {
    if (!TEST_EMAIL) { test.skip(); return; }
    const ok = await loginAs(page, TEST_EMAIL, TEST_PASS, AUTH_STATE_APPROVED);
    if (!ok) {
      addIssue('ÁREA1', 'R1.2', 'HIGH', 'Login B2B aprovado falhou — teste inconclusivo');
      return;
    }

    await page.goto(PRODUCT_URL, { waitUntil: 'domcontentloaded', timeout: 45_000 });
    await waitReady(page);
    await ss(page, 'r1-2-pdp-b2b-approved');

    const priceTexts = await page.locator('.price-box .price').allTextContents().catch(() => [] as string[]);
    const priceVisible = priceTexts.length > 0;
    const zeroPrice    = priceTexts.filter(t => t.includes('0,00'));
    const addToCart    = await page.locator('#product-addtocart-button, .action.tocart').first().isVisible().catch(() => false);

    if (!priceVisible) addIssue('ÁREA1', 'R1.2', 'CRITICAL', 'Preço não visível para B2B aprovado — GroupPricePlugin ou HidePricePlugin bloqueando');
    if (zeroPrice.length) addIssue('ÁREA1', 'R1.2', 'CRITICAL', `R$0,00 para B2B aprovado — ERP price não aplicado: ${zeroPrice.join(', ')}`);
    if (!addToCart) addIssue('ÁREA1', 'R1.2', 'HIGH', 'Botão add-to-cart ausente para B2B aprovado — BlockCartAddPlugin indevidamente ativo');

    console.log(`R1.2 PDP B2B: prices="${priceTexts.join('|')}" addToCart=${addToCart}`);
  });

  test('R1.3 | Guest — PLP: produtos renderizados, sem R$0,00, sem double-wrap', async ({ page }) => {
    await page.goto(CATEGORY_URL, { waitUntil: 'domcontentloaded', timeout: 45_000 });
    await page.locator('.item-product, .products.wrapper').first()
      .waitFor({ state: 'visible', timeout: 20_000 }).catch(() => {});
    await ss(page, 'r1-3-plp-guest');

    const count = await page.locator('.item-product').count();
    if (count === 0) {
      addIssue('ÁREA1', 'R1.3', 'CRITICAL', 'Nenhum produto renderizado na PLP');
    }

    const priceTexts = await page.locator('.item-product .price').allTextContents().catch(() => [] as string[]);
    const zeroPrice  = priceTexts.filter(t => t.includes('0,00'));
    if (zeroPrice.length) addIssue('ÁREA1', 'R1.3', 'HIGH', `${zeroPrice.length} produto(s) com R$0,00 na PLP`);

    const doubleWrap = await page.evaluate(() => {
      for (const item of Array.from(document.querySelectorAll('.item-product'))) {
        const outer = item.querySelector('.price-final_price');
        if (outer && outer.querySelector('.price-final_price')) return true;
      }
      return false;
    });
    if (doubleWrap) addIssue('ÁREA1', 'R1.3', 'HIGH', 'Double-wrap .price-final_price na PLP');

    console.log(`R1.3 PLP: count=${count} zeroPrice=${zeroPrice.length} doubleWrap=${doubleWrap}`);
  });

  test('R1.4 | FPC — dois contextos guest devem ver o mesmo estado de preço (sem cache poisoning)', async ({ browser }) => {
    const ctx1 = await browser.newContext({ locale: 'pt-BR' });
    const ctx2 = await browser.newContext({ locale: 'pt-BR' });
    const p1   = await ctx1.newPage();
    const p2   = await ctx2.newPage();

    await p1.goto(PRODUCT_URL, { waitUntil: 'domcontentloaded', timeout: 45_000 });
    await p2.goto(PRODUCT_URL, { waitUntil: 'domcontentloaded', timeout: 45_000 });

    const price1 = await p1.locator('.price-box .price').first().textContent().catch(() => 'N/A');
    const price2 = await p2.locator('.price-box .price').first().textContent().catch(() => 'N/A');

    await ctx1.close();
    await ctx2.close();

    if (price1 !== price2) {
      addIssue('ÁREA1', 'R1.4', 'HIGH', `FPC inconsistente: ctx1="${price1}" ctx2="${price2}" — possível vazamento HttpContext entre requests`);
    }
    console.log(`R1.4 FPC: ctx1="${price1}" ctx2="${price2}" consistent=${price1 === price2}`);
  });
});

// ---------------------------------------------------------------------------
// ÁREA 2 — Auth redirects
// ---------------------------------------------------------------------------
test.describe('ÁREA 2 — Auth redirects', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  test('R2.1 | Guest — /customer/account/create → redirect para /b2b/register', async ({ page }) => {
    await page.goto(`${BASE_URL}/customer/account/create/`, { waitUntil: 'commit', timeout: 30_000 });
    const finalUrl   = page.url();
    const isB2BReg   = finalUrl.includes('/b2b/register') || finalUrl.includes('/b2b/account/register');
    const isB2BLogin = finalUrl.includes('/b2b/account/login');

    if (!isB2BReg && !isB2BLogin) {
      addIssue('ÁREA2', 'R2.1', 'HIGH', `CreateRedirect plugin não interceptou: finalUrl="${finalUrl}"`);
    }
    console.log(`R2.1: /customer/account/create/ → ${finalUrl}`);
  });

  test('R2.2 | Guest — /customer/account/login → redirect para /b2b/account/login', async ({ page }) => {
    await page.goto(`${BASE_URL}/customer/account/login/`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
    const finalUrl   = page.url();
    const isB2BLogin = finalUrl.includes('/b2b/account/login');

    if (!isB2BLogin) {
      addIssue('ÁREA2', 'R2.2', 'HIGH', `LoginRedirectPlugin não interceptou: finalUrl="${finalUrl}"`);
    }

    const emailField = await page.locator('#b2b-email').isVisible().catch(() => false);
    if (!emailField && isB2BLogin) {
      addIssue('ÁREA2', 'R2.2', 'MEDIUM', 'Página /b2b/account/login carregou mas #b2b-email não visível');
    }
    await ss(page, 'r2-2-login-redirect');
    console.log(`R2.2: /customer/account/login/ → ${finalUrl} emailField=${emailField}`);
  });

  test('R2.3 | Guest — /customer/account/forgotpassword → redirect B2B', async ({ page }) => {
    await page.goto(`${BASE_URL}/customer/account/forgotpassword/`, { waitUntil: 'commit', timeout: 30_000 });
    const finalUrl  = page.url();
    const redirected = !finalUrl.includes('/customer/account/forgotpassword');
    if (!redirected) {
      addIssue('ÁREA2', 'R2.3', 'MEDIUM', `ForgotPasswordRedirectPlugin não interceptou: finalUrl="${finalUrl}"`);
    }
    console.log(`R2.3: /customer/account/forgotpassword/ → ${finalUrl}`);
  });

  test('R2.4 | B2B logado — /customer/account → redirect /b2b/account/dashboard', async ({ page }) => {
    if (!TEST_EMAIL) { test.skip(); return; }
    const ok = await loginAs(page, TEST_EMAIL, TEST_PASS, AUTH_STATE_APPROVED);
    if (!ok) {
      addIssue('ÁREA2', 'R2.4', 'HIGH', 'Login B2B falhou — não foi possível testar DashboardRedirectPlugin');
      return;
    }
    await page.goto(`${BASE_URL}/customer/account/`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
    const finalUrl    = page.url();
    const isDashboard = finalUrl.includes('/b2b/account/dashboard');
    if (!isDashboard) {
      addIssue('ÁREA2', 'R2.4', 'HIGH', `DashboardRedirectPlugin não interceptou: finalUrl="${finalUrl}"`);
    }
    console.log(`R2.4: /customer/account/ → ${finalUrl}`);
  });
});

// ---------------------------------------------------------------------------
// ÁREA 3 — Checkout / cart blocking
// ---------------------------------------------------------------------------
test.describe('ÁREA 3 — Checkout e cart blocking', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  test('R3.1 | Guest — add to cart deve ser bloqueado ou redirecionar para login', async ({ page }) => {
    await page.goto(PRODUCT_URL, { waitUntil: 'domcontentloaded', timeout: 45_000 });
    await waitReady(page);

    const addBtn    = page.locator('#product-addtocart-button, .action.tocart').first();
    const btnExists = await addBtn.isVisible().catch(() => false);

    if (btnExists) {
      await addBtn.click({ force: true });
      await page.waitForTimeout(2000);

      const finalUrl    = page.url();
      const isLoginPage = finalUrl.includes('/b2b/account/login') || finalUrl.includes('/customer/account/login');
      const errorMsg    = await page.locator('.message-error, .messages .error').first().isVisible().catch(() => false);
      const counter     = await page.locator('.counter-number').first().textContent().catch(() => '0');
      const counterNum  = parseInt(counter ?? '0', 10);

      if (!isLoginPage && !errorMsg && counterNum > 0) {
        addIssue('ÁREA3', 'R3.1', 'HIGH', `Guest adicionou ao carrinho — BlockCartAddPlugin não ativo (counter="${counter}")`);
      }
      await ss(page, 'r3-1-guest-add-to-cart');
      console.log(`R3.1 add-to-cart guest: url="${finalUrl}" error=${errorMsg} counter="${counter}"`);
    } else {
      console.log('R3.1: botão add-to-cart oculto para guest (comportamento esperado em strict_b2b)');
    }
  });

  test('R3.2 | Guest — /checkout → deve ser bloqueado antes de renderizar OPC', async ({ page }) => {
    await page.goto(`${BASE_URL}/checkout/`, { waitUntil: 'commit', timeout: 30_000 });
    await page.waitForTimeout(1500);
    const finalUrl      = page.url();
    const checkoutBlock = await page.locator('.opc-wrapper, .steps-wizard-block').first().isVisible().catch(() => false);

    if (checkoutBlock) {
      addIssue('ÁREA3', 'R3.2', 'CRITICAL', `Guest acessou checkout OPC — BlockCheckoutPlugin não interceptou (url="${finalUrl}")`);
    }
    console.log(`R3.2: /checkout/ → ${finalUrl} checkoutBlock=${checkoutBlock}`);
  });

  test('R3.3 | B2B pendente — checkout bloqueado com mensagem explicativa', async ({ page }) => {
    if (!PENDING_EMAIL) { test.skip(); return; }
    const ok = await loginAs(page, PENDING_EMAIL, PENDING_PASS, AUTH_STATE_PENDING);
    if (!ok) {
      addIssue('ÁREA3', 'R3.3', 'MEDIUM', 'Login com conta pendente falhou — teste inconclusivo');
      return;
    }

    await page.goto(`${BASE_URL}/checkout/`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
    await waitReady(page);
    await ss(page, 'r3-3-pending-checkout');

    const finalUrl     = page.url();
    const checkoutForm = await page.locator('.opc-wrapper').first().isVisible().catch(() => false);
    const blockedMsg   = await page.locator('.message-notice, .b2b-pending-message, .message-warning').first().isVisible().catch(() => false);

    if (checkoutForm) {
      addIssue('ÁREA3', 'R3.3', 'CRITICAL', 'Cliente pendente chegou ao checkout — BlockCheckoutPlugin não verificou approval status');
    }
    if (!blockedMsg && !checkoutForm) {
      addIssue('ÁREA3', 'R3.3', 'MEDIUM', 'Bloqueado mas sem mensagem explicativa — UX degradada');
    }
    console.log(`R3.3: pendente checkout → "${finalUrl}" checkoutForm=${checkoutForm} blockedMsg=${blockedMsg}`);
  });

  test('R3.4 | B2B aprovado — cart/checkout/totais sem R$0,00 e sem HideCartTotals indevido', async ({ page }) => {
    if (!TEST_EMAIL) { test.skip(); return; }
    await loginAs(page, TEST_EMAIL, TEST_PASS, AUTH_STATE_APPROVED);

    await page.goto(`${BASE_URL}/checkout/cart/`, { waitUntil: 'domcontentloaded', timeout: 45_000 });
    await waitReady(page);
    await ss(page, 'r3-4-approved-cart');

    const cartItems  = await page.locator('.cart.item, .items-in-cart .item').count();
    const cartTotals = await page.locator('.cart-totals, .totals').first().isVisible().catch(() => false);

    if (cartItems > 0 && !cartTotals) {
      addIssue('ÁREA3', 'R3.4', 'HIGH', 'Carrinho com itens mas totais ausentes — HideCartTotalsPlugin bloqueando indevidamente B2B aprovado');
    }

    if (cartItems > 0) {
      const totalTexts = await page.locator('.cart-totals .price').allTextContents().catch(() => [] as string[]);
      const zeroTotals = totalTexts.filter(t => t.includes('0,00'));
      if (zeroTotals.length) {
        addIssue('ÁREA3', 'R3.4', 'HIGH', `Total R$0,00 no carrinho aprovado — ERP price não propagado para quote total`);
      }
    }
    console.log(`R3.4: cart items=${cartItems} totals=${cartTotals}`);
  });
});

// ---------------------------------------------------------------------------
// ÁREA 4 — Smoke de páginas que disparam observers de pedido
// ---------------------------------------------------------------------------
test.describe('ÁREA 4 — Smoke de observers/event fan-out', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  test('R4.1 | B2B aprovado — dashboard carrega sem erros JS críticos', async ({ page }) => {
    if (!TEST_EMAIL) { test.skip(); return; }
    const jsErrors: string[] = [];
    page.on('pageerror', (e) => jsErrors.push(e.message));

    const ok = await loginAs(page, TEST_EMAIL, TEST_PASS, AUTH_STATE_APPROVED);
    if (!ok) { addIssue('ÁREA4', 'R4.1', 'HIGH', 'Login B2B falhou'); return; }

    await page.goto(`${BASE_URL}/b2b/account/dashboard/`, { waitUntil: 'domcontentloaded', timeout: 45_000 });
    await waitReady(page);
    await ss(page, 'r4-1-dashboard');

    const hasDashboard = await page
      .locator('.b2b-dashboard, .account-nav, .b2b-account, .dashboard')
      .first().isVisible().catch(() => false);

    if (!hasDashboard) addIssue('ÁREA4', 'R4.1', 'HIGH', 'Dashboard B2B não renderizou bloco principal');
    if (jsErrors.length) addIssue('ÁREA4', 'R4.1', 'MEDIUM', `${jsErrors.length} JS error(s): ${jsErrors[0]}`);

    console.log(`R4.1 dashboard: hasDashboard=${hasDashboard} jsErrors=${jsErrors.length}`);
  });

  test('R4.2 | B2B aprovado — histórico de pedidos: HTTP 200 e sem 500', async ({ page }) => {
    if (!TEST_EMAIL) { test.skip(); return; }
    await loginAs(page, TEST_EMAIL, TEST_PASS, AUTH_STATE_APPROVED);

    const response = await page.goto(`${BASE_URL}/sales/order/history/`, { waitUntil: 'domcontentloaded', timeout: 45_000 });
    await waitReady(page);
    await ss(page, 'r4-2-order-history');

    const status    = response?.status() ?? 0;
    const serverErr = await page.locator('.page-title:has-text("404"), .page-title:has-text("503"), .page-title:has-text("500")').first().isVisible().catch(() => false);

    if (status >= 500) addIssue('ÁREA4', 'R4.2', 'CRITICAL', `Histórico de pedidos retornou HTTP ${status}`);
    if (serverErr)     addIssue('ÁREA4', 'R4.2', 'CRITICAL', 'Página de pedidos exibe erro de servidor visível');

    console.log(`R4.2 order history: status=${status} serverErr=${serverErr}`);
  });

  test('R4.3 | API — resposta de histórico de pedidos não contém stack trace PHP', async ({ page }) => {
    if (!TEST_EMAIL) { test.skip(); return; }
    await loginAs(page, TEST_EMAIL, TEST_PASS, AUTH_STATE_APPROVED);

    const resp = await page.request.get(`${BASE_URL}/sales/order/history/`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).catch(() => null);

    if (!resp) {
      addIssue('ÁREA4', 'R4.3', 'MEDIUM', 'Request para histórico falhou completamente');
      return;
    }

    const body         = await resp.text().catch(() => '');
    const hasException = body.includes('Uncaught') || body.includes('Fatal error') || body.includes('Stack trace');
    if (hasException) {
      addIssue('ÁREA4', 'R4.3', 'CRITICAL', 'Resposta contém stack trace PHP — observer lançando exceção não capturada');
    }
    console.log(`R4.3 order API: status=${resp.status()} hasException=${hasException}`);
  });

  test('R4.4 | Página b2b/register/success: retorna 200 ou redirect (nunca 500)', async ({ page }) => {
    const resp   = await page.goto(`${BASE_URL}/b2b/register/success/`, { waitUntil: 'commit', timeout: 30_000 });
    const status = resp?.status() ?? 0;
    if (status >= 500) {
      addIssue('ÁREA4', 'R4.4', 'CRITICAL', `b2b/register/success retornou HTTP ${status} — observer customer_register_success lançando exceção`);
    }
    console.log(`R4.4 register/success: HTTP ${status} → ${page.url()}`);
  });
});

// ---------------------------------------------------------------------------
// Relatório consolidado
// ---------------------------------------------------------------------------
test.afterAll(async () => {
  if (issues.length === 0) {
    console.log('\n✅ REGRESSÃO B2B PASSOU — nenhum issue encontrado');
    return;
  }

  const bySev = (s: Issue['severity']) => issues.filter(i => i.severity === s);
  console.log(`\n${'═'.repeat(68)}`);
  console.log(`RELATÓRIO DE REGRESSÃO B2B — ${new Date().toLocaleString('pt-BR')}`);
  console.log('═'.repeat(68));
  for (const sev of ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'] as const) {
    const list = bySev(sev);
    if (!list.length) continue;
    console.log(`\n── ${sev} (${list.length}) ──`);
    list.forEach(i => console.log(`  [${i.area}] ${i.test}: ${i.description}`));
  }
  const c = bySev('CRITICAL').length, h = bySev('HIGH').length;
  console.log(`\nTotal: CRITICAL=${c} HIGH=${h} MEDIUM=${bySev('MEDIUM').length} LOW=${bySev('LOW').length}`);

  const reportPath = path.join(SS_DIR, 'regression-report.json');
  fs.mkdirSync(SS_DIR, { recursive: true });
  fs.writeFileSync(reportPath, JSON.stringify({ timestamp: new Date().toISOString(), issues }, null, 2), 'utf8');
  console.log(`Relatório JSON: ${reportPath}`);
});
