import fs from 'fs';
import path from 'path';
import crypto from 'crypto';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';
import type { Page } from '@playwright/test';

export type Severity = 'critical' | 'major' | 'minor';

export interface VisualFinding {
  id: string;
  severity: Severity;
  page: string;
  component: string;
  title: string;
  description: string;
  expected: string;
  actual: string;
  browser: string;
  device: string;
  evidence: string[];
  autofixRuleId?: string;
}

export interface VisualTarget {
  slug: string;
  url: string;
  pageLabel: string;
}

export interface AuditPaths {
  baselineDir: string;
  currentDir: string;
  reportDir: string;
}

export const MCP_COOKIE_SELECTORS = [
  '#awa-cookie-accept',
  '.awa-cookie-banner__btn--accept',
  '.cookie-btn-accept',
  '#btn-cookie-allow',
  '#onetrust-accept-btn-handler',
].join(', ');

export const DEFAULT_TARGETS: VisualTarget[] = [
  // Core pages
  { slug: 'home', url: 'https://awamotos.com/', pageLabel: 'Home' },
  // Category / PLP
  { slug: 'category-guidoes', url: 'https://awamotos.com/guidoes.html', pageLabel: 'PLP Guidoes' },
  { slug: 'category-bagageiros', url: 'https://awamotos.com/bagageiros.html', pageLabel: 'PLP Bagageiros' },
  // PDP
  { slug: 'pdp-ret-biz', url: 'https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html', pageLabel: 'PDP Ret BIZ' },
  // Search
  { slug: 'search-bagageiro', url: 'https://awamotos.com/catalogsearch/result/?q=bagageiro', pageLabel: 'Search Results' },
  // Auth / Account
  { slug: 'login', url: 'https://awamotos.com/customer/account/login/', pageLabel: 'Login' },
  // Cart
  { slug: 'cart', url: 'https://awamotos.com/checkout/cart/', pageLabel: 'Cart' },
  // B2B
  { slug: 'b2b-landing', url: 'https://awamotos.com/b2b', pageLabel: 'B2B Landing' },
];

/**
 * Maximum allowed pixel difference ratio before flagging as regression.
 * 0.005 = 0.5% of total pixels can differ (handles dynamic content like
 * social proof counters, timestamps, product ordering changes).
 */
const parsedVisualThreshold = Number.parseFloat(process.env.MCP_VISUAL_THRESHOLD || '0.005');
const MAX_DIFF_PIXEL_RATIO = Number.isFinite(parsedVisualThreshold) && parsedVisualThreshold >= 0
  ? parsedVisualThreshold
  : 0.005;
const MCP_VISUAL_DEBUG = process.env.MCP_VISUAL_DEBUG === '1';

function isMobile390Snapshot(filePath: string): boolean {
  return /[\\/]mobile-390[\\/][^\\/]+\.png$/i.test(filePath);
}

export function resolveAuditPaths(rootDir: string): AuditPaths {
  return {
    baselineDir: path.join(rootDir, 'snapshots', 'mcp-visual-baseline'),
    currentDir: path.join(rootDir, 'screenshots', 'mcp-visual-current'),
    reportDir: path.join(rootDir, 'reports', 'mcp-visual'),
  };
}

export function ensureDir(dirPath: string): void {
  fs.mkdirSync(dirPath, { recursive: true });
}

export async function waitPageStable(page: Page): Promise<void> {
  await page.waitForLoadState('domcontentloaded', { timeout: 30_000 }).catch(() => {});
  await page.waitForLoadState('load', { timeout: 30_000 }).catch(() => {});
  await page.waitForTimeout(900);
}

export async function dismissCookieBanner(page: Page): Promise<void> {
  const btn = page.locator(MCP_COOKIE_SELECTORS).first();
  const visible = await btn.isVisible({ timeout: 2_000 }).catch(() => false);
  if (visible) {
    await btn.click({ force: true }).catch(() => {});
    await page.waitForTimeout(300);
  }
}

export async function stabilizeVisualSnapshot(page: Page): Promise<void> {
  await page
    .evaluate(() => {
      const volatileSelectors = [
        '.link-on-bottom',
        '.awa-whatsapp-float',
        'a.awa-whatsapp-float',
        '[class*="link-on-bottom"]',
        '[class*="whatsapp-float"]',
      ];

      const styleId = 'mcp-visual-stabilize-overlays';
      let styleEl = document.getElementById(styleId) as HTMLStyleElement | null;
      if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = styleId;
        styleEl.textContent = `${volatileSelectors.join(',')} { visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; }`;
        document.head.appendChild(styleEl);
      }

      for (const selector of volatileSelectors) {
        const elements = document.querySelectorAll<HTMLElement>(selector);
        for (const el of elements) {
          el.style.setProperty('visibility', 'hidden', 'important');
          el.style.setProperty('opacity', '0', 'important');
          el.style.setProperty('pointer-events', 'none', 'important');
        }
      }
    })
    .catch(() => {});

  await page.waitForTimeout(120);
}

function sha256(filePath: string): string {
  const content = fs.readFileSync(filePath);
  return crypto.createHash('sha256').update(content).digest('hex');
}

/**
 * Compare screenshots using pixel-level diff with tolerance.
 * Returns the ratio of different pixels (0.0 = identical, 1.0 = completely different).
 * Falls back to SHA256 hash if images have different dimensions.
 */
function pixelDiffRatio(actualPath: string, baselinePath: string): number {
  try {
    const actualBuf = fs.readFileSync(actualPath);
    const baselineBuf = fs.readFileSync(baselinePath);

    const actual = PNG.sync.read(actualBuf);
    const baseline = PNG.sync.read(baselineBuf);

    // If dimensions differ, treat as 100% different
    if (actual.width !== baseline.width || actual.height !== baseline.height) {
      return 1.0;
    }

    let compareHeight = actual.height;
    // Mobile pages have volatile floating widgets near viewport bottom; ignore this strip for stability.
    if (isMobile390Snapshot(actualPath)) {
      compareHeight = Math.max(1, actual.height - 180);
    }

    const totalPixels = actual.width * compareHeight;
    if (totalPixels === 0) return 0;

    const compareBytes = totalPixels * 4;
    const actualData = compareHeight === actual.height
      ? actual.data
      : actual.data.subarray(0, compareBytes);
    const baselineData = compareHeight === baseline.height
      ? baseline.data
      : baseline.data.subarray(0, compareBytes);

    const diffPixels = pixelmatch(
      actualData,
      baselineData,
      undefined, // no output diff image
      actual.width,
      compareHeight,
      { threshold: 0.1 } // per-pixel color sensitivity
    );

    return diffPixels / totalPixels;
  } catch (error: unknown) {
    if (MCP_VISUAL_DEBUG) {
      const errorMessage = error instanceof Error ? error.message : String(error);
      // eslint-disable-next-line no-console
      console.warn(
        `[mcp-visual] pixel comparison fallback to hash for ${path.basename(actualPath)} vs ${path.basename(baselinePath)}: ${errorMessage}`
      );
    }
    // If pixel comparison fails, fall back to hash comparison
    return sha256(actualPath) !== sha256(baselinePath) ? 1.0 : 0;
  }
}

export function compareAgainstBaseline(
  actualPath: string,
  baselinePath: string,
  updateBaseline: boolean
): { changed: boolean; baselineMissing: boolean; diffRatio?: number } {
  const baselineExists = fs.existsSync(baselinePath);
  if (!baselineExists) {
    if (updateBaseline) {
      ensureDir(path.dirname(baselinePath));
      fs.copyFileSync(actualPath, baselinePath);
      return { changed: false, baselineMissing: false, diffRatio: 0 };
    }
    return { changed: false, baselineMissing: true };
  }

  // Quick check: if hashes match, no need for pixel comparison
  if (sha256(actualPath) === sha256(baselinePath)) {
    return { changed: false, baselineMissing: false, diffRatio: 0 };
  }

  // Pixel-level comparison with tolerance
  const diffRatio = pixelDiffRatio(actualPath, baselinePath);
  const changed = diffRatio > MAX_DIFF_PIXEL_RATIO;

  if (updateBaseline) {
    fs.copyFileSync(actualPath, baselinePath);
    return { changed: false, baselineMissing: false, diffRatio };
  }

  return { changed, baselineMissing: false, diffRatio };
}

async function getRect(page: Page, selector: string): Promise<{ x: number; y: number; width: number; height: number } | null> {
  return page
    .evaluate((sel) => {
      const el = document.querySelector(sel);
      if (!el) return null;
      const r = el.getBoundingClientRect();
      if (r.width <= 0 || r.height <= 0) return null;
      return { x: r.x, y: r.y, width: r.width, height: r.height };
    }, selector)
    .catch(() => null);
}

function intersects(
  a: { x: number; y: number; width: number; height: number },
  b: { x: number; y: number; width: number; height: number }
): boolean {
  return !(
    a.x + a.width <= b.x ||
    b.x + b.width <= a.x ||
    a.y + a.height <= b.y ||
    b.y + b.height <= a.y
  );
}

export async function detectVisualFindings(
  page: Page,
  target: VisualTarget,
  browserName: string,
  deviceName: string,
  evidence: string[]
): Promise<VisualFinding[]> {
  const findings: VisualFinding[] = [];

  const overflow = await page
    .evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
    .catch(() => 0);

  if (overflow > 2) {
    findings.push({
      id: `${target.slug}-overflow`,
      severity: 'major',
      page: target.pageLabel,
      component: 'Global layout',
      title: 'Horizontal overflow detected',
      description: 'The page width exceeds viewport width.',
      expected: 'No horizontal scroll in default viewport.',
      actual: `Detected overflow of ${Math.round(overflow)}px.`,
      browser: browserName,
      device: deviceName,
      evidence,
      autofixRuleId: 'horizontal-overflow',
    });
  }

  const clipped = await page
    .evaluate(() => {
      const selectors = [
        '.customer-welcome',
        '.authorization-link',
        '.minicart-wrapper',
        '.logo',
        '.page-title-wrapper h1',
      ];
      for (const sel of selectors) {
        const el = document.querySelector(sel) as HTMLElement | null;
        if (!el) continue;
        if (el.scrollWidth > el.clientWidth + 6 && (el.innerText || '').trim().length > 8) {
          return sel;
        }
      }
      return '';
    })
    .catch(() => '');

  if (clipped) {
    findings.push({
      id: `${target.slug}-text-clipping`,
      severity: 'major',
      page: target.pageLabel,
      component: clipped,
      title: 'Text clipping detected',
      description: 'A visible UI text container is clipping its content.',
      expected: 'Text should be fully readable or wrapped.',
      actual: `Selector ${clipped} has clipped text.`,
      browser: browserName,
      device: deviceName,
      evidence,
      autofixRuleId: 'text-clipping',
    });
  }

  const accountRect = await getRect(page, '.customer-welcome, .authorization-link, .header-right .customer-menu');
  const logoRect = await getRect(page, '.logo, .header .logo');
  if (accountRect && logoRect && intersects(accountRect, logoRect)) {
    findings.push({
      id: `${target.slug}-header-overlap`,
      severity: 'critical',
      page: target.pageLabel,
      component: 'Header account area',
      title: 'Header overlap detected',
      description: 'Account area overlaps logo block.',
      expected: 'Header blocks should not overlap.',
      actual: 'Account and logo rectangles intersect.',
      browser: browserName,
      device: deviceName,
      evidence,
      autofixRuleId: 'header-account-overlap',
    });
  }

  const cookieObstruction = await page
    .evaluate(() => {
      const banner = document.querySelector('.cookie-message, .cookie-notice, .awa-cookie-banner') as HTMLElement | null;
      if (!banner) return 0;
      const style = window.getComputedStyle(banner);
      if (!['fixed', 'sticky'].includes(style.position)) return 0;
      const rect = banner.getBoundingClientRect();
      return rect.height;
    })
    .catch(() => 0);

  if (cookieObstruction > 70) {
    findings.push({
      id: `${target.slug}-cookie-obstruction`,
      severity: 'major',
      page: target.pageLabel,
      component: 'Cookie banner',
      title: 'Cookie banner obstructs viewport',
      description: 'Cookie banner consumes a large fixed area at the bottom.',
      expected: 'Banner should not block significant page content.',
      actual: `Detected fixed/sticky cookie bar with ${Math.round(cookieObstruction)}px height.`,
      browser: browserName,
      device: deviceName,
      evidence,
      autofixRuleId: 'cookie-banner-obstructive',
    });
  }

  const brokenImage = await page
    .evaluate(() => {
      const images = Array.from(document.querySelectorAll('img')) as HTMLImageElement[];
      for (const img of images) {
        const rect = img.getBoundingClientRect();
        if (rect.width < 40 || rect.height < 40) continue;
        if (img.complete && img.naturalWidth === 0) return img.src || 'unknown-image';
      }
      return '';
    })
    .catch(() => '');

  if (brokenImage) {
    findings.push({
      id: `${target.slug}-broken-image`,
      severity: 'major',
      page: target.pageLabel,
      component: 'Image rendering',
      title: 'Broken image detected',
      description: 'A visible image element failed to load.',
      expected: 'Visible images should render with valid source.',
      actual: `Broken image source: ${brokenImage}`,
      browser: browserName,
      device: deviceName,
      evidence,
    });
  }

  return findings;
}

function sanitizeReportValue(value: unknown, rootDir: string): unknown {
  if (typeof value === 'string') {
    if (!path.isAbsolute(value)) {
      return value;
    }

    const resolvedRoot = `${path.resolve(rootDir)}${path.sep}`;
    const resolvedValue = path.resolve(value);

    if (resolvedValue.startsWith(resolvedRoot)) {
      return path.relative(rootDir, resolvedValue).split(path.sep).join('/');
    }

    return path.basename(resolvedValue);
  }

  if (Array.isArray(value)) {
    return value.map((item) => sanitizeReportValue(item, rootDir));
  }

  if (value !== null && typeof value === 'object') {
    const result: Record<string, unknown> = {};
    for (const [key, nestedValue] of Object.entries(value)) {
      result[key] = sanitizeReportValue(nestedValue, rootDir);
    }
    return result;
  }

  return value;
}

export function writeJsonReport(reportPath: string, payload: unknown): void {
  ensureDir(path.dirname(reportPath));
  const sanitizedPayload = sanitizeReportValue(payload, process.cwd());
  fs.writeFileSync(reportPath, JSON.stringify(sanitizedPayload, null, 2), 'utf8');
}

export function severityWeight(severity: Severity): number {
  if (severity === 'critical') return 3;
  if (severity === 'major') return 2;
  return 1;
}

export function shouldFailBySeverity(findings: VisualFinding[], failOn: 'critical' | 'major' | 'none'): boolean {
  if (failOn === 'none') return false;
  const threshold = failOn === 'critical' ? 3 : 2;
  return findings.some((f) => severityWeight(f.severity) >= threshold);
}
