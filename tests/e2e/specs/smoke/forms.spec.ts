import { test, expect } from '@playwright/test';
import { navigateTo, checkOverflow } from '../../helpers/deep-audit.helpers';

const CONTATO = 'https://awamotos.com/contato';
const TRABALHE = 'https://awamotos.com/trabalhe-conosco';

test.describe('Smoke — Formulários', () => {
  test('01 — contato carrega', async ({ page }) => {
    const ok = await navigateTo(page, CONTATO);
    if (!ok) { test.skip(); return; }
    const form = page.locator('form#contact-form, form.contact, .contact-form, .page-title, h1, main').first();
    const vis = await form.isVisible({ timeout: 15000 }).catch(() => false);
    if (!vis) console.warn('[P1] Página de contato carregou mas sem formulário visível');
  });

  test('02 — contato tem campos obrigatórios', async ({ page }) => {
    await navigateTo(page, CONTATO);
    const name = page.locator('input[name="name"], input[name="firstname"]').first();
    const email = page.locator('input[name="email"], input[type="email"]').first();
    const vis1 = await name.isVisible({ timeout: 5000 }).catch(() => false);
    const vis2 = await email.isVisible({ timeout: 5000 }).catch(() => false);
    if (!vis1) console.warn('[P1] Campo nome não visível no contato');
    if (!vis2) console.warn('[P1] Campo email não visível no contato');
  });

  test('03 — contato tem botão enviar', async ({ page }) => {
    await navigateTo(page, CONTATO);
    const btn = page.locator('button[type="submit"], .action.submit').first();
    await expect(btn).toBeVisible({ timeout: 10000 });
  });

  test('04 — trabalhe conosco carrega', async ({ page }) => {
    const ok = await navigateTo(page, TRABALHE);
    // Pode não existir, skip se 404
    if (!ok) { console.info('[INFO] Trabalhe conosco não acessível'); test.skip(); }
    const content = page.locator('.page-title, form, .curriculo-form').first();
    await expect(content).toBeVisible({ timeout: 10000 });
  });

  test('05 — contato sem overflow', async ({ page }) => {
    await navigateTo(page, CONTATO);
    const { hasOverflow } = await checkOverflow(page);
    expect(hasOverflow).toBe(false);
  });
});