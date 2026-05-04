/**
 * Regressão opcional — alt do SlideBanner (hero) sem colagem strip_tags (ex. MotosModelos).
 *
 * Produção pode ainda não ter o plugin deployado; por isso este ficheiro só corre quando:
 *   HERO_ALT_REGRESSION=1 npx playwright test specs/hero-slider-alt-regression.spec.ts --project=notebook-1280
 *
 * @see docs/INVESTIGACAO_BUG_VISUAL_SLIDE_BANNER_HERO_2026-05.md
 */
import { test, expect } from '@playwright/test';

const BASE = process.env.BASE_URL || 'https://awamotos.com';

test.skip(
  !process.env.HERO_ALT_REGRESSION,
  'Defina HERO_ALT_REGRESSION=1 para executar (evita falha na CI contra prod antigo).'
);

test.describe('Hero SlideBanner — alt', () => {
  test('Nenhum img .wrapper_slider com substring MotosModelos', async ({ page }) => {
    await page.goto(BASE, { waitUntil: 'domcontentloaded', timeout: 60_000 });
    const hasGluedBug = await page.evaluate(() => {
      const imgs = Array.from(document.querySelectorAll('.wrapper_slider img[alt]'));
      return imgs.some((img) => (img.getAttribute('alt') || '').includes('MotosModelos'));
    });
    expect(hasGluedBug, 'Deploy: SliderSlideAltNormalizePlugin + setup:di:compile').toBe(false);
  });
});
