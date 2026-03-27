/**
 * Helper: Header Layout — seletores e utilitários compartilhados
 */
import { Page, expect } from '@playwright/test';

/* ── Seletores ─────────────────────────────────────────── */
export const SELECTORS = {
  header:          'header.awa-site-header',
  topHeader:       '.awa-site-header .top-header',
  headerMain:      '.awa-site-header .header',
  wpHeader:        '.awa-site-header .header .wp-header[data-awa-header-row]',
  primaryRow:      '.awa-site-header .header .awa-header-primary-row',
  brandCell:       '.awa-site-header .header .awa-header-brand-cell',
  logo:            '.awa-site-header .header .logo',
  mobileLogo:      '.awa-site-header .header .awa-header-mobile-logo',
  topSearch:       '.awa-site-header .header .top-search',
  searchBlock:     '.awa-site-header .header .top-search .block-search',
  searchInput:     '.awa-site-header .header input[data-awa-search-input="true"], .awa-site-header .header #search',
  minicart:        '.awa-site-header .header .awa-header-minicart, .awa-site-header .header .mini-cart-wrapper',
  contactSlot:     '.awa-site-header .header .awa-header-contact-slot',
  navToggle:       '.awa-site-header .header .awa-header-mobile-toggle',
  stickyHeader:    '.awa-site-header .header-wrapper-sticky',
  headerNav:       '.awa-site-header .header-control',
  headerCart:      '.awa-site-header .header .awa-header-cart-link',
} as const;

/* ── Breakpoints ───────────────────────────────────────── */
export const BREAKPOINTS = {
  mobileMax:   767,
  tabletMin:   768,
  tabletMax:  1023,
  notebookMin: 1024,
  notebookMax: 1366,
  desktopMin:  1367,
} as const;

/* ── Wait para header estar pronto ───────────────────────── */
export async function waitForHeader(page: Page): Promise<void> {
  // Aguarda DOM carregado
  await page.waitForLoadState('domcontentloaded');
  // Aguarda o header estar visível
  await page.locator(SELECTORS.header).waitFor({ state: 'visible', timeout: 15_000 });
  // Aguarda estabilidade visual (CSS externo carregado)
  await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {
    // networkidle pode nunca chegar em sites com polling; ignorar timeout
  });
  // Aguarda fontes
  await page.evaluate(() => document.fonts.ready);
  // Pequena pausa para garantir que transições CSS terminaram
  await page.waitForTimeout(400);
}

/* ── Helper: getBoundingBox com verificação ─────────────── */
export async function getBBox(page: Page, selector: string) {
  const el = page.locator(selector).first();
  await el.waitFor({ state: 'visible', timeout: 5_000 }).catch(() => {});
  return el.boundingBox();
}

/* ── Helper: getComputedCSS ──────────────────────────────── */
export async function getCSSProp(
  page: Page,
  selector: string,
  property: string
): Promise<string> {
  return page.evaluate(
    ([sel, prop]) => {
      const el = document.querySelector(sel as string);
      return el ? window.getComputedStyle(el).getPropertyValue(prop as string).trim() : '';
    },
    [selector, property]
  );
}

/* ── Helper: getMultipleCSS ──────────────────────────────── */
export async function getMultipleCSS(
  page: Page,
  selector: string,
  properties: string[]
): Promise<Record<string, string>> {
  return page.evaluate(
    ([sel, props]) => {
      const el = document.querySelector(sel as string);
      if (!el) return {};
      const cs = window.getComputedStyle(el);
      return (props as string[]).reduce((acc: Record<string, string>, p) => {
        acc[p] = cs.getPropertyValue(p).trim();
        return acc;
      }, {});
    },
    [selector, properties]
  );
}

/* ── Helper: pxToNum ──────────────────────────────────────── */
export function px(value: string): number {
  return parseFloat(value) || 0;
}

/* ── Helper: isVisible ────────────────────────────────────── */
export async function isVisible(page: Page, selector: string): Promise<boolean> {
  const el = page.locator(selector).first();
  try {
    await el.waitFor({ state: 'visible', timeout: 2_000 });
    return true;
  } catch {
    return false;
  }
}

/* ── Helper: verificar sobreposição entre dois elementos ─────── */
export async function checkOverlap(
  page: Page,
  selectorA: string,
  selectorB: string,
): Promise<{ overlaps: boolean; gapPx: number }> {
  const a = await getBBox(page, selectorA);
  const b = await getBBox(page, selectorB);

  if (!a || !b) return { overlaps: false, gapPx: 0 };

  const aRight  = a.x + a.width;
  const bRight  = b.x + b.width;
  const aBottom = a.y + a.height;
  const bBottom = b.y + b.height;

  const overlapX = Math.min(aRight, bRight) - Math.max(a.x, b.x);
  const overlapY = Math.min(aBottom, bBottom) - Math.max(a.y, b.y);

  const overlaps = overlapX > 2 && overlapY > 2; // tolera 2px
  const gapPx    = overlaps ? -Math.max(overlapX, overlapY) : Math.min(
    Math.abs(a.x - bRight),
    Math.abs(b.x - aRight),
  );

  return { overlaps, gapPx };
}
