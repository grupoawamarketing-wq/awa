import { Page } from '@playwright/test';
export { navigateTo, COMMON, waitForPage, dismissCookie, collectJsErrors, css, cssMultiple, isVisible, hasNoOverflow } from './visual-audit.helpers';

export type BugPriority = 'P0' | 'P1' | 'P2' | 'P3';
export interface ConsoleEntry { type: string; text: string; }
export interface NetworkError { url: string; status: number; }

export function collectConsoleErrors(page: Page): ConsoleEntry[] {
  const entries: ConsoleEntry[] = [];
  page.on('console', msg => { if (msg.type() === 'error') entries.push({ type: 'error', text: msg.text() }); });
  page.on('pageerror', e => entries.push({ type: 'pageerror', text: e.message }));
  return entries;
}

export function collectNetworkErrors(page: Page): NetworkError[] {
  const errors: NetworkError[] = [];
  page.on('response', res => { if (res.status() >= 400) errors.push({ url: res.url(), status: res.status() }); });
  return errors;
}

export function filterCriticalJsErrors(entries: ConsoleEntry[]): ConsoleEntry[] {
  return entries.filter(e => e.type === 'pageerror' || /require is not defined|Cannot read prop|Uncaught TypeError|Uncaught ReferenceError/i.test(e.text));
}

export function filter404s(errors: NetworkError[]): NetworkError[] {
  return errors.filter(e => e.status === 404 && !/hot-update|favicon|sw\.js/i.test(e.url));
}

export function filter500s(errors: NetworkError[]): NetworkError[] {
  return errors.filter(e => e.status >= 500);
}

export async function checkOverflow(page: Page): Promise<{ hasOverflow: boolean; diff: number }> {
  try {
    const result = await Promise.race<{ hasOverflow: boolean; diff: number }>([
      page.evaluate(() => {
        const sw = document.documentElement.scrollWidth;
        const cw = document.documentElement.clientWidth;
        return { hasOverflow: sw > cw + 4, diff: sw - cw };
      }),
      new Promise<{ hasOverflow: boolean; diff: number }>(r => setTimeout(() => r({ hasOverflow: false, diff: 0 }), 8000)),
    ]);
    return result;
  } catch { return { hasOverflow: false, diff: 0 }; }
}

export async function findBrokenImages(page: Page): Promise<string[]> {
  try {
    return await Promise.race<string[]>([
      page.evaluate(() => {
        // Only flag images fully loaded (complete=true) but with no data (naturalWidth=0)
        // Ignores lazy/loading images which are not broken, just pending
        return Array.from(document.querySelectorAll('img'))
          .filter(img => img.complete && img.naturalWidth === 0 && !!img.src && img.offsetParent !== null)
          .map(img => img.src).slice(0, 20);
      }),
      new Promise<string[]>(r => setTimeout(() => r([]), 8000)),
    ]);
  } catch { return []; }
}

export async function checkCardAlignment(page: Page, selector: string): Promise<{ aligned: boolean; heights: number[] }> {
  const cards = page.locator(selector);
  const count = await cards.count();
  if (count < 2) return { aligned: true, heights: [] };
  const heights: number[] = [];
  for (let i = 0; i < Math.min(count, 8); i++) {
    const box = await cards.nth(i).boundingBox();
    if (box) heights.push(Math.round(box.height));
  }
  const unique = [...new Set(heights)];
  return { aligned: unique.length <= 2, heights };
}

export async function waitForImages(page: Page): Promise<void> {
  await page.waitForTimeout(2000);
  await Promise.race([
    page.evaluate(() => Promise.all(
      Array.from(document.images).filter(i => !i.complete).map(i => new Promise(r => { i.onload = i.onerror = r; }))
    )).catch(() => {}),
    new Promise<void>(r => setTimeout(r, 8000)),
  ]);
}

export function getViewportLabel(page: Page): string {
  const vp = page.viewportSize();
  if (!vp) return 'unknown';
  if (vp.width <= 480) return 'mobile';
  if (vp.width <= 768) return 'tablet';
  return 'desktop';
}