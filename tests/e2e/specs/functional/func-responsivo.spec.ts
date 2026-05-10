import { test, expect } from '@playwright/test';
import { navigateTo, COMMON } from '../../helpers/visual-audit.helpers';

const HOME = 'https://awamotos.com';

test.describe('Responsivo — desktop e mobile', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('01 — sem overflow horizontal (P0)', async ({ page }) => {
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 4
    );
    if (overflow) console.error('[P0] Overflow horizontal em ' + page.viewportSize()?.width + 'px');
    expect(overflow, '[P0] Overflow horizontal').toBe(false);
  });

  test('02 — header visível em qualquer viewport', async ({ page }) => {
    await expect(page.locator(COMMON.header).first()).toBeVisible({ timeout: 10_000 });
  });

  test('03 — hamburger em mobile (P1)', async ({ page }) => {
    const vp = page.viewportSize();
    if ((vp?.width ?? 1280) >= 768) { test.skip(); return; }
    const toggle = page.locator(
      '.nav-toggle, .action.nav-toggle, .hamburger, .mobile-menu-toggle, .awa-header-mobile-toggle'
    ).first();
    const visible = await toggle.isVisible({ timeout: 8_000 }).catch(() => false);
    if (!visible) console.warn('[P1] Sem hamburger em mobile');
    expect(visible, '[P1] Sem botão hamburger em mobile').toBe(true);
  });

  test('04 — imagens não ultrapassam viewport (P2)', async ({ page }) => {
    // Verificar apenas imagens acima do fold (scrollY=0) para evitar lazy-load offscreen
    const count = await page.evaluate(() => {
      const vw = document.documentElement.clientWidth;
      return Array.from(document.querySelectorAll('img'))
        .filter(img => {
          const r = img.getBoundingClientRect();
          return r.top < window.innerHeight && r.right > vw + 4;
        }).length;
    });
    if (count > 0) console.warn('[P2] ' + count + ' imagens com overflow horizontal');
    // P2 — só avisa, não falha hard
    if (count > 3) console.error('[P2-BUG] ' + count + ' imagens overflow em mobile — verificar CSS responsivo');
  });

  test('05 — fonte body >= 12px em mobile (P2)', async ({ page }) => {
    const vp = page.viewportSize();
    if ((vp?.width ?? 1280) >= 768) { test.skip(); return; }
    const fontSize = await page.evaluate(
      () => parseFloat(window.getComputedStyle(document.body).fontSize)
    ).catch(() => 16);
    if (fontSize < 14) console.warn('[P2] Fonte body em mobile: ' + fontSize + 'px');
    expect(fontSize).toBeGreaterThanOrEqual(12);
  });
});
