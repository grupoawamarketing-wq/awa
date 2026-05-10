import { test, expect } from '@playwright/test';
import { navigateTo, collectConsoleErrors, filterCriticalJsErrors, checkOverflow } from '../../helpers/deep-audit.helpers';

const LOGIN = 'https://awamotos.com/customer/account/login/';
const B2B_LOGIN = 'https://awamotos.com/b2b/account/login/';

test.describe('Smoke — Login', () => {
  test('01 — página de login carrega', async ({ page }) => {
    const ok = await navigateTo(page, LOGIN);
    if (!ok) test.skip();
    const form = page.locator('#login-form, .b2b-login-form, .login-container, .page-main').first();
    await expect(form).toBeVisible({ timeout: 15000 });
  });

  test('02 — campos email e senha', async ({ page }) => {
    await navigateTo(page, B2B_LOGIN);
    const email = page.locator('input[type="email"], input#email, input#b2b-email').first();
    const pass = page.locator('input[type="password"], input#pass, input#b2b-pass').first();
    await expect(email).toBeVisible({ timeout: 10000 });
    await expect(pass).toBeVisible({ timeout: 5000 });
  });

  test('03 — botão submit', async ({ page }) => {
    await navigateTo(page, B2B_LOGIN);
    const btn = page.locator('button[type="submit"], .action.login, .b2b-btn-entrar').first();
    await expect(btn).toBeVisible({ timeout: 10000 });
  });

  test('04 — link criar conta', async ({ page }) => {
    await navigateTo(page, LOGIN);
    const link = page.locator('a[href*="create"], a[href*="register"], a[href*="cadastr"]').first();
    const vis = await link.isVisible({ timeout: 5000 }).catch(() => false);
    if (!vis) console.warn('[P2] Link criar conta não visível');
  });

  test('05 — sem JS errors (P0)', async ({ page }) => {
    const errors = collectConsoleErrors(page);
    await navigateTo(page, LOGIN);
    await page.waitForTimeout(3000);
    const critical = filterCriticalJsErrors(errors);
    expect(critical).toHaveLength(0);
  });

  test('06 — sem overflow', async ({ page }) => {
    await navigateTo(page, LOGIN);
    const { hasOverflow } = await checkOverflow(page);
    expect(hasOverflow).toBe(false);
  });
});