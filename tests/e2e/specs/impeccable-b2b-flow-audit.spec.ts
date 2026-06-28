/**
 * Impeccable — Auditoria profunda de fluxo B2B (Fase 2)
 * Objetivo:
 *  - validar login/estado guest + logado
 *  - validar módulos administrativos B2B
 *  - validar trilha catálogo -> carrinho -> checkout (sem concluir pagamento real por padrão)
 */
import { test, expect, type Page, type Response } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import {
  isVisible,
  loginB2B,
  dismissCookie,
} from '../helpers/visual-audit.helpers';
import {
  collectConsoleErrors,
  collectNetworkErrors,
  filterCriticalJsErrors,
  filter404s,
  filter500s,
  hasNoOverflow,
} from '../helpers/deep-audit.helpers';

type Severity = 'P0' | 'P1' | 'P2' | 'P3';

type DeviceViewport = { label: string; width: number; height: number };

interface Issue {
  phase: 'FASE_2_B2B';
  severity: Severity;
  route: string;
  viewport: string;
  component: string;
  step: string;
  description: string;
  evidence: string;
}

const BASE_URL = process.env.AWA_BASE_URL || 'https://awamotos.com';
const TARGET_ROUTES = (process.env.IMPECCABLE_B2B_ROUTES || '').split(',').map((value) => value.trim()).filter(Boolean);
const TARGET_VIEWPORTS = (process.env.IMPECCABLE_B2B_VIEWPORTS || '').split(',').map((value) => value.trim()).filter(Boolean);
const SAVE_SCREENSHOTS = process.env.IMPECCABLE_KEEP_SCREENSHOTS !== '0';
const TEST_USER = process.env.TEST_USER || '';
const TEST_PASS = process.env.TEST_PASS || '';
const ALLOW_CHECKOUT_SUCCESS = process.env.ALLOW_CHECKOUT_SUCCESS === '1';
const REPORT_DIR = path.join(__dirname, '../reports');
const SCREENSHOT_DIR = path.join(__dirname, '../screenshots/impeccable-b2b-flow-audit');
const REPORT_FILE = path.join(REPORT_DIR, 'impeccable-b2b-flow-audit.json');

const VIEWPORTS: DeviceViewport[] = [
  { label: '390x844', width: 390, height: 844 },
  { label: '768x1024', width: 768, height: 1024 },
  { label: '1366x768', width: 1366, height: 768 },
];

const B2B_PAGES = [
  '/b2b/account/login/',
  '/b2b/account/dashboard/',
  '/b2b/shoppinglist/',
  '/b2b/quote/',
  '/b2b/reorder/history/',
  '/sales/order/history/',
  '/customer/address/',
];

const TARGET_PAGES = TARGET_ROUTES.length
  ? B2B_PAGES.filter((route) => TARGET_ROUTES.includes(route))
  : B2B_PAGES;

const B2B_VIEWPORTS = TARGET_VIEWPORTS.length
  ? VIEWPORTS.filter((viewport) => TARGET_VIEWPORTS.includes(viewport.label))
  : VIEWPORTS;

const B2B_PUBLIC_TEST_PRODUCT = '/bagageiro-titan-125-modelo-05-08-fan-125-modelo-07-08-fumacado-3039.html';

const issues: Issue[] = [];

function addIssue(issue: Issue): void {
  issues.push(issue);
  console.warn(`[${issue.severity}] ${issue.route} [${issue.viewport}] ${issue.component} / ${issue.step}: ${issue.description}`);
}

function ensureDirs(): void {
  fs.mkdirSync(REPORT_DIR, { recursive: true });
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

function toSafeFileName(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-zA-Z0-9_-]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
    .toLowerCase();
}

async function screenshot(page: Page, name: string): Promise<string> {
  if (!SAVE_SCREENSHOTS) {
    return `${name}.skipped.png`;
  }

  ensureDirs();
  const fileName = `${toSafeFileName(name)}.png`;
  const filePath = path.join(SCREENSHOT_DIR, fileName);
  await page.screenshot({ path: filePath, fullPage: true, timeout: 45_000 }).catch(() => {});
  return filePath;
}

async function openRoute(page: Page, route: string): Promise<{ response: Response | null; status: number | null; finalUrl: string }> {
  const response = await page.goto(`${BASE_URL}${route}`, { waitUntil: 'domcontentloaded', timeout: 80_000 }).catch(() => null);
  await page.waitForLoadState('domcontentloaded').catch(() => {});
  await dismissCookie(page);
  return {
    response,
    status: response ? response.status() : null,
    finalUrl: page.url(),
  };
}

async function auditRouteCommon(route: string, page: Page, viewport: DeviceViewport): Promise<void> {
  const consoleErrors = collectConsoleErrors(page);
  const networkErrors = collectNetworkErrors(page);

  const opened = await openRoute(page, route);
  await page.waitForTimeout(1_500);

  if (opened.status === null) {
    addIssue({
      phase: 'FASE_2_B2B',
      severity: 'P0',
      route,
      viewport: viewport.label,
      component: 'Navegação',
      step: 'goto',
      description: 'Falha ao carregar rota',
      evidence: `final=${opened.finalUrl}`,
    });
    return;
  }

  const hasMain = await isVisible(page, 'main, #maincontent, .page-main, .columns', 8_000);
  if (!hasMain && !route.includes('account/login')) {
    addIssue({
      phase: 'FASE_2_B2B',
      severity: 'P2',
      route,
      viewport: viewport.label,
      component: 'Layout',
      step: 'main-content',
      description: 'container principal não visível',
      evidence: `status=${opened.status}`,
    });
  }

  const noOverflow = await hasNoOverflow(page);
  if (!noOverflow) {
    addIssue({
      phase: 'FASE_2_B2B',
      severity: 'P2',
      route,
      viewport: viewport.label,
      component: 'Layout',
      step: 'overflow',
      description: 'overflow horizontal detectado',
      evidence: `route=${route}`,
    });
  }

  const pageerrors = filterCriticalJsErrors(consoleErrors);
  if (pageerrors.length > 0) {
    addIssue({
      phase: 'FASE_2_B2B',
      severity: pageerrors.length >= 3 ? 'P1' : 'P2',
      route,
      viewport: viewport.label,
      component: 'JS',
      step: 'pageerror',
      description: `${pageerrors.length} erro(s) JS crítico(s)`,
      evidence: pageerrors.map((item) => item.text).join(' | '),
    });
  }

  const networkCritical = [...filter404s(networkErrors), ...filter500s(networkErrors)];
  if (networkCritical.length > 0) {
    const first = networkCritical[0];
    addIssue({
      phase: 'FASE_2_B2B',
      severity: first.status === 500 ? 'P1' : 'P2',
      route,
      viewport: viewport.label,
      component: 'Rede',
      step: `HTTP_${first.status}`,
      description: 'Falha de requisição de rede durante a rota',
      evidence: first.url,
    });
  }
}

async function ensureLoggedInB2B(page: Page, viewport: DeviceViewport): Promise<boolean> {
  if (!TEST_USER || !TEST_PASS) {
    addIssue({
      phase: 'FASE_2_B2B',
      severity: 'P1',
      route: '/b2b/account/login/',
      viewport: viewport.label,
      component: 'Auth',
      step: 'credenciais',
      description: 'Variáveis TEST_USER/TEST_PASS ausentes no ambiente',
      evidence: 'LOGIN_BYPASS',
    });
    return false;
  }

  await loginB2B(page);
  await page.waitForTimeout(1_200);
  const isLoginPage = await page.url().includes('/b2b/account/login/');
  const guestHint = await isVisible(page, '#b2b-email, #b2b-pass, .b2b-login-shell', 4_000);
  if (isLoginPage || guestHint) {
    addIssue({
      phase: 'FASE_2_B2B',
      severity: 'P1',
      route: '/b2b/account/login/',
      viewport: viewport.label,
      component: 'Auth',
      step: 'login',
      description: 'Login B2B não confirmou sessão autenticada',
      evidence: `url=${page.url()}`,
    });
    return false;
  }

  return true;
}

test.describe('B2B — auditoria de fluxo ponta a ponta (comportamento preservado)', () => {
  test.setTimeout(900_000);

  test('01 | Estado guest no login B2B', async ({ page }) => {
    for (const viewport of B2B_VIEWPORTS) {
      await test.step(`${viewport.label}`, async () => {
        await page.setViewportSize(viewport);
        await openRoute(page, '/b2b/account/login/');
        await page.waitForTimeout(1_000);

        await screenshot(page, `b2b-login-guest-${viewport.label}`);
        await auditRouteCommon('/b2b/account/login/', page, viewport);

        const visibleEmail = await isVisible(page, '#b2b-email, #email, input[name="login[username]"]', 5_000);
        const visiblePass = await isVisible(page, '#b2b-pass, #pass, input[name="login[password]"]', 5_000);
        const visibleBtn = await isVisible(page, '.b2b-btn-entrar, #send2, button[type="submit"]', 5_000);

        if (!visibleEmail || !visiblePass || !visibleBtn) {
          addIssue({
            phase: 'FASE_2_B2B',
            severity: 'P1',
            route: '/b2b/account/login/',
            viewport: viewport.label,
            component: 'Formulario',
            step: 'campos',
            description: 'Campos críticos de login B2B ausentes',
            evidence: `email=${visibleEmail} pass=${visiblePass} btn=${visibleBtn}`,
          });
        }

        await page.locator('#b2b-email, #email, input[name="login[username]"]').first().fill(TEST_USER).catch(() => {});
        await page.locator('#b2b-pass, #pass, input[name="login[password]"]').first().fill(TEST_PASS).catch(() => {});
        await screenshot(page, `b2b-login-filled-${viewport.label}`);
      });
    }
  });

  test('02 | Login B2B e dashboard', async ({ page }) => {
    const viewport = B2B_VIEWPORTS[0] ?? B2B_VIEWPORTS[1] ?? B2B_VIEWPORTS[2] ?? VIEWPORTS[2];
    await page.setViewportSize(viewport);

    const logged = await ensureLoggedInB2B(page, viewport);
    if (!logged) {
      return;
    }

    await page.waitForTimeout(1_200);
    const dashboardOpen = await openRoute(page, '/b2b/account/dashboard/');
    await page.waitForTimeout(1_000);
    await screenshot(page, `b2b-dashboard-${viewport.label}`);

    if (dashboardOpen.status !== null && dashboardOpen.status >= 300 && dashboardOpen.status < 400) {
      addIssue({
        phase: 'FASE_2_B2B',
        severity: 'P1',
        route: '/b2b/account/dashboard/',
        viewport: viewport.label,
        component: 'Navegação',
        step: 'dashboard',
        description: `Dashboard retornou redirecionamento não esperado (${dashboardOpen.status})`,
        evidence: `final=${dashboardOpen.finalUrl}`,
      });
    }

    if (!dashboardOpen.finalUrl.includes('/b2b/account/dashboard')) {
      addIssue({
        phase: 'FASE_2_B2B',
        severity: 'P1',
        route: '/b2b/account/dashboard/',
        viewport: viewport.label,
        component: 'Auth',
        step: 'guard',
        description: 'Dashboard não permaneceu no escopo B2B autenticado',
        evidence: `final=${dashboardOpen.finalUrl}`,
      });
      return;
    }

    const navPresence = await isVisible(page, '.b2b-nav, .account-nav, .customer-account-navigation', 5_000);
    if (!navPresence) {
      addIssue({
        phase: 'FASE_2_B2B',
        severity: 'P2',
        route: '/b2b/account/dashboard/',
        viewport: viewport.label,
        component: 'Navegação',
        step: 'sidebar',
        description: 'Menu/account-nav B2B não encontrado',
        evidence: dashboardOpen.finalUrl,
      });
    }

    await auditRouteCommon('/b2b/account/dashboard/', page, viewport);
  });

  test('03 | Rotas administrativas B2B', async ({ page }) => {
    const viewport = B2B_VIEWPORTS[0] ?? B2B_VIEWPORTS[1] ?? B2B_VIEWPORTS[2] ?? VIEWPORTS[2];
    await page.setViewportSize(viewport);

    const logged = await ensureLoggedInB2B(page, viewport);
    if (!logged) {
      return;
    }

    const pagesToAudit = TARGET_PAGES.length ? TARGET_PAGES : B2B_PAGES;
    for (const route of pagesToAudit.slice(1)) {
      await test.step(route, async () => {
        const opened = await openRoute(page, route);
        await page.waitForTimeout(1_300);
        await screenshot(page, `b2b-route-${route.replace(/\//g, '-')}-${viewport.label}`);

        if (opened.status === null) {
          addIssue({
            phase: 'FASE_2_B2B',
            severity: 'P0',
            route,
            viewport: viewport.label,
            component: 'HTTP',
            step: 'goto',
            description: 'Página administrativa não carregou',
            evidence: `final=${opened.finalUrl}`,
          });
          return;
        }

        if (!opened.finalUrl.includes('/b2b/') && !opened.finalUrl.includes('/sales/order')) {
          addIssue({
            phase: 'FASE_2_B2B',
            severity: 'P1',
            route,
            viewport: viewport.label,
            component: 'Auth',
            step: 'redirecionamento',
            description: 'Rota redirecionou fora do escopo B2B',
            evidence: `final=${opened.finalUrl}`,
          });
        }

        const hasBreadcrumb = await isVisible(page, '.breadcrumbs, .page-title, .dashboard, .page-header', 5_000);
        if (!hasBreadcrumb) {
          addIssue({
            phase: 'FASE_2_B2B',
            severity: 'P2',
            route,
            viewport: viewport.label,
            component: 'Layout',
            step: 'header/page-title',
            description: 'Cabeçalho/padrão de página não identificado',
            evidence: `status=${opened.status}`,
          });
        }

        await auditRouteCommon(route, page, viewport);
      });
    }
  });

  test('04 | Fluxo PDP -> carrinho -> checkout', async ({ page }) => {
    const viewport = B2B_VIEWPORTS[0] ?? B2B_VIEWPORTS[1] ?? B2B_VIEWPORTS[2] ?? VIEWPORTS[2];
    await page.setViewportSize(viewport);

    const logged = await ensureLoggedInB2B(page, viewport);
    if (!logged) {
      return;
    }

    await openRoute(page, '/bagageiros.html');
    await page.waitForTimeout(2_000);
    const firstProduct = page.locator('.item-product a.product-item-link, .product-item-link').first();
    const canUseDirectPdp = await firstProduct.isVisible().catch(() => false);

    if (!canUseDirectPdp) {
      addIssue({
        phase: 'FASE_2_B2B',
        severity: 'P2',
        route: '/bagageiros.html',
        viewport: viewport.label,
        component: 'Catálogo',
        step: 'catálogo',
        description: 'PLP sem produto clicável para iniciar jornada',
        evidence: 'Não foi encontrado .product-item-link',
      });
      return;
    }

    await firstProduct.click();
    await page.waitForTimeout(2_000);
    await screenshot(page, `b2b-pdp-opened-${viewport.label}`);

    const atcVisible = await isVisible(page, '#product-addtocart-button, .action.tocart, .b2b-add-to-cart', 6_000);
    const addBtn = page.locator('#product-addtocart-button, .action.tocart, .b2b-add-to-cart').first();
    const addDisabled = atcVisible ? await addBtn.isDisabled().catch(() => true) : true;

    if (!atcVisible) {
      addIssue({
        phase: 'FASE_2_B2B',
        severity: 'P2',
        route: B2B_PUBLIC_TEST_PRODUCT,
        viewport: viewport.label,
        component: 'PDP',
        step: 'addtocart',
        description: 'Botão de adicionar ao carrinho não visível',
        evidence: page.url(),
      });
    } else if (addDisabled) {
      addIssue({
        phase: 'FASE_2_B2B',
        severity: 'P1',
        route: B2B_PUBLIC_TEST_PRODUCT,
        viewport: viewport.label,
        component: 'PDP',
        step: 'addtocart',
        description: 'Botão adicionar ao carrinho está desabilitado',
        evidence: page.url(),
      });
    } else {
      await addBtn.click();
      await page.waitForTimeout(2_500);
      await screenshot(page, `b2b-pdp-after-add-${viewport.label}`);

      const qtyInput = page.locator('input[name="qty"], .qty').first();
      const qtyValue = await qtyInput.inputValue().catch(() => '');
      if (!qtyValue) {
        addIssue({
          phase: 'FASE_2_B2B',
          severity: 'P2',
          route: B2B_PUBLIC_TEST_PRODUCT,
          viewport: viewport.label,
          component: 'PDP',
          step: 'qtd',
          description: 'Campo de quantidade sem valor padrão',
          evidence: 'qty input vazio',
        });
      }
    }

    await openRoute(page, '/checkout/cart/');
    await page.waitForTimeout(1_200);
    const hasCartRows = await isVisible(page, '.cart.item, .cart-item, .items', 8_000);
    if (!hasCartRows) {
      addIssue({
        phase: 'FASE_2_B2B',
        severity: 'P1',
        route: '/checkout/cart/',
        viewport: viewport.label,
        component: 'Carrinho',
        step: 'itens',
        description: 'Carrinho não exibe itens após tentativa de adicionar',
        evidence: page.url(),
      });
    }
    await screenshot(page, `b2b-cart-${viewport.label}`);

    const checkoutBtn = page.locator('.checkout-methods-items .action.primary.checkout, .action.primary.checkout').first();
    const checkoutVisible = await checkoutBtn.isVisible().catch(() => false);
    if (!checkoutVisible) {
      addIssue({
        phase: 'FASE_2_B2B',
        severity: 'P1',
        route: '/checkout/cart/',
        viewport: viewport.label,
        component: 'Carrinho',
        step: 'checkout',
        description: 'Botão principal de checkout não encontrado no carrinho',
        evidence: 'Fluxo interrompido antes da tela de pagamento',
      });
      return;
    }

    await checkoutBtn.click().catch(() => {});
    await page.waitForTimeout(3_000);
    await screenshot(page, `b2b-checkout-${viewport.label}`);

    const hasShipping = await isVisible(page, '#shipping, .shipping-address-items, .checkout-shipping-address', 8_000);
    const hasPayment = await isVisible(page, '.payment-methods, .opc-payment', 8_000);
    const hasReview = await isVisible(page, '.order-summary, .summary', 8_000);

    if (!hasShipping) {
      addIssue({
        phase: 'FASE_2_B2B',
        severity: 'P2',
        route: '/checkout/',
        viewport: viewport.label,
        component: 'Checkout',
        step: 'shipping',
        description: 'Seção de entrega não visível',
        evidence: page.url(),
      });
    }

    const placeOrderBtn = page.locator('.place-order-primary .action.primary, .action-primary button, button[type="submit"]').first();
    const hasPlaceBtn = await placeOrderBtn.isVisible().catch(() => false);

    if (ALLOW_CHECKOUT_SUCCESS) {
      if (hasPlaceBtn) {
        await placeOrderBtn.click().catch(() => {});
        await page.waitForTimeout(5_000);
        if (page.url().includes('/checkout/success/')) {
          await screenshot(page, `b2b-checkout-success-${viewport.label}`);
          return;
        }

        addIssue({
          phase: 'FASE_2_B2B',
          severity: 'P2',
          route: '/checkout/',
          viewport: viewport.label,
          component: 'Checkout',
          step: 'success',
          description: 'Não houve transição para /checkout/success com segurança habilitada',
          evidence: `url=${page.url()}`,
        });
      } else {
        addIssue({
          phase: 'FASE_2_B2B',
          severity: 'P2',
          route: '/checkout/',
          viewport: viewport.label,
          component: 'Checkout',
          step: 'place-order',
          description: 'Botão final de compra não disponível',
          evidence: page.url(),
        });
      }
    } else {
      if (hasPlaceBtn) {
        addIssue({
          phase: 'FASE_2_B2B',
          severity: 'P1',
          route: '/checkout/',
          viewport: viewport.label,
          component: 'Checkout',
          step: 'bloqueio-seguro',
          description: 'Checkout chega a etapa de finalização, mas não clicado por política sem pagamento real',
          evidence: 'Define ALLOW_CHECKOUT_SUCCESS=1 para concluir com bloqueio explícito',
        });
      } else {
        addIssue({
          phase: 'FASE_2_B2B',
          severity: 'P2',
          route: '/checkout/',
          viewport: viewport.label,
          component: 'Checkout',
          step: 'checkout-incompleto',
          description: 'Sem botão de finalização visível para concluir política de teste',
          evidence: page.url(),
        });
      }
    }

    if (!hasPayment || !hasReview) {
      addIssue({
        phase: 'FASE_2_B2B',
        severity: hasPayment && hasReview ? 'P3' : 'P2',
        route: '/checkout/',
        viewport: viewport.label,
        component: 'Checkout',
        step: 'estrutura',
        description: 'Seções de pagamento/resumo não completas no fluxo',
        evidence: `shipping=${hasShipping} payment=${hasPayment} review=${hasReview}`,
      });
    }

    await auditRouteCommon('/checkout/', page, viewport);
  });
});

test.afterAll(() => {
  ensureDirs();
  const bySeverity = {
    P0: issues.filter((issue) => issue.severity === 'P0').length,
    P1: issues.filter((item) => item.severity === 'P1').length,
    P2: issues.filter((item) => item.severity === 'P2').length,
    P3: issues.filter((item) => item.severity === 'P3').length,
  };
  const payload = {
    generatedAt: new Date().toISOString(),
    baseUrl: BASE_URL,
    mode: {
      allowCheckoutSuccess: ALLOW_CHECKOUT_SUCCESS,
    },
    totalIssues: issues.length,
    bySeverity,
    issues,
  };
  fs.writeFileSync(REPORT_FILE, JSON.stringify(payload, null, 2));
  console.log('\n════════ AUDIT IMPERCCABLE: B2B FLOW AUDIT ════════');
  console.log(`Relatório gravado em: ${REPORT_FILE}`);
  console.log(`Snapshots: ${SCREENSHOT_DIR}`);
  console.log(`Total de issues: ${issues.length} (P0=${bySeverity.P0}, P1=${bySeverity.P1}, P2=${bySeverity.P2}, P3=${bySeverity.P3})`);
  if (!issues.length) {
    console.log('✅ Sem problemas registrados nesta bateria de fluxo B2B.');
  }
});
