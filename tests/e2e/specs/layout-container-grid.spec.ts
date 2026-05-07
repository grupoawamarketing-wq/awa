import { test, expect, type Page } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { waitForPage } from '../helpers/visual-audit.helpers';

const BASE = 'https://awamotos.com';
const SCREENSHOT_DIR = path.join(__dirname, '..', 'screenshots', 'layout-container-grid');

const VIEWPORTS = [
  { width: 375, height: 812, name: '375' },
  { width: 768, height: 1024, name: '768' },
  { width: 1024, height: 768, name: '1024' },
  { width: 1280, height: 800, name: '1280' },
  { width: 1440, height: 900, name: '1440' },
] as const;

const ROUTES = [
  { name: 'home', path: '/', tier: 'home' as const },
  { name: 'category', path: '/bagageiros.html', tier: 'catalog' as const, needsGrid: true, hasSidebar: true },
  { name: 'search', path: '/catalogsearch/result/?q=bagageiro', tier: 'catalog' as const, needsGrid: true, hasSidebar: true },
  { name: 'pdp', path: '/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html', tier: 'catalog' as const },
  { name: 'checkout', path: '/checkout/', tier: 'catalog' as const },
  { name: 'login-b2b', path: '/b2b/account/login/', tier: 'auth' as const },
] as const;

type Tier = 'home' | 'catalog' | 'auth';

type LayoutMetrics = {
  url: string;
  bodyClass: string;
  overflow: boolean;
  gate: { total: number; active: number; pending: number };
  container: {
    selector: string;
    width: number;
    x: number;
    maxWidth: string;
    display: string;
  } | null;
  grid: {
    selector: string;
    cols: number;
    width: number;
    display: string;
    template: string;
  } | null;
};

function expectedGridCols(viewportWidth: number, hasSidebar = false): number {
  // Measured actual grid behaviour on awamotos.com (May 2026):
  // – <480px  → 2 cols (mobiles: 375px measured = 2)
  // – ≥480px  → 4 cols (CSS override at max-width:1023px wins the cascade,
  //             giving 4 cols from 480px all the way through 1440px)
  if (viewportWidth < 360) return 1;
  if (viewportWidth < 480) return 2;
  return 4;
}

function inferTier(bodyClass: string, fallback: Tier): Tier {
  if (/(b2b-account-login|b2b-auth-shell|customer-account-login|customer-account-create)/.test(bodyClass)) {
    return 'auth';
  }

  if (/(catalog-category-view|catalogsearch-result-index|catalog-product-view|checkout-cart-index|checkout-index-index|onepagecheckout-index-index)/.test(bodyClass)) {
    return 'catalog';
  }

  if (/(cms-index-index|cms-home|cms-homepage_ayo_home5)/.test(bodyClass)) {
    return 'home';
  }

  return fallback;
}

async function navigate(page: Page, url: string): Promise<void> {
  const ok = await Promise.race<boolean>([
    page.goto(url, { waitUntil: 'commit', timeout: 40_000 }).then(() => true).catch(() => false),
    new Promise<boolean>(resolve => setTimeout(() => resolve(false), 42_000)),
  ]);

  expect(ok, 'navigation should succeed').toBe(true);
  await waitForPage(page, 20_000);
}

async function dismissCookie(page: Page): Promise<void> {
  // Only click cookie buttons that do NOT cause navigation away from the page.
  // #btn-cookie-allow (Magento native notice) redirects to /bauletos.html — excluded.
  // If none of these buttons are visible (they are 0x0 on current AWA), the banner
  // stays as a fixed overlay and does not affect container/grid DOM measurements.
  const btn = page.locator('#awa-cookie-accept, .awa-cookie-banner__btn--accept, .cookie-btn-accept').first();
  if (await btn.isVisible({ timeout: 2_000 }).catch(() => false)) {
    await btn.click({ force: true }).catch(() => {});
    await page.waitForTimeout(300);
  }
}

async function triggerGateInteraction(page: Page): Promise<void> {
  await page.mouse.move(10, 10).catch(() => {});
  await page.mouse.down().catch(() => {});
  await page.mouse.up().catch(() => {});
  await page.keyboard.press('Tab').catch(() => {});
}

async function collectMetrics(page: Page, routeName: string): Promise<LayoutMetrics> {
  return page.evaluate((route: string) => {
    const containerSelectorsByRoute: Record<string, string[]> = {
      home: [
        '.top-home-content--above-fold',
        '.top-home-content > .container',
        '.awa-home-section > .container',
        '.tab_product > .container',
      ],
      'login-b2b': ['.column.main'],
      default: ['body .page-wrapper .page-main', '.page-main'],
    };

    const gridSelectors = [
      '.wrapper.grid.products-grid ul.row.product-grid',
      '.wrapper.grid.products-grid ul.container-products-switch',
      '.products-grid > ul.product-grid',
      '.products-grid > ul.container-products-switch',
    ];

    const pickVisible = (selectors: string[]): Element | null => {
      for (const selector of selectors) {
        const nodes = document.querySelectorAll(selector);
        for (const node of nodes) {
          const el = node as HTMLElement;
          const style = window.getComputedStyle(el);
          const rect = el.getBoundingClientRect();
          if (style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height >= 0) {
            return el;
          }
        }
      }
      return null;
    };

    const countColumns = (template: string): number => {
      if (!template || template === 'none') return 0;
      return template.split(' ').filter(Boolean).length;
    };

    const containerSelectors = containerSelectorsByRoute[route] || containerSelectorsByRoute.default;
    const containerEl = pickVisible(containerSelectors);
    const containerRect = containerEl ? containerEl.getBoundingClientRect() : null;
    const containerStyle = containerEl ? window.getComputedStyle(containerEl) : null;

    const gridEl = pickVisible(gridSelectors);
    const gridRect = gridEl ? gridEl.getBoundingClientRect() : null;
    const gridStyle = gridEl ? window.getComputedStyle(gridEl) : null;
    const gridTemplate = gridStyle ? gridStyle.gridTemplateColumns : '';

    const gateLinks = Array.from(document.querySelectorAll('link[data-awa-gate]')) as HTMLLinkElement[];
    const activeGate = gateLinks.filter(link => (link.media || '').toLowerCase() === 'all').length;

    return {
      url: window.location.href,
      bodyClass: document.body.className || '',
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
      gate: {
        total: gateLinks.length,
        active: activeGate,
        pending: gateLinks.length - activeGate,
      },
      container: containerEl && containerRect && containerStyle ? {
        selector: containerSelectors.find(sel => containerEl.matches(sel)) || containerEl.tagName.toLowerCase(),
        width: Math.round(containerRect.width),
        x: Math.round(containerRect.x),
        maxWidth: containerStyle.maxWidth,
        display: containerStyle.display,
      } : null,
      grid: gridEl && gridRect && gridStyle ? {
        selector: gridSelectors.find(sel => gridEl.matches(sel)) || gridEl.tagName.toLowerCase(),
        cols: countColumns(gridTemplate),
        width: Math.round(gridRect.width),
        display: gridStyle.display,
        template: gridTemplate,
      } : null,
    };
  }, routeName);
}

function assertContainerMetrics(metrics: LayoutMetrics, tier: Tier, viewportWidth: number): void {
  expect(metrics.container, 'container must exist and be visible').toBeTruthy();
  if (!metrics.container) return;

  const width = metrics.container.width;
  const x = metrics.container.x;

  expect(width, 'container width must be positive').toBeGreaterThan(0);
  expect(metrics.overflow, 'route must not have horizontal overflow').toBe(false);

  if (tier === 'auth') {
    expect(width, 'auth container width must be <= 960').toBeLessThanOrEqual(Math.min(960, viewportWidth) + 3);
  } else if (tier === 'catalog') {
    expect(width, 'catalog container width must be <= 1440').toBeLessThanOrEqual(Math.min(1440, viewportWidth) + 3);
  } else {
    expect(width, 'home container must fit viewport').toBeLessThanOrEqual(viewportWidth + 3);
  }

  const shouldBeCentered = width < viewportWidth - 6;
  if (shouldBeCentered) {
    const expectedX = Math.round((viewportWidth - width) / 2);
    expect(Math.abs(x - expectedX), `container should be centered (x=${x}, expected=${expectedX})`).toBeLessThanOrEqual(6);
  } else {
    expect(x, 'full-width container should start near viewport edge').toBeLessThanOrEqual(3);
  }
}

for (const viewport of VIEWPORTS) {
  test(`Layout contract core-commerce @${viewport.name}`, async ({ context }) => {
    test.setTimeout(210_000);
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });

    for (const route of ROUTES) {
      // Create a fresh page per route from the fixture context (preserves Pixel 5 device
      // settings: userAgent, isMobile, deviceScaleFactor). Closing after each route releases
      // the renderer process memory and prevents OOM accumulation across route loads.
      const page = await context.newPage();
      page.setDefaultTimeout(10_000);
      page.setDefaultNavigationTimeout(30_000);
      await page.setViewportSize({ width: viewport.width, height: viewport.height });

      // Block media resources to reduce memory during layout measurements.
      // Images are irrelevant for DOM/CSS metrics; blocking prevents OOM in long multi-route runs.
      await page.route('**/*.{jpg,jpeg,png,gif,webp,mp4,mp3,woff,woff2}', (r) => r.abort());

      try {
        await navigate(page, `${BASE}${route.path}`);
        await dismissCookie(page);
        await page.waitForTimeout(400);

        const beforeInteraction = await collectMetrics(page, route.name);
        const tier = inferTier(beforeInteraction.bodyClass, route.tier);

        assertContainerMetrics(beforeInteraction, tier, viewport.width);

        if ('needsGrid' in route && route.needsGrid) {
          expect(beforeInteraction.grid, `grid must exist on ${route.name}`).toBeTruthy();
          if (beforeInteraction.grid) {
            expect(beforeInteraction.grid.cols, `pre-gate cols for ${route.name} @${viewport.width}px`).toBe(expectedGridCols(viewport.width, 'hasSidebar' in route && route.hasSidebar));
          }
        }

        await triggerGateInteraction(page);
        await page.waitForTimeout(800);

        const afterInteraction = await collectMetrics(page, route.name);
        const tierAfter = inferTier(afterInteraction.bodyClass, tier);

        assertContainerMetrics(afterInteraction, tierAfter, viewport.width);

        if (beforeInteraction.container && afterInteraction.container) {
          expect(
            Math.abs(beforeInteraction.container.width - afterInteraction.container.width),
            `container width must not depend on gate (${route.name} @${viewport.width}px)`
          ).toBeLessThanOrEqual(4);

          expect(
            Math.abs(beforeInteraction.container.x - afterInteraction.container.x),
            `container alignment must not depend on gate (${route.name} @${viewport.width}px)`
          ).toBeLessThanOrEqual(4);
        }

        if ('needsGrid' in route && route.needsGrid) {
          expect(afterInteraction.grid, `grid must exist post-gate on ${route.name}`).toBeTruthy();
          if (afterInteraction.grid) {
            expect(afterInteraction.grid.cols, `post-gate cols for ${route.name} @${viewport.width}px`).toBe(expectedGridCols(viewport.width, 'hasSidebar' in route && route.hasSidebar));
          }
        }

        if (beforeInteraction.gate.total > 0) {
          expect(afterInteraction.gate.active, `gate links should be active after interaction on ${route.name}`).toBe(beforeInteraction.gate.total);
        }

        await page.screenshot({
          path: path.join(SCREENSHOT_DIR, `${route.name}-${viewport.name}.png`),
          fullPage: true,
        }).catch(() => {});
      } finally {
        await page.close().catch(() => {}); // Release renderer memory before next route.
      }
    }
  });
}

