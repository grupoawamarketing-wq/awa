import { test, expect } from '@playwright/test';
import { navigateTo, COMMON, waitForImages } from '../../helpers/deep-audit.helpers';

const HOME = 'https://awamotos.com';

test.describe('Visual — Footer', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
    await waitForImages(page);
  });

  test('01 — screenshot footer', async ({ page }) => {
    const footer = page.locator(COMMON.footer).first();
    await footer.scrollIntoViewIfNeeded().catch(() => {});
    await page.waitForTimeout(500);
    await expect(footer).toHaveScreenshot('footer.png', {
      maxDiffPixelRatio: 0.05, animations: 'disabled',
    });
  });

  test('02 — colunas alinhadas (desktop)', async ({ page }, testInfo) => {
    if (testInfo.project.name.includes('mobile')) { test.skip(); return; }
    const cols = page.locator('footer .col-md-3, footer .footer-column, .footer.content > div, .footer-top > .container > .row > div');
    const count = await cols.count();
    if (count < 2) return;
    const tops: number[] = [];
    for (let i = 0; i < Math.min(count, 4); i++) {
      const box = await cols.nth(i).boundingBox();
      if (box) tops.push(Math.round(box.y));
    }
    const unique = [...new Set(tops)];
    if (unique.length > 2) console.warn('[P2] Colunas footer desalinhadas');
  });

  test('03 — mobile stacks corretamente', async ({ page }, testInfo) => {
    if (!testInfo.project.name.includes('mobile')) { test.skip(); return; }
    const footer = page.locator(COMMON.footer).first();
    const box = await footer.boundingBox();
    if (box) expect(box.width).toBeLessThanOrEqual(page.viewportSize()!.width + 2);
  });
});