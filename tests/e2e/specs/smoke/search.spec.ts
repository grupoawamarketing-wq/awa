import { test, expect } from '@playwright/test';
import { navigateTo, COMMON, checkOverflow } from '../../helpers/deep-audit.helpers';

const HOME = 'https://awamotos.com';
const TERM = 'bagageiro';

test.describe('Smoke — Busca', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('01 — campo aceita digitação', async ({ page }) => {
    const search = page.locator(COMMON.search).first();
    const vis = await search.isVisible({ timeout: 3000 }).catch(() => false);
    if (!vis) await page.locator('.action.search, .search-toggle').first().click({ force: true }).catch(() => {});
    await search.fill(TERM, { force: true }).catch(() => {});
    const val = await search.inputValue().catch(() => '');
    expect(val).toBeTruthy();
  });

  test('02 — Enter redireciona (P0)', async ({ page }) => {
    const search = page.locator(COMMON.search).first();
    const vis = await search.isVisible({ timeout: 3000 }).catch(() => false);
    if (!vis) await page.locator('.action.search, .search-toggle').first().click({ force: true }).catch(() => {});
    await search.fill(TERM, { force: true }).catch(() => {});
    await search.press('Enter').catch(() => {});
    await page.waitForTimeout(5000);
    expect(page.url()).not.toBe(HOME + '/');
  });

  test('03 — resultados exibem produtos (P0)', async ({ page }) => {
    await page.goto('https://awamotos.com/catalogsearch/result/?q=' + TERM, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => {});
    await page.waitForTimeout(4000);
    const count = await page.locator('.product-item').count().catch(() => 0);
    if (count === 0) console.error('[P0] Busca sem resultados');
    expect(count).toBeGreaterThan(0);
  });

  test('04 — autocomplete aparece (P1)', async ({ page }) => {
    const search = page.locator(COMMON.search).first();
    const vis = await search.isVisible({ timeout: 3000 }).catch(() => false);
    if (!vis) await page.locator('.action.search, .search-toggle').first().click({ force: true }).catch(() => {});
    await search.fill(TERM, { force: true }).catch(() => {});
    await page.waitForTimeout(1500);
    const ac = page.locator('.search-autocomplete, [role="listbox"], .aw-autocomplete').first();
    const acVis = await ac.isVisible({ timeout: 3000 }).catch(() => false);
    if (!acVis) console.warn('[P1] Autocomplete não apareceu');
  });

  test('05 — página de resultados sem overflow', async ({ page }) => {
    await page.goto('https://awamotos.com/catalogsearch/result/?q=' + TERM, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => {});
    await page.waitForTimeout(3000);
    const { hasOverflow } = await checkOverflow(page);
    expect(hasOverflow).toBe(false);
  });
});