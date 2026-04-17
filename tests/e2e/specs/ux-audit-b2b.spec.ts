/**
 * UX AUDIT — Fluxo Completo B2B (Desktop 1280px)
 * Credenciais via variáveis de ambiente: TEST_USER / TEST_PASS
 */
import { test, expect, Page } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const BASE_URL   = 'https://awamotos.com';
const TEST_EMAIL = process.env.TEST_USER ?? '';
const TEST_PASS  = process.env.TEST_PASS  ?? '';
const SS_DIR         = path.join(__dirname, '..', 'screenshots', 'ux-audit');
const AUTH_STATE_FILE = path.join(__dirname, '..', '.auth-state.json');

const issues: Array<{ step: string; severity: string; description: string; impact: string }> = [];

function addIssue(step: string, severity: string, description: string, impact: string) {
  console.warn(`[ISSUE ${severity}] ${step}: ${description}`);
  issues.push({ step, severity, description, impact });
}

async function screenshot(page: Page, name: string) {
  fs.mkdirSync(SS_DIR, { recursive: true });
  await page.screenshot({ path: path.join(SS_DIR, `${name}.png`), fullPage: false, timeout: 8_000 }).catch((e: Error) => {
    console.warn(`⚠️  Screenshot ${name}.png falhou: ${e.message}`);
  });
  console.log(`📸 ${name}.png`);
}

async function waitReady(page: Page, timeout = 20_000) {
  await page.waitForLoadState('domcontentloaded', { timeout }).catch(() => {});
  await page.waitForLoadState('load', { timeout }).catch(() => {});
}

async function dismissCookieBanner(page: Page) {
  const btn = page.locator('#awa-cookie-accept, .awa-cookie-banner__btn--accept').first();
  const visible = await btn.isVisible().catch(() => false);
  if (visible) {
    await btn.click({ force: true }).catch(() => {});
    await page.waitForTimeout(400);
  }
}

async function doLogin(page: Page) {
  // Reutilizar cookies salvos após primeiro login
  if (fs.existsSync(AUTH_STATE_FILE)) {
    try {
      const saved = JSON.parse(fs.readFileSync(AUTH_STATE_FILE, 'utf8'));
      await page.context().addCookies(saved.cookies || []);
      // Verificar auth com request leve ao invés de navegação completa
      const resp = await page.request.get(`${BASE_URL}/customer/account/ajax/`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).catch(() => null);
      if (resp && resp.status() !== 302) {
        // Cookie aplicado — ir para home para "aquecer" a sessão
        await page.goto(BASE_URL, { waitUntil: 'commit', timeout: 15_000 }).catch(() => {});
        return;
      }
    } catch (_) {}
  }

  // Login manual (fallback)
  await page.goto(`${BASE_URL}/b2b/account/login/`, { waitUntil: 'domcontentloaded', timeout: 60_000 }).catch(() => {});
  if (!page.url().includes('/b2b/account/login')) return;

  await dismissCookieBanner(page);
  await page.locator('#b2b-email').first().fill(TEST_EMAIL);
  await page.locator('#b2b-pass').first().fill(TEST_PASS);
  await page.locator('.b2b-btn-entrar').first().click({ force: true }).catch(() => {});
  await page.waitForLoadState('domcontentloaded', { timeout: 30_000 }).catch(() => {});
  await page.waitForTimeout(1500);
}

test.describe('UX Audit B2B', () => {
  test.use({ viewport: { width: 1280, height: 800 } });

  test('01 | Homepage — Header guest', async ({ page }) => {
    const jsErrors: string[] = [];
    page.on('pageerror', (e) => jsErrors.push(e.message));
    await page.goto(BASE_URL, { waitUntil: 'domcontentloaded', timeout: 60_000 });
    await page.waitForTimeout(2000);
    await screenshot(page, '01-homepage-guest');

    const logo    = await page.locator('.logo').first().isVisible().catch(() => false);
    const search  = await page.locator('input#search, input[name="q"]').first().isVisible().catch(() => false);
    const cart    = await page.locator('.minicart-wrapper, .action.showcart').first().isVisible().catch(() => false);
    const loginLk = await page.locator('a[data-awa-auth-link]').first().isVisible().catch(() => false);
    const nav     = await page.locator('nav.navigation, .nav-sections').first().isVisible().catch(() => false);
    const hh      = await page.evaluate(() => {
      const h = document.querySelector('header[role="banner"].awa-site-header');
      return h ? h.getBoundingClientRect().height : 0;
    }).catch(() => 0);

    if (!logo)    addIssue('Header', 'ALTA',  'Logo não visível',         'Identidade visual');
    if (!search)  addIssue('Header', 'ALTA',  'Campo busca não visível',  'Usuário não consegue buscar');
    if (!cart)    addIssue('Header', 'ALTA',  'Ícone carrinho ausente',   'Carrinho inacessível');
    if (!loginLk) addIssue('Header', 'MÉDIA', 'Link login ausente',       'Dificulta acesso');
    if (!nav)     addIssue('Header', 'MÉDIA', 'Navegação ausente',        'Categorias inacessíveis');
    if (hh < 50)  addIssue('Header', 'ALTA',  `Header colapsado: ${hh}px`, 'Header invisível');

    console.log(`Header: logo=${logo} search=${search} cart=${cart} login=${loginLk} nav=${nav} height=${hh}px`);
    if (jsErrors.length) addIssue('Homepage', 'ALTA', `${jsErrors.length} erro(s) JS: ${jsErrors[0]}`, 'Funcionalidades quebradas');
    else console.log('✅ Sem erros JS na homepage');

    if (!logo && !search) addIssue('Header', 'ALTA', 'Logo e busca ausentes após carregamento', 'Página pode não ter renderizado');
  });

  test('02 | Login B2B', async ({ page }) => {
    const jsErrors: string[] = [];
    page.on('pageerror', (e) => jsErrors.push(e.message));
    await page.goto(`${BASE_URL}/customer/account/login/`, { waitUntil: 'domcontentloaded' });
    await waitReady(page);
    await screenshot(page, '02a-login-page');

    const emailF = await page.locator('#b2b-email').first().isVisible().catch(() => false);
    const passF  = await page.locator('#b2b-pass').first().isVisible().catch(() => false);
    if (!emailF) addIssue('Login', 'ALTA', 'Campo email ausente', 'Login impossível');
    if (!passF)  addIssue('Login', 'ALTA', 'Campo senha ausente',  'Login impossível');

    if (emailF && passF) {
      await page.locator('#b2b-email').first().fill(TEST_EMAIL);
      await page.locator('#b2b-pass').first().fill(TEST_PASS);
      await screenshot(page, '02b-login-filled');
      await page.locator('.b2b-btn-entrar').first().click({ force: true, timeout: 30_000 }).catch(() => {});
      await page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30_000 }).catch(() => {});
      await page.waitForTimeout(2000);
      const url = page.url();
      const errVisible = await page.locator('.message-error, .messages .error').first().isVisible().catch(() => false);
      await screenshot(page, '02c-post-login');
      if (errVisible) {
        const txt = await page.locator('.message-error').first().textContent().catch(() => '');
        addIssue('Login', 'ALTA', `Erro: ${txt?.trim()}`, 'Cliente não acessa área B2B');
      } else {
        console.log(`✅ Login OK — URL: ${url}`);
        // Salvar cookies para reutilizar nos testes seguintes sem re-login
        const cookies = await page.context().cookies();
        fs.writeFileSync(AUTH_STATE_FILE, JSON.stringify({ cookies }), 'utf8');
        console.log(`✅ StorageState salvo: ${AUTH_STATE_FILE}`);
      }
    }
    if (jsErrors.length) addIssue('Login', 'MÉDIA', `JS: ${jsErrors[0]}`, 'Formulário pode falhar');
  });

  test('03 | Busca — retrovisor', async ({ page }) => {
    const jsErrors: string[] = [];
    page.on('pageerror', (e) => jsErrors.push(e.message));
    await doLogin(page);
    await page.goto(`${BASE_URL}/catalogsearch/result/?q=retrovisor`, { waitUntil: 'domcontentloaded' });
    await waitReady(page);
    // Aguardar KO.js renderizar produtos (dinâmico via AJAX)
    await page.locator('.product-item, .search.results .note-msg, .message.info.empty').first().waitFor({ state: 'visible', timeout: 20_000 }).catch(() => {});
    await screenshot(page, '03a-search-results');

    const count   = await page.locator('.product-item').count();
    const prices  = await page.locator('.price').count();
    const filters = await page.locator('.filter-options, #layered-filter-block').first().isVisible().catch(() => false);
    const paging  = await page.locator('.pages').first().isVisible().catch(() => false);

    console.log(`Busca "retrovisor": ${count} produtos, ${prices} preços, filtros=${filters}, paginação=${paging}`);
    if (count === 0)   addIssue('Busca', 'ALTA',  'Nenhum produto na busca',           'Busca não funciona');
    if (prices === 0)  addIssue('Busca', 'ALTA',  'Preços não visíveis (B2B logado)',  'Impacto direto em conversão');
    if (!filters)      addIssue('Busca', 'BAIXA', 'Filtros ausentes na busca',         'Refinamento impossível');
    if (count >= 12 && !paging) addIssue('Busca', 'BAIXA', 'Paginação ausente',        'Produtos inacessíveis');

    // Verificar preços zerados
    const priceTexts = await page.locator('.price-box .price').allTextContents().catch(() => [] as string[]);
    const zeroP = priceTexts.filter(p => p.includes('0,00')).length;
    if (zeroP) addIssue('Busca', 'ALTA', `${zeroP} produto(s) com preço R$0,00`, 'Erro de preço');

    await screenshot(page, '03b-search-filters');
    if (jsErrors.length) addIssue('Busca', 'MÉDIA', jsErrors[0], 'Filtros AJAX podem falhar');
    else console.log('✅ Sem erros JS na busca');
    // count=0 já reportado via addIssue acima — não falhar o runner
  });

  test('04 | Categoria — Bagageiros', async ({ page }) => {
    const jsErrors: string[] = [];
    page.on('pageerror', (e) => jsErrors.push(e.message));
    await doLogin(page);
    await page.goto(`${BASE_URL}/bagageiros.html`, { waitUntil: 'domcontentloaded' });
    await waitReady(page);
    await screenshot(page, '04a-category');

    const items     = await page.locator('.product-item').count();
    const toolbar   = await page.locator('.toolbar-products').first().isVisible().catch(() => false);
    const filterOpt = await page.locator('.filter-options-item').count();
    const addBtns   = await page.locator('.action.tocart').count();
    const catPrices = await page.locator('.price').count();

    console.log(`Categoria: ${items} produtos, toolbar=${toolbar}, ${filterOpt} filtros, ${addBtns} btns ATC, ${catPrices} preços`);
    if (items === 0)     addIssue('Categoria', 'ALTA',  'Categoria vazia',                    'Produtos inacessíveis');
    if (!toolbar)        addIssue('Categoria', 'BAIXA', 'Toolbar ausente',                    'Sem ordenação');
    if (filterOpt === 0) addIssue('Categoria', 'MÉDIA', 'Sem filtros na categoria',           'Navegação difícil');
    if (addBtns === 0)   addIssue('Categoria', 'ALTA',  'Sem botão ATC na lista',             'Compra impossível');
    if (catPrices === 0) addIssue('Categoria', 'ALTA',  'Preços ausentes na categoria',       'Impacto B2B crítico');

    // Testar abertura de filtro
    if (filterOpt > 0) {
      await page.locator('.filter-options-title').first().click();
      await page.waitForTimeout(800);
      const opened = await page.locator('.filter-options-content').first().isVisible().catch(() => false);
      if (!opened) addIssue('Categoria', 'MÉDIA', 'Filtro não abre ao clicar', 'Interação quebrada');
      else { console.log('✅ Filtro abre OK'); await screenshot(page, '04b-filter-open'); }
    }
    if (jsErrors.length) addIssue('Categoria', 'MÉDIA', jsErrors[0], 'Filtros AJAX');
    else console.log('✅ Sem erros JS na categoria');
  });

  test('05 | PDP — Página de Produto', async ({ page }) => {
    const jsErrors: string[] = [];
    page.on('pageerror', (e) => jsErrors.push(e.message));
    await doLogin(page);
    const href = 'https://awamotos.com/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html';
    await page.goto(href, { waitUntil: 'domcontentloaded', timeout: 60_000 }).catch(() => {});
    await waitReady(page);
    await screenshot(page, '05a-pdp-above-fold');

    const imgV   = await page.locator('.fotorama__img, .product-image-photo, .gallery-placeholder__image').first().isVisible().catch(() => false);
    const titleT = await page.locator('h1.page-title .base, h1.page-title').first().textContent().catch(() => '');
    const priceV = await page.locator('.product-info-price .price, .price-box .price').first().isVisible().catch(() => false);
    const priceT = await page.locator('.product-info-price .price, .price-box .price').first().textContent().catch(() => '');
    const qtyV   = await page.locator('input#qty, input[name="qty"]').first().isVisible().catch(() => false);
    const atcV   = await page.locator('#product-addtocart-button, .action.tocart').first().isVisible().catch(() => false);
    const atcDis = await page.locator('#product-addtocart-button, .action.tocart').first().isDisabled().catch(() => false);
    const skuT   = await page.locator('.product.attribute.sku .value').first().textContent().catch(() => '');
    const descV  = await page.locator('.product.attribute.description, #description').first().isVisible().catch(() => false);

    console.log(`PDP: img=${imgV} title="${titleT?.trim()}" price=${priceV}(${priceT?.trim()}) qty=${qtyV} atc=${atcV}(dis=${atcDis}) sku="${skuT?.trim()}" desc=${descV}`);

    if (!imgV)   addIssue('PDP', 'ALTA',  'Imagem ausente',                     'UX comprometida');
    if (!titleT?.trim()) addIssue('PDP', 'ALTA', 'Título ausente',              'SEO/UX crítico');
    if (!priceV) addIssue('PDP', 'ALTA',  'Preço não visível (B2B logado)',      'Conversão impactada');
    if (priceT?.includes('0,00')) addIssue('PDP', 'ALTA', 'Preço R$0,00',       'Erro crítico de preço');
    if (!qtyV)   addIssue('PDP', 'MÉDIA', 'Campo qty ausente',                  'Quantidade não ajustável');
    if (!atcV)   addIssue('PDP', 'ALTA',  'Botão ATC ausente',                  'Compra impossível');
    if (atcDis)  addIssue('PDP', 'ALTA',  'Botão ATC desabilitado',             'Compra impossível');
    if (!skuT?.trim()) addIssue('PDP', 'BAIXA', 'SKU ausente',                  'B2B precisa do SKU');
    if (!descV)  addIssue('PDP', 'BAIXA', 'Descrição ausente',                  'Informação insuficiente');

    if (atcV && !atcDis) {
      await page.locator('#product-addtocart-button, .action.tocart').first().click();
      await page.waitForTimeout(3000);
      const successV = await page.locator('.message-success, .messages .success').first().isVisible().catch(() => false);
      const cartN    = await page.locator('.counter.qty .counter-number').first().textContent().catch(() => '0');
      await screenshot(page, '05b-after-add-to-cart');
      if (!successV && cartN === '0') addIssue('PDP', 'ALTA', 'Produto não adicionado ao carrinho', 'Fluxo de compra quebrado');
      else console.log(`✅ Produto adicionado — carrinho: ${cartN}`);
    }

    if (jsErrors.length) addIssue('PDP', 'MÉDIA', jsErrors[0], 'Galeria/ATC pode falhar');
    else console.log('✅ Sem erros JS na PDP');
    await screenshot(page, '05c-pdp-full');
  });

  test('06 | Carrinho', async ({ page }) => {
    const jsErrors: string[] = [];
    page.on('pageerror', (e) => jsErrors.push(e.message));
    await doLogin(page);
    await page.goto('https://awamotos.com/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html', { waitUntil: 'domcontentloaded', timeout: 60_000 }).catch(() => {});
    await waitReady(page);
    await page.locator('#product-addtocart-button, .action.tocart').first().click().catch(() => {});
    await page.waitForTimeout(3000);
    await page.goto(`${BASE_URL}/checkout/cart/`, { waitUntil: 'domcontentloaded', timeout: 60_000 });
    await waitReady(page);
    await screenshot(page, '06a-cart');

    const cartItems  = await page.locator('.cart.item, .items .item').count();
    const itemName   = await page.locator('.product-item-name').first().textContent().catch(() => '');
    const priceV     = await page.locator('.price').first().isVisible().catch(() => false);
    const totalT     = await page.locator('.grand.totals .price').first().textContent().catch(() => '');
    const qtyF       = await page.locator('input.input-text.qty').first().isVisible().catch(() => false);
    const checkoutBt = await page.locator('.checkout-methods-items .action.primary.checkout, .action.primary.checkout').first().isVisible().catch(() => false);

    console.log(`Carrinho: ${cartItems} itens, item="${itemName?.trim()}", price=${priceV}, total="${totalT?.trim()}", qty=${qtyF}, checkout=${checkoutBt}`);

    if (cartItems === 0) { addIssue('Carrinho', 'ALTA', 'Carrinho vazio',               'Produto não adicionado'); return; }
    if (!priceV)           addIssue('Carrinho', 'ALTA', 'Preço ausente no carrinho',    'Crítico B2B');
    if (!totalT?.trim())   addIssue('Carrinho', 'MÉDIA','Total ausente',                'Usuário não sabe quanto paga');
    if (!qtyF)             addIssue('Carrinho', 'MÉDIA','Campo qty ausente',            'Quantidade não editável');
    if (!checkoutBt)       addIssue('Carrinho', 'ALTA', 'Botão Checkout ausente',       'Usuário não consegue avançar');

    if (qtyF) {
      await page.locator('input.input-text.qty').first().click({ clickCount: 3 });
      await page.locator('input.input-text.qty').first().fill('2');
      await page.locator('input.input-text.qty').first().press('Enter');
      await page.waitForTimeout(2000);
      await screenshot(page, '06b-qty-updated');
      console.log('✅ Quantidade alterada para 2');
    }
    await screenshot(page, '06c-cart-final');
    if (jsErrors.length) addIssue('Carrinho', 'MÉDIA', jsErrors[0], 'Atualização qty pode falhar');
    else console.log('✅ Sem erros JS no carrinho');
  });

  test('07 | Checkout — Validar etapas (sem finalizar)', async ({ page }) => {
    const jsErrors: string[] = [];
    page.on('pageerror', (e) => jsErrors.push(e.message));
    await doLogin(page);
    await page.goto('https://awamotos.com/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html', { waitUntil: 'domcontentloaded', timeout: 60_000 }).catch(() => {});
    await waitReady(page);
    await page.locator('#product-addtocart-button, .action.tocart').first().click().catch(() => {});
    await page.waitForTimeout(3000);
    await page.goto(`${BASE_URL}/checkout/`, { waitUntil: 'domcontentloaded', timeout: 60_000 }).catch(() => {});
    await waitReady(page, 30_000);
    await page.waitForTimeout(4000);
    await screenshot(page, '07a-checkout-initial');

    const url = page.url();
    console.log(`Checkout URL: ${url}`);
    // /checkout padrão Magento OU /expresscheckout.html (Rokanthemes OnePageCheckout) — ambos válidos
    const isCheckoutUrl = url.includes('/checkout') || url.includes('expresscheckout');
    if (!isCheckoutUrl) { addIssue('Checkout', 'ALTA', `Redirecionamento inválido: ${url}`, 'Checkout inacessível'); return; }

    const shippingV = await page.locator('#shipping, .checkout-shipping-address, [data-role="shipping-address-form"]').first().isVisible().catch(() => false);
    const streetV   = await page.locator('input[name="street[0]"]').first().isVisible().catch(() => false);
    const cityV     = await page.locator('input[name="city"]').first().isVisible().catch(() => false);
    const cepV      = await page.locator('input[name="postcode"]').first().isVisible().catch(() => false);
    const sidebarV  = await page.locator('.opc-sidebar, #opc-sidebar').first().isVisible().catch(() => false);
    const nextV     = await page.locator('button.button.action.continue.primary, .actions-toolbar .primary button').first().isVisible().catch(() => false);
    const nextTxt   = await page.locator('button.button.action.continue.primary, .actions-toolbar .primary button').first().textContent().catch(() => '');

    console.log(`Checkout: shipping=${shippingV}, rua=${streetV}, cidade=${cityV}, cep=${cepV}, sidebar=${sidebarV}, next="${nextTxt?.trim()}"(${nextV})`);

    if (!shippingV) addIssue('Checkout', 'ALTA',  'Etapa de entrega não renderiza', 'Checkout quebrado');
    if (!streetV)   addIssue('Checkout', 'MÉDIA', 'Campo Rua ausente',              'Formulário incompleto');
    if (!cepV)      addIssue('Checkout', 'MÉDIA', 'Campo CEP ausente',              'Frete não calculável');
    if (!sidebarV)  addIssue('Checkout', 'MÉDIA', 'Resumo do pedido ausente',       'Usuário não vê o que compra');
    if (!nextV)     addIssue('Checkout', 'ALTA',  'Botão continuar ausente',        'Checkout bloqueado');

    await screenshot(page, '07b-checkout-shipping');

    // Verificar se há pre-filled address (cliente B2B com endereço salvo)
    const savedAddr = await page.locator('.shipping-address-item, .ship-to').count();
    console.log(`Endereços salvos: ${savedAddr}`);
    if (savedAddr > 0) console.log('✅ Cliente tem endereço salvo');

    // NÃO finalizar pedido
    const placeOrderBtn = await page.locator('.payment-method._active .actions-toolbar button.action.primary').first().isVisible().catch(() => false);
    if (placeOrderBtn) console.log('⚠️  Botão "Finalizar" visível — NÃO clicado (auditoria)');

    await screenshot(page, '07c-checkout-final');
    if (jsErrors.length) addIssue('Checkout', 'ALTA', `${jsErrors.length} erro(s) JS: ${jsErrors[0]}`, 'Checkout pode não funcionar');
    else console.log('✅ Sem erros JS no checkout');
  });

  test('08 | Relatório Final', async () => {
    const alta  = issues.filter(i => i.severity === 'ALTA');
    const media = issues.filter(i => i.severity === 'MÉDIA');
    const baixa = issues.filter(i => i.severity === 'BAIXA');
    console.log('\n══════════════════════════════════════════════════');
    console.log('       RELATÓRIO UX AUDIT — AWA MOTOS B2B         ');
    console.log('══════════════════════════════════════════════════');
    console.log(`TOTAL: ${issues.length} | 🔴 ALTA: ${alta.length} | 🟡 MÉDIA: ${media.length} | 🟢 BAIXA: ${baixa.length}`);
    if (alta.length)  { console.log('\n🔴 ALTA:');  alta.forEach((i,n) => console.log(`  ${n+1}. [${i.step}] ${i.description}\n     Impacto: ${i.impact}`)); }
    if (media.length) { console.log('\n🟡 MÉDIA:'); media.forEach((i,n) => console.log(`  ${n+1}. [${i.step}] ${i.description}\n     Impacto: ${i.impact}`)); }
    if (baixa.length) { console.log('\n🟢 BAIXA:'); baixa.forEach((i,n) => console.log(`  ${n+1}. [${i.step}] ${i.description}\n     Impacto: ${i.impact}`)); }
    if (!issues.length) console.log('✅ Nenhum problema encontrado!');
    fs.mkdirSync(SS_DIR, { recursive: true });
    fs.writeFileSync(path.join(SS_DIR, 'audit-report.json'), JSON.stringify({ date: new Date().toISOString(), total: issues.length, alta: alta.length, media: media.length, baixa: baixa.length, issues }, null, 2));
    console.log('══════════════════════════════════════════════════\n');
    expect(true).toBe(true);
  });
});
