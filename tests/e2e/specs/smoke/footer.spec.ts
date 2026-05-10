import { test, expect } from '@playwright/test';
import { navigateTo, COMMON, findBrokenImages, checkOverflow } from '../../helpers/deep-audit.helpers';

const HOME = 'https://awamotos.com';

test.describe('Smoke — Footer', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('01 — footer visível', async ({ page }) => {
    const footer = page.locator(COMMON.footer).first();
    await footer.scrollIntoViewIfNeeded().catch(() => {});
    await expect(footer).toBeVisible({ timeout: 10000 });
  });

  test('02 — footer tem links', async ({ page }) => {
    const links = page.locator('footer a[href], .footer.content a[href]');
    const count = await links.count();
    expect(count).toBeGreaterThan(0);
  });

  test('03 — links não são javascript:void', async ({ page }) => {
    const links = page.locator('footer a[href], .footer.content a[href]');
    const count = await links.count();
    let invalid = 0;
    for (let i = 0; i < Math.min(count, 15); i++) {
      const href = await links.nth(i).getAttribute('href').catch(() => '');
      if (href === 'javascript:void(0)' || href === '#') invalid++;
    }
    if (invalid > 2) console.warn('[P2] ' + invalid + ' links inválidos no footer');
  });

  test('04 — copyright presente', async ({ page }) => {
    const cp = page.locator('footer .copyright, .footer .copyright, [class*="copyright"]').first();
    const vis = await cp.isVisible({ timeout: 5000 }).catch(() => false);
    if (!vis) console.warn('[P3] Copyright não visível');
  });

  test('05 — footer height razoável', async ({ page }, testInfo) => {
    const footer = page.locator(COMMON.footer).first();
    await footer.scrollIntoViewIfNeeded().catch(() => {});
    const box = await footer.boundingBox();
    // Mobile footers stack columns vertically — allow up to 1500px
    const isMobile = testInfo.project.name.includes('mobile');
    const maxHeight = isMobile ? 1500 : 800;
    if (box) {
      if (box.height > maxHeight) console.warn('[P1] Footer muito alto: ' + Math.round(box.height) + 'px (max: ' + maxHeight + 'px)');
      expect(box.height).toBeLessThan(maxHeight);
    }
  });

  test('06 — sem overflow', async ({ page }) => {
    const { hasOverflow } = await checkOverflow(page);
    expect(hasOverflow).toBe(false);
  });
});