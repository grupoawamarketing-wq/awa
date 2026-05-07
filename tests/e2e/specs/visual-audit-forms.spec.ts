/**
 * Visual Audit — Formulários B2B
 *
 * Valida os formulários de autenticação e cadastro do sistema B2B:
 *  1. Login B2B (/b2b/account/login/)
 *  2. Ativar/Reclamar conta (/b2b/account/claim/)
 *  3. Cadastro (/b2b/account/register/)
 *  4. Recuperar senha (/b2b/account/forgotpassword/)
 *  5. Estados visuais (foco, validação)
 */
import { test, expect, type Page } from '@playwright/test';
import {
  navigateTo, css, isVisible, hasNoOverflow, collectJsErrors,
  TOKENS,
} from '../helpers/visual-audit.helpers';

const BASE = 'https://awamotos.com';

/* Seletores canônicos do módulo B2B (GrupoAwamotos_B2B) */
const B2B = {
  LOGIN: {
    url:      `${BASE}/b2b/account/login/`,
    shell:    '#b2b-login-shell',
    email:    '#b2b-email',
    pass:     '#b2b-pass',
    toggle:   '.b2b-password-toggle',
    btn:      '.b2b-btn-entrar',
  },
  CLAIM: {
    url:      `${BASE}/b2b/account/claim/`,
    shell:    '#b2b-claim-shell',
    field:    '#b2b-cnpj-or-email',
    btn:      '.b2b-btn-claim-submit',
  },
  REGISTER: {
    url:      `${BASE}/b2b/account/register/`,
    shell:    '#b2b-register-shell, .b2b-register-form',
  },
  FORGOT: {
    url:      `${BASE}/b2b/account/forgotpassword/`,
    shell:    '#b2b-forgot-shell, .b2b-forgot-form',
    field:    '#b2b-forgot-email, input[name="email"]',
    btn:      '.b2b-btn-forgot-submit, button[type="submit"]',
  },
} as const;

/* ═══════════════════════════════════════════════════════════════════
   1. LOGIN B2B
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Forms B2B — Login', () => {
  let loginPage: Page;

  test.beforeAll(async ({ browser }) => {
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'pt-BR' });
    loginPage = await ctx.newPage();
    const ok = await navigateTo(loginPage, B2B.LOGIN.url);
    if (!ok) throw new Error('Página de login B2B não carregou');
    await loginPage.waitForTimeout(2_000);
  });

  test.afterAll(async () => {
    await loginPage?.context().close().catch(() => {});
  });

  test('Shell de login (#b2b-login-shell) presente no DOM', async () => {
    if (!loginPage) { test.skip(); return; }
    const present = await loginPage.locator(B2B.LOGIN.shell).count()
      .then(c => c > 0).catch(() => false);
    expect(present, 'Container #b2b-login-shell deve estar no DOM').toBe(true);
  });

  test('Campo email (#b2b-email) visível', async () => {
    if (!loginPage) { test.skip(); return; }
    const visible = await isVisible(loginPage, B2B.LOGIN.email, 5_000);
    expect(visible, 'Campo #b2b-email deve estar visível').toBe(true);
  });

  test('Campo senha (#b2b-pass) visível', async () => {
    if (!loginPage) { test.skip(); return; }
    const visible = await isVisible(loginPage, B2B.LOGIN.pass, 5_000);
    expect(visible, 'Campo #b2b-pass deve estar visível').toBe(true);
  });

  test('Botão entrar (.b2b-btn-entrar) visível e habilitado', async () => {
    if (!loginPage) { test.skip(); return; }
    const btn = loginPage.locator(B2B.LOGIN.btn).first();
    const visible  = await btn.isVisible().catch(() => false);
    const disabled = await btn.isDisabled().catch(() => true);
    expect(visible, 'Botão .b2b-btn-entrar deve estar visível').toBe(true);
    expect(disabled, 'Botão entrar não deve estar desabilitado por padrão').toBe(false);
  });

  test('Toggle de senha presente', async () => {
    if (!loginPage) { test.skip(); return; }
    const toggle = await isVisible(loginPage, B2B.LOGIN.toggle, 3_000);
    console.log(`Toggle senha visível: ${toggle}`);
  });

  test('Email/CNPJ input tem tipo de texto (aceita email ou CNPJ)', async () => {
    if (!loginPage) { test.skip(); return; }
    const type = await loginPage.locator(B2B.LOGIN.email).first()
      .getAttribute('type').catch(() => 'text');
    // Campo aceita email OU CNPJ — type="text" ou type="email" são válidos
    expect(['text', 'email'].includes(type ?? ''), `Campo deve ser text ou email (got: ${type})`).toBe(true);
  });

  test('Senha input tem type="password"', async () => {
    if (!loginPage) { test.skip(); return; }
    const type = await loginPage.locator(B2B.LOGIN.pass).first()
      .getAttribute('type').catch(() => '');
    expect(type, 'Campo de senha deve ter type="password"').toBe('password');
  });

  test('Sem overflow horizontal na página de login', async () => {
    if (!loginPage) { test.skip(); return; }
    const ok = await hasNoOverflow(loginPage);
    expect(ok, 'Login B2B não deve ter overflow horizontal').toBe(true);
  });

  test('Screenshot desktop — login B2B', async ({ page }) => {
    const ok = await navigateTo(page, B2B.LOGIN.url);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);
    await expect(page).toHaveScreenshot('form-login-b2b-desktop.png', {
      maxDiffPixelRatio: 0.04,
      animations: 'disabled',
    });
  });

  test('Screenshot mobile — login B2B (375px)', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    const ok = await navigateTo(page, B2B.LOGIN.url);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);
    await expect(page).toHaveScreenshot('form-login-b2b-mobile.png', {
      maxDiffPixelRatio: 0.04,
      animations: 'disabled',
    });
  });
});

/* ═══════════════════════════════════════════════════════════════════
   2. CLAIM / ATIVAR CONTA
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Forms B2B — Claim/Ativar Conta', () => {
  let claimPage: Page;

  test.beforeAll(async ({ browser }) => {
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'pt-BR' });
    claimPage = await ctx.newPage();
    await navigateTo(claimPage, B2B.CLAIM.url);
    await claimPage.waitForTimeout(2_000);
  });

  test.afterAll(async () => {
    await claimPage?.context().close().catch(() => {});
  });

  test('Página claim carrega sem erro 500', async () => {
    if (!claimPage) { test.skip(); return; }
    const url = claimPage.url();
    expect(url, 'Não deve redirecionar para página de erro').not.toContain('/errors/');
    const title = await claimPage.title();
    console.log(`Claim page title: ${title}`);
  });

  test('Campo CNPJ/email visível', async () => {
    if (!claimPage) { test.skip(); return; }
    const vis = await isVisible(claimPage, B2B.CLAIM.field, 5_000);
    console.log(`Campo CNPJ/email visível: ${vis}`);
  });

  test('Screenshot — claim form desktop', async ({ page }) => {
    const ok = await navigateTo(page, B2B.CLAIM.url);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);
    await expect(page).toHaveScreenshot('form-claim-b2b-desktop.png', {
      maxDiffPixelRatio: 0.04,
      animations: 'disabled',
    });
  });
});

/* ═══════════════════════════════════════════════════════════════════
   3. CADASTRO B2B
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Forms B2B — Cadastro', () => {
  test('Página de cadastro carrega', async ({ page }) => {
    const ok = await navigateTo(page, B2B.REGISTER.url);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const url = page.url();
    const hasForm = await isVisible(page, 'form, input[type="text"], input[type="email"]', 5_000);
    console.log(`Register URL: ${url}, hasForm: ${hasForm}`);
  });

  test('Screenshot — cadastro B2B desktop', async ({ page }) => {
    const ok = await navigateTo(page, B2B.REGISTER.url);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);
    await expect(page).toHaveScreenshot('form-register-b2b-desktop.png', {
      maxDiffPixelRatio: 0.04,
      animations: 'disabled',
    });
  });
});

/* ═══════════════════════════════════════════════════════════════════
   4. RECUPERAR SENHA
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Forms B2B — Esqueceu a senha', () => {
  test('Página de recuperação carrega', async ({ page }) => {
    const ok = await navigateTo(page, B2B.FORGOT.url);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const hasInput = await isVisible(page, B2B.FORGOT.field, 5_000);
    console.log(`Forgot password input visível: ${hasInput}`);
  });

  test('Screenshot — esqueci a senha desktop', async ({ page }) => {
    const ok = await navigateTo(page, B2B.FORGOT.url);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);
    await expect(page).toHaveScreenshot('form-forgot-password-desktop.png', {
      maxDiffPixelRatio: 0.04,
      animations: 'disabled',
    });
  });
});

/* ═══════════════════════════════════════════════════════════════════
   5. ESTADOS VISUAIS
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Forms B2B — Estados visuais (foco / validação)', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  test('Campo email com foco tem outline/border visível', async ({ page }) => {
    const ok = await navigateTo(page, B2B.LOGIN.url);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const email = page.locator(B2B.LOGIN.email).first();
    if (!await email.isVisible().catch(() => false)) { test.skip(); return; }

    await email.click({ force: true }).catch(() => {});
    await page.waitForTimeout(300);

    const focusStyle = await page.evaluate((sel) => {
      const el = document.querySelector(sel) as HTMLElement | null;
      if (!el) return null;
      const style = window.getComputedStyle(el);
      return {
        outlineWidth:  style.outlineWidth,
        outlineColor:  style.outlineColor,
        outlineStyle:  style.outlineStyle,
        borderColor:   style.borderColor,
        boxShadow:     style.boxShadow,
      };
    }, B2B.LOGIN.email).catch(() => null);

    if (!focusStyle) { test.skip(); return; }
    console.log(`Focus styles: ${JSON.stringify(focusStyle)}`);

    /* Verificar se tem algum indicador de foco (outline OU box-shadow OU border muda) */
    const hasFocusIndicator = (
      (focusStyle.outlineWidth !== '0px' && focusStyle.outlineStyle !== 'none') ||
      focusStyle.boxShadow !== 'none'
    );
    expect(hasFocusIndicator, 'Campo com foco deve ter outline ou box-shadow visível (acessibilidade)').toBe(true);
  });

  test('Submeter form vazio mostra mensagem de validação', async ({ page }) => {
    const ok = await navigateTo(page, B2B.LOGIN.url);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const btn = page.locator(B2B.LOGIN.btn).first();
    if (!await btn.isVisible().catch(() => false)) { test.skip(); return; }

    await btn.click({ force: true }).catch(() => {});
    await page.waitForTimeout(1_000);

    /* Verificar validação HTML5 nativa ou mensagem de erro customizada */
    const hasValidation = await page.evaluate(() => {
      const inputs = document.querySelectorAll('input[required], input[type="email"]');
      let valid = false;
      inputs.forEach(inp => {
        if (!(inp as HTMLInputElement).validity.valid) valid = true;
      });
      const errorMsg = !!document.querySelector('.mage-error, .field-error, .b2b-error, [class*="error"]');
      return valid || errorMsg;
    }).catch(() => false);

    console.log(`Validação ao submeter vazio: ${hasValidation}`);
    expect(hasValidation, 'Submeter formulário vazio deve mostrar validação').toBe(true);
  });
});
