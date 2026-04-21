/**
 * Helper: Header Layout — seletores e utilitários compartilhados
 */
import { Page } from '@playwright/test';

/* ── Seletores ─────────────────────────────────────────── */
const HEADER_ROW_SELECTOR =
  '.awa-site-header .header .awa-main-header__inner[data-awa-header-row], .awa-site-header .header .wp-header[data-awa-header-row]';

export const SELECTORS = {
  header:          '[data-awa-header-content]',
  topHeader:       '.awa-site-header .top-header',
  headerMain:      '.awa-site-header .header',
  headerRow:       HEADER_ROW_SELECTOR,
  wpHeader:        HEADER_ROW_SELECTOR,
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
  await page.waitForLoadState('domcontentloaded');
  // waitFor pode travar 120s se Chrome crasha — usar race com Node.js timer
  await Promise.race<void>([
    page.locator(SELECTORS.header).waitFor({ state: 'visible', timeout: 15_000 }).catch(() => {}),
    new Promise<void>(r => setTimeout(r, 17_000)),
  ]);
  await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});
  // fonts.ready com race
  await Promise.race<void>([
    page.evaluate(() => document.fonts.ready.then(() => {})).catch(() => {}),
    new Promise<void>(r => setTimeout(r, 5_000)),
  ]);
  // Pausa de 400ms via Node.js timer (não via waitForTimeout que chama CDP)
  await new Promise<void>(r => setTimeout(r, 400));
}

/* ── Helper: getBoundingBox com verificação ─────────────── */
export async function getBBox(page: Page, selector: string) {
  const el = page.locator(selector).first();
  // waitFor pode travar se Chrome crasha — usar race
  const visible = await Promise.race<boolean>([
    el.waitFor({ state: 'visible', timeout: 5_000 }).then(() => true).catch(() => false),
    new Promise<false>(r => setTimeout(() => r(false), 6_000)),
  ]);
  if (!visible) return null;
  return Promise.race([
    el.boundingBox().catch(() => null),
    new Promise<null>(r => setTimeout(() => r(null), 5_000)),
  ]);
}

/* ── Helper: getComputedCSS ──────────────────────────────── */
export async function getCSSProp(
  page: Page,
  selector: string,
  property: string
): Promise<string> {
  return Promise.race<string>([
    page.evaluate(
      ([sel, prop]) => {
        const el = document.querySelector(sel as string);
        return el ? window.getComputedStyle(el).getPropertyValue(prop as string).trim() : '';
      },
      [selector, property]
    ).catch(() => ''),
    new Promise<string>(r => setTimeout(() => r(''), 5_000)),
  ]);
}

/* ── Helper: getMultipleCSS ──────────────────────────────── */
export async function getMultipleCSS(
  page: Page,
  selector: string,
  properties: string[]
): Promise<Record<string, string>> {
  return Promise.race<Record<string, string>>([
    page.evaluate(
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
    ).catch(() => ({})),
    new Promise<Record<string, string>>(r => setTimeout(() => r({}), 5_000)),
  ]);
}

/* ── Helper: pxToNum ──────────────────────────────────────── */
export function px(value: string): number {
  return parseFloat(value) || 0;
}

/* ── Helper: isVisible ────────────────────────────────────── */
export async function isVisible(page: Page, selector: string): Promise<boolean> {
  return Promise.race<boolean>([
    page.locator(selector).first()
      .waitFor({ state: 'visible', timeout: 2_000 })
      .then(() => true)
      .catch(() => false),
    new Promise<false>(r => setTimeout(() => r(false), 4_000)),
  ]);
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
