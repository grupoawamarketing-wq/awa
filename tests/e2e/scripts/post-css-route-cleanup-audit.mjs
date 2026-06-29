import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const projectRoot = '/home/user/htdocs/srv1113343.hstgr.cloud';
const e2eRoot = path.join(projectRoot, 'tests/e2e');
const shotsDir = path.join(e2eRoot, 'shots/post-css-route-cleanup-2026-06-25');
const artifactsDir = path.join(e2eRoot, 'artifacts/post-css-route-cleanup-2026-06-25');

const routes = [
  { key: 'home', label: 'Home', url: 'https://awamotos.com/' },
  { key: 'plp', label: 'PLP', url: 'https://awamotos.com/retrovisores.html' },
  { key: 'pdp', label: 'PDP', url: 'https://awamotos.com/bagageiros/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html' },
  { key: 'search_real', label: 'Busca real', url: 'https://awamotos.com/catalogsearch/result/?q=bagageiro' },
  { key: 'b2b_login', label: 'B2B Login', url: 'https://awamotos.com/b2b/account/login/' },
  { key: 'b2b_register', label: 'B2B Register', url: 'https://awamotos.com/b2b/register/' },
  { key: 'cart', label: 'Carrinho', url: 'https://awamotos.com/checkout/cart/' }
];

const viewports = [
  { key: 'vp-1440x900', width: 1440, height: 900 },
  { key: 'vp-768x1024', width: 768, height: 1024 },
  { key: 'vp-390x844', width: 390, height: 844 }
];

const normalizeUrl = (u) => {
  try {
    const x = new URL(u);
    x.hash = '';
    return x.toString();
  } catch {
    return u;
  }
};

const getRedirectChain = (response) => {
  const chain = [];
  if (!response) return chain;
  let req = response.request();
  const stack = [];
  while (req) {
    stack.push(req.url());
    req = req.redirectedFrom();
  }
  return stack.reverse();
};

async function waitStable(page) {
  try { await page.waitForLoadState('domcontentloaded', { timeout: 45000 }); } catch {}
  try { await page.waitForLoadState('networkidle', { timeout: 20000 }); } catch {}
  await page.waitForTimeout(1500);
}

function inferOrigin(href) {
  try {
    const u = new URL(href);
    const marker = '/pt_BR/';
    const idx = u.pathname.indexOf(marker);
    const tail = idx >= 0 ? u.pathname.slice(idx + marker.length) : '';
    if (tail.startsWith('css/')) return 'AWA Theme (child)';
    if (tail.startsWith('Magento_')) return 'Magento Core Module';
    if (tail.startsWith('Rokanthemes_')) return 'Ayo/Rokanthemes Module';
    if (tail.startsWith('GrupoAwamotos_')) return 'AWA Custom Module';
    if (tail.startsWith('Ayo_')) return 'Ayo Custom Module';
    return 'Indeterminado';
  } catch {
    return 'Indeterminado';
  }
}

function classifyBucket(file) {
  const n = file.toLowerCase();
  if (n.includes('styles-l') || n.includes('print.css') || n.includes('custom_default') || n.includes('header-mobile-grid-critical')) return 'A';
  if (n.includes('round9') || n.includes('round10') || n.includes('components-b2b-foundation') || n.includes('compat-b2b-nav')) return 'B';
  if (n.includes('page-home') || n.includes('page-b2b-cart-checkout')) return 'C';
  if (n.includes('audit') || n.includes('bugfix') || n.includes('terminal') || n.includes('hotfix') || n.includes('visual-qa-fixes')) return 'D';
  if (n.includes('bundle') || n.includes('promax') || n.includes('impeccable')) return 'E';
  return 'F';
}

function riskByBucket(bucket) {
  if (bucket === 'A') return 'Alto';
  if (bucket === 'B') return 'Médio';
  if (bucket === 'C') return 'Baixo';
  if (bucket === 'D') return 'Médio';
  if (bucket === 'E') return 'Médio';
  return 'Investigação';
}

async function getLocalSizeFromStaticUrl(href) {
  try {
    const u = new URL(href);
    const m = u.pathname.match(/^\/static\/version\d+\/(.+)$/);
    if (!m) return null;
    const rel = m[1];
    const fp = path.join(projectRoot, 'pub/static', rel);
    const st = await fs.stat(fp);
    return st.size;
  } catch {
    return null;
  }
}

async function auditScenario(browser, route, vp, report) {
  const context = await browser.newContext({
    viewport: { width: vp.width, height: vp.height },
    ignoreHTTPSErrors: true
  });
  const page = await context.newPage();

  const consoleErrors = [];
  const consoleWarnings = [];
  const networkErrors = [];

  page.on('console', (msg) => {
    const item = { type: msg.type(), text: msg.text() };
    if (msg.type() === 'error') consoleErrors.push(item);
    if (msg.type() === 'warning') consoleWarnings.push(item);
  });

  page.on('requestfailed', (req) => {
    networkErrors.push({
      kind: 'requestfailed',
      url: req.url(),
      method: req.method(),
      resourceType: req.resourceType(),
      reason: req.failure()?.errorText || 'unknown'
    });
  });

  page.on('response', (res) => {
    const status = res.status();
    if (status >= 400) {
      networkErrors.push({
        kind: 'http',
        status,
        url: res.url(),
        method: res.request().method(),
        resourceType: res.request().resourceType()
      });
    }
  });

  let mainResponse = null;
  try {
    mainResponse = await page.goto(route.url, { waitUntil: 'domcontentloaded', timeout: 90000 });
  } catch (e) {
    report.scenarios.push({
      route: route.key,
      viewport: vp.key,
      url: route.url,
      fatal: true,
      error: String(e)
    });
    await context.close();
    return;
  }

  await waitStable(page);

  const cssLinks = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map((el, idx) => ({
      order: idx + 1,
      href: el.href,
      media: el.media || '',
      onload: el.getAttribute('onload') || '',
      inNoscript: !!el.closest('noscript')
    }));
  });

  const htmlSanity = await page.evaluate(() => ({
    hasHtml: !!document.documentElement,
    hasHead: !!document.head,
    hasBody: !!document.body,
    hasMain: !!document.querySelector('main, [role="main"], #maincontent')
  }));

  const screenshotName = `${route.key}__${vp.key}.png`;
  const screenshotPath = path.join(shotsDir, screenshotName);
  await page.screenshot({ path: screenshotPath, fullPage: true });

  report.scenarios.push({
    route: route.key,
    routeLabel: route.label,
    viewport: vp.key,
    requestedUrl: route.url,
    finalUrl: page.url(),
    httpStatus: mainResponse?.status() ?? null,
    redirectChain: getRedirectChain(mainResponse),
    htmlSanity,
    cssTotal: cssLinks.length,
    cssLinks,
    consoleErrors,
    consoleWarnings,
    networkErrors,
    screenshot: path.relative(e2eRoot, screenshotPath)
  });

  await context.close();
}

async function pdpDeepAudit(browser, report) {
  const route = routes.find((r) => r.key === 'pdp');
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const page = await context.newPage();

  const reqSizeMap = new Map();
  page.on('requestfinished', async (req) => {
    if (req.resourceType() !== 'stylesheet') return;
    try {
      const s = await req.sizes();
      reqSizeMap.set(normalizeUrl(req.url()), s);
    } catch {}
  });

  const cdp = await context.newCDPSession(page);
  await cdp.send('DOM.enable');
  await cdp.send('CSS.enable');
  const sheetUrlById = new Map();
  cdp.on('CSS.styleSheetAdded', (ev) => {
    const id = ev?.header?.styleSheetId;
    const src = ev?.header?.sourceURL || '';
    if (id) sheetUrlById.set(id, src);
  });

  await cdp.send('CSS.startRuleUsageTracking');
  const resp = await page.goto(route.url, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await waitStable(page);
  const cssLinks = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map((el, idx) => ({
      order: idx + 1,
      href: el.href,
      media: el.media || '',
      onload: el.getAttribute('onload') || '',
      inNoscript: !!el.closest('noscript')
    }));
  });

  const stop = await cdp.send('CSS.stopRuleUsageTracking');
  const usage = stop?.ruleUsage || [];
  const covBySheet = new Map();
  for (const ru of usage) {
    if (!covBySheet.has(ru.styleSheetId)) covBySheet.set(ru.styleSheetId, { total: 0, used: 0 });
    const obj = covBySheet.get(ru.styleSheetId);
    obj.total += 1;
    if (ru.used) obj.used += 1;
  }

  const covByUrl = new Map();
  for (const [id, cov] of covBySheet.entries()) {
    const url = sheetUrlById.get(id);
    if (!url) continue;
    const n = normalizeUrl(url);
    if (!covByUrl.has(n)) covByUrl.set(n, { total: 0, used: 0 });
    const agg = covByUrl.get(n);
    agg.total += cov.total;
    agg.used += cov.used;
  }

  const pdpCssMap = [];
  for (const link of cssLinks) {
    const hrefN = normalizeUrl(link.href);
    const filename = (() => {
      try { return path.basename(new URL(link.href).pathname); } catch { return link.href; }
    })();
    const cov = covByUrl.get(hrefN) || { total: 0, used: 0 };
    const usedPct = cov.total > 0 ? Math.round((cov.used / cov.total) * 100) : null;
    const sizes = reqSizeMap.get(hrefN) || null;
    const localSize = await getLocalSizeFromStaticUrl(link.href);
    const bucket = classifyBucket(filename);

    pdpCssMap.push({
      order: link.order,
      href: link.href,
      file: filename,
      origin: inferOrigin(link.href),
      blocking: !link.inNoscript && !(link.media === 'print' && /this\.media\s*=\s*['\"]all['\"]/.test(link.onload)),
      async: !link.inNoscript && (link.media === 'print' && /this\.media\s*=\s*['\"]all['\"]/.test(link.onload)),
      noscriptFallback: link.inNoscript,
      transferredBytes: sizes?.responseBodySize ?? null,
      decodedBytesEstimate: localSize,
      usedRulesEstimate: cov.total > 0 ? `${cov.used}/${cov.total} (${usedPct}%)` : 'n/a',
      bucket,
      duplicateFunctional: ['D','E'].includes(bucket) ? 'Possível' : 'Não evidente',
      removalRisk: riskByBucket(bucket),
      consolidationCandidate: ['C','D','E'].includes(bucket) ? 'Sim' : 'Avaliar'
    });
  }

  const pdpSelectors = {
    header: 'header, .toki_header, .page-header',
    busca: 'input[type="search"], #search, .block-search input',
    menuDepartamentos: '.verticalmenu, .menu_left_home1, .navigation.verticalmenu',
    breadcrumb: '.breadcrumbs',
    galeria: '[data-gallery-role="gallery-placeholder"], .gallery-placeholder, .fotorama__stage',
    titulo: '.page-title .base, .product-info-main .page-title',
    skuRef: '.product.attribute.sku, [itemprop="sku"], .product.attribute.ref',
    precoB2B: '.product-info-price, .price-box, .b2b-login-to-see-price',
    ctaLoginCadastro: '.b2b-login-to-buy-btn, .b2b-login-to-see-price, a[href*="/b2b/account/login"], a[href*="/b2b/register"]',
    botaoCompra: '#product-addtocart-button, .action.tocart',
    tabs: '.product.info.detailed, .product.data.items, .data.item.title',
    relacionados: '.block.related, .products-related, .block.upsell',
    footer: 'footer, .page_footer'
  };

  const pdpComputed = await page.evaluate((selectors) => {
    const out = {};
    const stylePick = (el) => {
      const cs = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return {
        display: cs.display,
        visibility: cs.visibility,
        opacity: cs.opacity,
        position: cs.position,
        width: Math.round(r.width),
        height: Math.round(r.height),
        x: Math.round(r.x),
        y: Math.round(r.y)
      };
    };
    for (const [k, sel] of Object.entries(selectors)) {
      const els = Array.from(document.querySelectorAll(sel));
      const vis = els.find((el) => {
        const r = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        return r.width > 1 && r.height > 1 && cs.visibility !== 'hidden' && cs.display !== 'none' && parseFloat(cs.opacity || '1') > 0;
      });
      out[k] = {
        selector: sel,
        found: els.length,
        visible: !!vis,
        style: vis ? stylePick(vis) : null
      };
    }
    out.mobileOverflow = {
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth
    };
    return out;
  }, pdpSelectors);

  report.pdp = {
    url: route.url,
    finalUrl: page.url(),
    httpStatus: resp?.status() ?? null,
    cssTotal: cssLinks.length,
    cssMap: pdpCssMap,
    dependencyCheck: pdpComputed
  };

  await context.close();
}

async function homeHeroAudit(browser, report) {
  const route = routes.find((r) => r.key === 'home');
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const page = await context.newPage();
  await page.goto(route.url, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await waitStable(page);

  const hero = await page.evaluate(async () => {
    const visible = (el) => {
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return r.width > 20 && r.height > 20 && cs.display !== 'none' && cs.visibility !== 'hidden' && parseFloat(cs.opacity || '1') > 0;
    };

    const dupIds = (() => {
      const ids = Array.from(document.querySelectorAll('[id]')).map((e) => e.id);
      const map = new Map();
      for (const id of ids) map.set(id, (map.get(id) || 0) + 1);
      return Array.from(map.entries()).filter(([, c]) => c > 1).map(([id, count]) => ({ id, count }));
    })();

    const heroCandidates = Array.from(document.querySelectorAll('.owl-item, .owl-carousel .item, .slide-banner .item, [class*="hero"], [id*="hero"]'))
      .map((el) => {
        const r = el.getBoundingClientRect();
        const img = el.querySelector('img');
        return {
          tag: el.tagName,
          classes: el.className,
          id: el.id || null,
          y: Math.round(r.y),
          w: Math.round(r.width),
          h: Math.round(r.height),
          visible: visible(el),
          ariaHidden: el.getAttribute('aria-hidden'),
          imgSrc: img?.currentSrc || img?.src || null
        };
      })
      .filter((x) => x.h > 120 && x.w > 300 && x.y < 1500);

    const owlCloned = document.querySelectorAll('.owl-item.cloned').length;
    const owlLoaded = document.querySelectorAll('.owl-carousel.owl-loaded').length;
    const owlTotal = document.querySelectorAll('.owl-carousel').length;

    const byImg = new Map();
    for (const c of heroCandidates) {
      if (!c.imgSrc) continue;
      if (!byImg.has(c.imgSrc)) byImg.set(c.imgSrc, []);
      byImg.get(c.imgSrc).push(c);
    }

    const duplicateVisualBlocks = Array.from(byImg.entries())
      .filter(([, arr]) => arr.length > 1)
      .map(([src, arr]) => ({
        src,
        total: arr.length,
        visibleNow: arr.filter((x) => x.visible).length,
        items: arr.slice(0, 6)
      }));

    await new Promise((r) => setTimeout(r, 1800));
    const visibleAfter = Array.from(document.querySelectorAll('.owl-item, .owl-carousel .item, .slide-banner .item, [class*="hero"], [id*="hero"]'))
      .filter((el) => {
        const rr = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        return rr.height > 120 && rr.width > 300 && rr.y < 1500 && cs.display !== 'none' && cs.visibility !== 'hidden' && parseFloat(cs.opacity || '1') > 0;
      }).length;

    return {
      owlTotal,
      owlLoaded,
      owlCloned,
      heroCandidatesCount: heroCandidates.length,
      duplicateVisualBlocks,
      duplicateIdsTop20: dupIds.slice(0, 20),
      visibleHeroLikeAfterDelay: visibleAfter
    };
  });

  report.homeHero = hero;
  await context.close();
}

async function main() {
  await fs.mkdir(shotsDir, { recursive: true });
  await fs.mkdir(artifactsDir, { recursive: true });

  const report = {
    generatedAt: new Date().toISOString(),
    shotsDir: path.relative(e2eRoot, shotsDir),
    artifactsDir: path.relative(e2eRoot, artifactsDir),
    scenarios: [],
    pdp: null,
    homeHero: null,
    notes: []
  };

  const browser = await chromium.launch({ headless: true, args: ['--disable-dev-shm-usage'] });

  for (const vp of viewports) {
    for (const route of routes) {
      // eslint-disable-next-line no-await-in-loop
      await auditScenario(browser, route, vp, report);
      console.log(`captured ${route.key} @ ${vp.key}`);
    }
  }

  await pdpDeepAudit(browser, report);
  await homeHeroAudit(browser, report);

  await browser.close();

  const jsonPath = path.join(artifactsDir, 'post-css-route-cleanup-audit.json');
  await fs.writeFile(jsonPath, JSON.stringify(report, null, 2), 'utf8');

  const markdown = [];
  markdown.push('# Post CSS Route Cleanup Audit — 2026-06-25');
  markdown.push('');
  markdown.push(`- Gerado em: ${report.generatedAt}`);
  markdown.push(`- Screenshots: \`${report.shotsDir}\``);
  markdown.push(`- Artefatos: \`${report.artifactsDir}\``);
  markdown.push('');
  markdown.push('## Cenários por rota e viewport');
  markdown.push('');
  markdown.push('| Rota | Viewport | HTTP | URL final | CSS | Console errors | Network errors | Screenshot |');
  markdown.push('|---|---|---:|---|---:|---:|---:|---|');
  for (const s of report.scenarios) {
    markdown.push(`| ${s.route} | ${s.viewport} | ${s.httpStatus ?? 'n/a'} | ${s.finalUrl ?? 'n/a'} | ${s.cssTotal ?? 'n/a'} | ${(s.consoleErrors || []).length} | ${(s.networkErrors || []).length} | \`${s.screenshot || '-'}\` |`);
  }

  if (report.pdp?.cssMap?.length) {
    markdown.push('');
    markdown.push('## Mapa CSS PDP (ordem de carregamento)');
    markdown.push('');
    markdown.push('| # | Arquivo | Origem | Transferido (B) | Decodificado (B) | Bloqueante | Async | Regras usadas (est.) | Classe A-F | Risco | Candidato |');
    markdown.push('|---:|---|---|---:|---:|---|---|---|---|---|---|');
    for (const c of report.pdp.cssMap) {
      markdown.push(`| ${c.order} | ${c.file} | ${c.origin} | ${c.transferredBytes ?? 'n/a'} | ${c.decodedBytesEstimate ?? 'n/a'} | ${c.blocking ? 'Sim' : 'Não'} | ${c.async ? 'Sim' : 'Não'} | ${c.usedRulesEstimate} | ${c.bucket} | ${c.removalRisk} | ${c.consolidationCandidate} |`);
    }
  }

  const mdPath = path.join(artifactsDir, 'post-css-route-cleanup-audit.md');
  await fs.writeFile(mdPath, markdown.join('\n'), 'utf8');

  console.log(`json: ${jsonPath}`);
  console.log(`md: ${mdPath}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
