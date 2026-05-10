import { test, expect } from '@playwright/test';
import { navigateTo, COMMON } from '../../helpers/visual-audit.helpers';

const HOME = 'https://awamotos.com';

test.describe('Rodapé — links e informações', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
    // Scroll suave até o footer
    await page.evaluate(() => {
      const f = document.querySelector('footer, .page-footer');
      if (f) f.scrollIntoView({ behavior: 'auto', block: 'start' });
      else window.scrollTo(0, document.body.scrollHeight);
    });
    await page.waitForTimeout(800);
  });

  test('01 — rodapé visível (P1)', async ({ page }) => {
    // Seletor real: footer.page-footer
    const footer = page.locator('footer.page-footer, footer, .page-footer').first();
    await expect(footer).toBeVisible({ timeout: 10_000 });
  });

  test('02 — rodapé tem links (P1)', async ({ page }) => {
    const count = await page.locator('footer a, .page-footer a').count().catch(() => 0);
    if (count < 1) console.warn('[P1] Rodapé sem links');
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('03 — sem overflow horizontal (P2)', async ({ page }) => {
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 4
    );
    if (overflow) console.warn('[P2] Overflow horizontal com rodapé visível');
    expect(overflow).toBe(false);
  });
});
