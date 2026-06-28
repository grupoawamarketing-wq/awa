import { test, expect } from '@playwright/test';

const HOME_URL = process.env.HOME_SMOKE_URL || 'https://awamotos.com/';

test.describe('Home grid audit metrics', () => {
  test.describe.configure({ timeout: 90_000 });

  for (const viewport of [
    { name: 'mobile', width: 390, height: 844 },
    { name: 'desktop', width: 1366, height: 768 },
  ]) {
    test(`${viewport.name} — align-grid effective`, async ({ page }) => {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      await page.goto(`${HOME_URL}?grid-audit=${Date.now()}`, {
        waitUntil: 'networkidle',
        timeout: 90_000,
      });
      await page.waitForSelector('.awa-carousel-section', { timeout: 60_000 });
      await page.evaluate(() => {
        if (typeof (window as Window & { __awaApplyGatedCSS?: () => void }).__awaApplyGatedCSS === 'function') {
          (window as Window & { __awaApplyGatedCSS?: () => void }).__awaApplyGatedCSS!();
        }
      });
      await page.waitForTimeout(3000);

      const metrics = await page.evaluate(() => {
        const alignLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).filter((l) =>
          (l as HTMLLinkElement).href.includes('align-grid-terminal')
        );
        const thumbs = Array.from(document.querySelectorAll('.awa-carousel-section .product-thumb-link'))
          .slice(0, 3)
          .map((el) => getComputedStyle(el as Element).aspectRatio);
        const containers = Array.from(
          document.querySelectorAll('.content-top-home .container, .top-home-content.awa-home-section > .container')
        )
          .slice(0, 5)
          .map((el) => Math.round((el as HTMLElement).getBoundingClientRect().left));
        const header = document.querySelector('.content-top-home .awa-section-header');
        const benefits = document.querySelector('.awa-benefits-bar .awa-benefits-container');
        return {
          alignLinks: alignLinks.length,
          thumbAspectRatios: thumbs,
          containerLefts: [...new Set(containers)],
          sectionHeaderPad: header ? getComputedStyle(header).paddingInline : null,
          benefitsDisplay: benefits ? getComputedStyle(benefits).display : 'missing',
          token: getComputedStyle(document.body).getPropertyValue('--awa-grid-shell-max').trim(),
        };
      });

      expect(metrics.alignLinks).toBeGreaterThanOrEqual(1);
      expect(metrics.token).toMatch(/1440|1280|min\(100%/);
      expect(metrics.sectionHeaderPad).toBe('0px');
      for (const ratio of metrics.thumbAspectRatios) {
        expect(ratio).toMatch(/^1\s*\/\s*1$/);
      }
      // Containers devem compartilhar o mesmo eixo (spread máx 24px entre extremos)
      if (metrics.containerLefts.length >= 2) {
        const sorted = [...metrics.containerLefts].sort((a, b) => a - b);
        expect(sorted[sorted.length - 1] - sorted[0]).toBeLessThanOrEqual(24);
      }
    });
  }
});
