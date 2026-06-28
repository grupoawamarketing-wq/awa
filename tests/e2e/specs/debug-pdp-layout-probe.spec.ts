import { test } from '@playwright/test';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-73277e.log';

function log(hypothesisId: string, message: string, data: unknown): void {
  const line = JSON.stringify({
    sessionId: '73277e',
    runId: process.env.PDP_PROBE_RUN || 'probe-initial',
    hypothesisId,
    location: 'debug-pdp-layout-probe.spec.ts',
    message,
    data,
    timestamp: Date.now(),
  });
  fs.appendFileSync(LOG, line + '\n');
}

test.describe.configure({ timeout: 90_000 });

test('pdp layout probe 6000', async ({ page, context }) => {
  const viewportWidth = Number(process.env.PDP_PROBE_VW || 1920);
  await context.route('**/*', (route) => {
    route.continue({ headers: { ...route.request().headers(), 'cache-control': 'no-cache', pragma: 'no-cache' } });
  });
  await page.setViewportSize({ width: viewportWidth, height: 900 });
  await page.goto(
    'https://awamotos.com/manetes/manete-embreagem-cg-titan-125-150-95-99-titan-00-titan-160-16-25-preto-6000.html?probe=' + Date.now(),
    { waitUntil: 'load', timeout: 90_000 }
  );
  await page.waitForTimeout(8000);

  const metrics = await page.evaluate(() => {
    const pick = (sel: string) => {
      const el = document.querySelector(sel);
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return {
        sel,
        left: Math.round(r.left),
        right: Math.round(r.right),
        width: Math.round(r.width),
        maxWidth: cs.maxWidth,
        marginLeft: cs.marginLeft,
        marginRight: cs.marginRight,
        marginInline: cs.marginInline,
        paddingLeft: cs.paddingLeft,
        paddingRight: cs.paddingRight,
      };
    };
    const vw = window.innerWidth;
    const pageMain =
      pick('#maincontent.page-main') || pick('.page-main.container') || pick('main.page-main');
    const nav = pick('.nav-breadcrumbs');
    const pw = pick('.page-wrapper');
    const header = pick('.awa-nav-bar__inner') || pick('.awa-main-header__inner');
    return {
      viewport: vw,
      asymmetry: pageMain
        ? {
            leftGap: pageMain.left,
            rightGap: vw - pageMain.right,
            centered: Math.abs(pageMain.left - (vw - pageMain.right)) <= 2,
          }
        : null,
      pageWrapper: pw,
      navBreadcrumbs: nav,
      pageMain,
      headerInner: header,
      productView: pick('.product-view'),
      mainDetailRow: pick('.main-detail > .row'),
      productMedia: pick('.product.media'),
      productInfo: pick('.product-info-main'),
      columnMain: pick('.column.main'),
    };
  });

  log('A', 'page-main centering', metrics.pageMain);
  log('B', 'nav-breadcrumbs centering', metrics.navBreadcrumbs);
  log('C', 'page-wrapper full width', metrics.pageWrapper);
  log('D', 'header vs page-main left edge', {
    headerLeft: metrics.headerInner?.left,
    pageMainLeft: metrics.pageMain?.left,
    navLeft: metrics.navBreadcrumbs?.left,
  });
  log('E', 'asymmetry check', metrics.asymmetry);

  const parentChain = await page.evaluate(() => {
    const el =
      document.querySelector('#maincontent.page-main') ||
      document.querySelector('.page-main.container');
    if (!el) return null;
    const chain: Array<Record<string, string | number>> = [];
    let node: Element | null = el;
    for (let i = 0; i < 5 && node; i++) {
      const r = node.getBoundingClientRect();
      const cs = getComputedStyle(node);
      chain.push({
        tag: node.tagName.toLowerCase(),
        id: (node as HTMLElement).id || '',
        className: (node.className || '').toString().slice(0, 80),
        left: Math.round(r.left),
        width: Math.round(r.width),
        maxWidth: cs.maxWidth,
        marginLeft: cs.marginLeft,
        marginRight: cs.marginRight,
        marginInline: cs.marginInline,
        display: cs.display,
      });
      node = node.parentElement;
    }
    return chain;
  });
  log('F', 'page-main parent chain', parentChain);
  const colMetrics = await page.evaluate(() => {
    const pick = (el: Element | null) => {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return {
        left: Math.round(r.left),
        right: Math.round(r.right),
        width: Math.round(r.width),
        display: cs.display,
        flex: cs.flex,
        maxWidth: cs.maxWidth,
        float: cs.float,
      };
    };
    const row = document.querySelector('.main-detail > .row');
    const cols = row ? Array.from(row.querySelectorAll(':scope > .col-md-6')) : [];
    return {
      row: pick(row),
      cols: cols.map((c, i) => ({ index: i, ...pick(c) })),
    };
  });
  log('H', 'main-detail row flex + col-md-6 boxes', colMetrics);

  const cssDiag = await page.evaluate(async () => {
    const row = document.querySelector('.main-detail > .row');
    const cs = row ? getComputedStyle(row) : null;
    const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map((l) => {
      const el = l as HTMLLinkElement;
      return { href: el.href, name: el.href.split('/').pop()?.split('?')[0] || '', media: el.media, disabled: el.disabled };
    });
    const refine = links.find((l) => l.name.includes('awa-bundle-refinements'));
    const gridHits: Array<{ sheet: string; snippet: string }> = [];
    const flexHits: Array<{ sheet: string; snippet: string }> = [];
    for (const link of links) {
      try {
        const res = await fetch(link.href);
        const text = await res.text();
        const gridNeedle = 'main-detail>.row{display:grid';
        const flexNeedle = 'main-detail>.row{display:flex';
        let idx = text.indexOf(gridNeedle);
        while (idx !== -1) {
          gridHits.push({ sheet: link.name, snippet: text.slice(idx, idx + 120) });
          idx = text.indexOf(gridNeedle, idx + 1);
        }
        idx = text.indexOf(flexNeedle);
        while (idx !== -1) {
          flexHits.push({ sheet: link.name, snippet: text.slice(idx, idx + 120) });
          idx = text.indexOf(flexNeedle, idx + 1);
        }
      } catch {
        /* skip */
      }
    }
    const inlineStyles = Array.from(document.querySelectorAll('style')).map((s) => s.textContent || '');
    for (const [i, text] of inlineStyles.entries()) {
      if (text.includes('main-detail') && text.includes('display:grid')) {
        gridHits.push({ sheet: `inline-style-${i}`, snippet: text.slice(text.indexOf('main-detail'), text.indexOf('main-detail') + 120) });
      }
      if (text.includes('main-detail') && text.includes('display:flex')) {
        flexHits.push({ sheet: `inline-style-${i}`, snippet: text.slice(text.indexOf('main-detail'), text.indexOf('main-detail') + 120) });
      }
    }
    return {
      rowDisplay: cs?.display,
      refineLoaded: !!refine,
      refineMedia: refine?.media,
      stylesheetCount: links.length,
      sheetOrder: links.map((l) => l.name),
      gridHits,
      flexHits,
    };
  });
  log('I', 'css cascade diagnostics', cssDiag);

  log('G', 'inner PDP grid widths vs page-main', {
    pageMainWidth: metrics.pageMain?.width,
    columnMain: metrics.columnMain,
    productView: metrics.productView,
    mainDetailRow: metrics.mainDetailRow,
    productMedia: metrics.productMedia,
    productInfo: metrics.productInfo,
    innerRightGap:
      metrics.pageMain && metrics.mainDetailRow
        ? metrics.pageMain.right - metrics.mainDetailRow.right
        : null,
  });
});
