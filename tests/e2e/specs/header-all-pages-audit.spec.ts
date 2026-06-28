import { expect, test } from '@playwright/test';

type HeaderPage = {
  id: string;
  path: string;
  noHeaderExpected?: boolean;
};

type Viewport = {
  name: string;
  width: number;
  height: number;
};

const allPages: HeaderPage[] = [
  { id: 'home', path: '/' },
  { id: 'plp-bauletos', path: '/bauletos.html' },
  { id: 'plp-carcacas', path: '/carcacas.html' },
  { id: 'search', path: '/catalogsearch/result/?q=guidao' },
  { id: 'pdp', path: '/bagageiro-titan-150-09-13-modelo-preto-macico-3000.html' },
  { id: 'cart', path: '/checkout/cart/' },
  { id: 'not-found', path: '/pagina-inexistente-awamotos-header-audit' },
  { id: 'b2b-login', path: '/b2b/account/login/', noHeaderExpected: true },
  { id: 'b2b-register', path: '/b2b/register/', noHeaderExpected: true },
];

const allViewports: Viewport[] = [
  { name: 'desktop', width: 1365, height: 760 },
  { name: 'tablet', width: 820, height: 900 },
  { name: 'mobile', width: 390, height: 844 },
];


const pageFilter = process.env.AWA_HEADER_AUDIT_PAGE;
const viewportFilter = process.env.AWA_HEADER_AUDIT_VIEWPORT;
const pages = pageFilter ? allPages.filter((page) => page.id === pageFilter) : allPages;
const viewports = viewportFilter ? allViewports.filter((viewport) => viewport.name === viewportFilter) : allViewports;

test.describe.configure({ mode: 'serial' });
test.use({ screenshot: 'off', video: 'off', trace: 'off' });

test.describe('AWA header all pages audit', () => {
  test.setTimeout(180_000);

  for (const viewport of viewports) {
    test(`${viewport.name} header metrics`, async ({ page }, testInfo) => {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });

      const results = [];
      const issues: string[] = [];

      for (const target of pages) {
        const url = `${target.path}${target.path.includes('?') ? '&' : '?'}awa-header-audit=${Date.now()}`;

        const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60_000 });
        await page.waitForLoadState('networkidle', { timeout: 30_000 }).catch(() => undefined);
        await page.waitForTimeout(9_000);

        const data = await page.evaluate((args) => {
          const rect = (selector: string) => {
            const el = document.querySelector(selector);
            if (!el) {
              return null;
            }
            const box = el.getBoundingClientRect();
            const style = window.getComputedStyle(el);

            return {
              display: style.display,
              visibility: style.visibility,
              opacity: style.opacity,
              position: style.position,
              leftCss: style.left,
              transform: style.transform,
              left: Math.round(box.left * 10) / 10,
              top: Math.round(box.top * 10) / 10,
              width: Math.round(box.width * 10) / 10,
              height: Math.round(box.height * 10) / 10,
            };
          };

          const terminalStyle = document.querySelector('#awa-header-vtex-clean-terminal-20260622');
          const criticalStyle = document.querySelector('#awa-header-vtex-clean-critical-20260622');

          return {
            id: args.id,
            viewport: args.viewport,
            status: args.status,
            bodyClass: document.body?.className ?? '',
            overflowX: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
            terminalStyle: Boolean(terminalStyle),
            terminalScript: Boolean(document.querySelector('#awa-header-vtex-clean-terminal-order-20260622')),
            criticalStyle: Boolean(criticalStyle),
            header: rect('.awa-site-header'),
            promo: rect('.awa-site-header #awa-b2b-promo-bar'),
            sticky: rect('.awa-site-header .header-wrapper-sticky'),
            main: rect('.awa-site-header .header.awa-main-header'),
            mainInner: rect('.awa-site-header .awa-main-header__inner.wp-header, .awa-site-header .awa-main-header__inner[data-awa-header-row]'),
            nav: rect('.awa-site-header .header-control.header-nav, .awa-site-header .header-control.awa-nav-bar, .awa-site-header .awa-nav-bar'),
            navInner: rect('.awa-site-header .header-control.header-nav > .container, .awa-site-header .awa-nav-bar__inner'),
            categories: rect('.awa-site-header .awa-header-categories.menu_left_home1'),
            categoryTrigger: rect('.awa-site-header .awa-header-categories.menu_left_home1 .our_categories.title-category-dropdown, .awa-site-header .awa-header-categories.menu_left_home1 button[data-role="awa-vertical-menu-trigger"]'),
            search: rect('.awa-site-header form#search_mini_form'),
            account: rect('.awa-site-header .awa-header-account-prompt'),
            cart: rect('.awa-site-header .awa-header-minicart .showcart, .awa-site-header .showcart.header-mini-cart'),
            quickLinks: rect('.awa-site-header .awa-nav-quick-links'),
          };
        }, { id: target.id, viewport: viewport.name, status: response?.status() ?? 0 });

        results.push(data);

        const prefix = `${viewport.name}/${target.id}`;
        if (target.noHeaderExpected) {
          if (data.header) {
            issues.push(`${prefix}: auth page unexpectedly rendered the storefront header`);
          }
          continue;
        }

        if (!data.header) {
          issues.push(`${prefix}: missing .awa-site-header`);
          continue;
        }

        if (!data.terminalStyle || !data.terminalScript) {
          issues.push(`${prefix}: terminal header lock was not injected`);
        }

        if (data.overflowX > 2) {
          issues.push(`${prefix}: horizontal overflow ${data.overflowX}px`);
        }

        if (viewport.name === 'desktop') {
          if (data.header.height < 140 || data.header.height > 175) {
            issues.push(`${prefix}: desktop header height ${data.header.height}px outside expected range`);
          }
          if (!data.search || data.search.height < 40 || data.search.height > 48) {
            issues.push(`${prefix}: search height invalid`);
          }
          if (!data.nav || data.nav.height < 46 || data.nav.height > 50) {
            issues.push(`${prefix}: nav height ${data.nav?.height ?? 0}px outside expected range`);
          }
          if (!data.categories || data.categories.display === 'none' || data.categories.visibility === 'hidden') {
            issues.push(`${prefix}: departments container hidden`);
          } else if (data.categories.left < -1 || data.categories.width < 180) {
            issues.push(`${prefix}: departments container offscreen or collapsed`);
          }
          if (!data.categoryTrigger || data.categoryTrigger.display === 'none' || data.categoryTrigger.width < 180) {
            issues.push(`${prefix}: departments trigger hidden or collapsed`);
          }
        } else {
          if (!data.search || data.search.height < 40 || data.search.height > 50) {
            issues.push(`${prefix}: responsive search height invalid`);
          }
          if (data.header.height > 220) {
            issues.push(`${prefix}: responsive header too tall (${data.header.height}px)`);
          }
        }
      }

      await testInfo.attach(`header-audit-${viewport.name}.json`, {
        body: JSON.stringify(results, null, 2),
        contentType: 'application/json',
      });

      console.log(JSON.stringify({ viewport: viewport.name, results, issues }, null, 2));
      expect.soft(issues).toEqual([]);
    });
  }
});
