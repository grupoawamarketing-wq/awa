import { test, expect } from '@playwright/test';
import { navigateTo, checkOverflow } from '../../helpers/deep-audit.helpers';

const LOGIN = 'https://awamotos.com/customer/account/login/';
const CREATE = 'https://awamotos.com/customer/account/create/';

test.describe('Visual — Formulários', () => {
  test('01 — screenshot login', async ({ page }) => {
    const ok = await navigateTo(page, LOGIN);
    if (!ok) test.skip();
    await expect(page).toHaveScreenshot('login.png', {
      maxDiffPixelRatio: 0.05, animations: 'disabled',
    });
  });

  test('02 — screenshot criar conta', async ({ page }) => {
    const ok = await navigateTo(page, CREATE);
    if (!ok) test.skip();
    await expect(page).toHaveScreenshot('create-account.png', {
      maxDiffPixelRatio: 0.05, animations: 'disabled',
    });
  });

  test('03 — sem overflow login', async ({ page }) => {
    await navigateTo(page, LOGIN);
    const { hasOverflow } = await checkOverflow(page);
    expect(hasOverflow).toBe(false);
  });
});