import { test, expect } from '@playwright/test';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-b2b-dashboard-container.log';

function log(hypothesisId: string, message: string, data: unknown): void {
  const line = JSON.stringify({
    sessionId: 'b2b-container',
    runId: process.env.B2B_PROBE_RUN || 'probe-initial',
    hypothesisId,
    location: 'debug-b2b-dashboard-container-probe.spec.ts',
    message,
    data,
    timestamp: Date.now(),
  });
  fs.appendFileSync(LOG, line + '\n');
}

test('b2b dashboard container axis alignment', async ({ page, context }) => {
  test.setTimeout(60000);
  const viewportWidth = Number(process.env.B2B_PROBE_VW || 1920);
  await context.route('**/*', (route) => {
    route.continue({
      headers: { ...route.request().headers(), 'cache-control': 'no-cache', pragma: 'no-cache' },
    });
  });
  await page.setViewportSize({ width: viewportWidth, height: 900 });
  await page.goto('https://awamotos.com/b2b/account/dashboard?probe=' + Date.now(), {
    waitUntil: 'domcontentloaded',
    timeout: 60000,
  });

  const onLogin = page.url().includes('login');
  if (onLogin) {
    const loggedIn = await (async () => {
      const email = process.env.B2B_PROBE_EMAIL;
      const password = process.env.B2B_PROBE_PASSWORD;
      if (!email || !password) return false;
      await page.fill('#b2b-email, input[name="login[username]"]', email);
      await page.fill('#b2b-password, input[name="login[password]"]', password);
      await page.click('.b2b-btn-entrar, button.action.login, #send2');
      await page.waitForURL(/b2b\/account\/dashboard/, { timeout: 30000 }).catch(() => undefined);
      return page.url().includes('b2b/account/dashboard');
    })();
    if (!loggedIn) {
      log('SKIP', 'dashboard requires login — set B2B_PROBE_EMAIL and B2B_PROBE_PASSWORD', { url: page.url() });
      return;
    }
  }

  await page.waitForSelector('.b2b-dashboard, .page-main', { timeout: 15000 }).catch(() => undefined);
  await page.waitForTimeout(2500);

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
        paddingLeft: cs.paddingLeft,
        paddingRight: cs.paddingRight,
      };
    };
    const vw = window.innerWidth;
    const shellRefs = [
      pick('.b2b-breadcrumbs-container'),
      pick('.page-main'),
    ].filter(Boolean);
    const stackRefs = [
      pick('.column.main'),
      pick('.b2b-dashboard-header'),
      pick('.b2b-summary-cards'),
      pick('.b2b-chart-container'),
      pick('.b2b-section'),
    ].filter(Boolean);

    const spread = (refs: Array<{ left: number; width: number } | null>) => {
      const valid = refs.filter(Boolean) as Array<{ left: number; width: number }>;
      if (valid.length < 2) return { leftSpread: 0, widthSpread: 0 };
      const lefts = valid.map((r) => r.left);
      const widths = valid.map((r) => r.width);
      return {
        leftSpread: Math.max(...lefts) - Math.min(...lefts),
        widthSpread: Math.max(...widths) - Math.min(...widths),
      };
    };

    const shell = spread(shellRefs);
    const stack = spread(stackRefs);

    return {
      viewport: vw,
      bodyClass: document.body.className.slice(0, 120),
      shellRefs,
      stackRefs,
      shellAligned: shell.leftSpread <= 2,
      stackAligned: stack.leftSpread <= 2 && stack.widthSpread <= 2,
      shellSpread: shell.leftSpread,
      stackSpread: stack.leftSpread,
      stackWidthSpread: stack.widthSpread,
      columnsDisplay: document.querySelector('.page-main > .columns')
        ? getComputedStyle(document.querySelector('.page-main > .columns')!).display
        : null,
      columnsFlexDirection: document.querySelector('.page-main > .columns')
        ? getComputedStyle(document.querySelector('.page-main > .columns')!).flexDirection
        : null,
      mainColumnLeft: (() => {
        const main = document.querySelector('.column.main');
        return main ? Math.round(main.getBoundingClientRect().left) : null;
      })(),
      pageMainLeft: (() => {
        const pm = document.querySelector('.page-main');
        return pm ? Math.round(pm.getBoundingClientRect().left) : null;
      })(),
    };
  });

  log('A', 'shell axis metrics', {
    shellRefs: metrics.shellRefs,
    shellSpread: metrics.shellSpread,
    shellAligned: metrics.shellAligned,
  });
  log('B', 'stack axis metrics', {
    stackRefs: metrics.stackRefs,
    stackSpread: metrics.stackSpread,
    stackWidthSpread: metrics.stackWidthSpread,
    stackAligned: metrics.stackAligned,
    columnsDisplay: metrics.columnsDisplay,
  });

  if (metrics.shellRefs.length >= 2) {
    expect(metrics.shellAligned, `shell left spread ${metrics.shellSpread}px`).toBe(true);
  }
  if (metrics.stackRefs.length >= 2) {
    expect(metrics.stackAligned, `stack spread ${metrics.stackSpread}px / width ${metrics.stackWidthSpread}px`).toBe(true);
  }
  if (viewportWidth <= 768 && metrics.mainColumnLeft != null && metrics.pageMainLeft != null) {
    const mainInset = metrics.mainColumnLeft - metrics.pageMainLeft;
    expect(mainInset, `mobile main column inset ${mainInset}px (expect stacked, not sidebar+main)`).toBeLessThan(48);
    expect(metrics.columnsFlexDirection === 'column' || metrics.columnsDisplay === 'flex').toBe(true);
  }
});
