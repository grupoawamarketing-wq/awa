import { test, Page } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

const BASE_URL = 'https://awamotos.com';
const SCREENSHOT_DIR = path.join(__dirname, '../../screenshots/full-ux-audit');
const REPORT_PATH = path.join(__dirname, '../../full-ux-audit-report.json');

const DESKTOP = { width: 1280, height: 800 };
const MOBILE  = { width: 390,  height: 844 };

interface Issue {
  page: string;
  viewport: string;
  severity: 'Critical' | 'High' | 'Medium' | 'Low';
  category: string;
  description: string;
  detail?: string;
}

const issues: Issue[] = [];

function addIssue(issue: Issue) {
  issues.push(issue);
  console.log(`  ⚠ [${issue.severity}][${issue.viewport}] ${issue.category}: ${issue.description}`);
}

async function shot(page: Page, name: string) {
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
  const p = path.join(SCREENSHOT_DIR, `${name}.png`);
  await page.screenshot({ path: p, fullPage: true, timeout: 30_000 }).catch(() => {});
  console.log(`  📸 ${name}.png`);
}

async function auditHorizontalOverflow(page: Page, pageName: string, viewport: string) {
  const result = await page.evaluate(() => {
    const docW = document.documentElement.scrollWidth;
    const winW = window.innerWidth;
    if (docW <= winW + 2) return null;
    const culprits: string[] = [];
    document.querySelectorAll('*').forEach(el => {
      const r = el.getBoundingClientRect();
      if (r.right > winW + 5) {
        culprits.push(`${el.tagName}.${String(el.className).split(' ')[0]}`);
      }
    });
    return { docW, winW, culprits: culprits.slice(0, 6) };
  });
  if (result) {
    addIssue({
      page: pageName, viewport,
      severity: viewport === 'Mobile' ? 'Critical' : 'High',
      category: 'Responsividade / Overflow Horizontal',
      description: `Página tem scroll horizontal (doc: ${result.docW}px, janela: ${result.winW}px)`,
      detail: `Elementos suspeitos: ${result.culprits.join(' | ')}`,
    });
  }
}

async function auditFontSizes(page: Page, pageName: string, viewport: string) {
  const tiny = await page.evaluate(() => {
    const out: { tag: string; text: string; size: string }[] = [];
    document.querySelectorAll('p, span, a, li, button, label, td, th').forEach(el => {
      if (out.length >= 10) return;
      const text = (el.textContent || '').trim();
      if (!text || text.length < 2) return;
      const r = el.getBoundingClientRect();
      if (!r.width || !r.height) return;
      const sz = parseFloat(window.getComputedStyle(el).fontSize);
      if (sz > 0 && sz < 11) out.push({ tag: el.tagName, text: text.slice(0, 40), size: `${sz}px` });
    });
    return out;
  });
  if (tiny.length) {
    addIssue({
      page: pageName, viewport,
      severity: viewport === 'Mobile' ? 'High' : 'Medium',
      category: 'Tipografia / Fonte Pequena',
      description: `${tiny.length} elemento(s) com fonte abaixo de 11px`,
      detail: tiny.map(t => `${t.tag} "${t.text}" → ${t.size}`).join(' | '),
    });
  }
}

async function auditBrokenImages(page: Page, pageName: string, viewport: string) {
  // Scroll to trigger lazy-loaded images before checking
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await page.waitForTimeout(800);
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(300);
  const broken = await page.evaluate(() =>
    Array.from(document.querySelectorAll('img'))
      .filter(i => (!i.complete || i.naturalWidth === 0) && i.getAttribute('loading') !== 'lazy')
      .map(i => i.src.replace(/^https?:\/\/[^/]+/, ''))
      .slice(0, 8)
  );
  if (broken.length) {
    addIssue({
      page: pageName, viewport,
      severity: 'High',
      category: 'Visual / Imagens Quebradas',
      description: `${broken.length} imagem(ns) não carregou`,
      detail: broken.join(' | '),
    });
  }
}

async function auditTouchTargets(page: Page, pageName: string) {
  const small = await page.evaluate(() => {
    const out: { tag: string; text: string; w: number; h: number }[] = [];
    document.querySelectorAll('a, button, [role="button"]').forEach(el => {
      if (out.length >= 12) return;
      const r = el.getBoundingClientRect();
      if (!r.width || !r.height) return;
      if (r.width < 44 || r.height < 44) {
        out.push({ tag: el.tagName, text: (el.textContent || '').trim().slice(0, 30), w: Math.round(r.width), h: Math.round(r.height) });
      }
    });
    return out;
  });
  if (small.length) {
    addIssue({
      page: pageName, viewport: 'Mobile',
      severity: 'Medium',
      category: 'UX / Touch Targets Pequenos',
      description: `${small.length} elemento(s) interativo(s) abaixo de 44×44px`,
      detail: small.map(e => `${e.tag} "${e.text}" → ${e.w}×${e.h}px`).join(' | '),
    });
  }
}

async function runAudit(page: Page, url: string, pageName: string) {
  console.log(`\n━━━ ${pageName} ━━━`);

  for (const [vp, label] of [[DESKTOP, 'Desktop'], [MOBILE, 'Mobile']] as const) {
    await page.setViewportSize(vp);
    try {
      await page.goto(url, { waitUntil: 'networkidle', timeout: 35000 });
    } catch {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 35000 });
    }
    await page.waitForTimeout(1500);
    await shot(page, `${pageName.replace(/\s/g, '_').toLowerCase()}_${label.toLowerCase()}`);
    await auditHorizontalOverflow(page, pageName, label);
    await auditFontSizes(page, pageName, label);
    await auditBrokenImages(page, pageName, label);
    if (label === 'Mobile') await auditTouchTargets(page, pageName);
  }
}

// ──────────────────────────────────────────────
test.describe('AwaMotos – Full UX Audit', () => {
  test.setTimeout(300000);

  test('1 – Homepage', async ({ page }) => {
    await runAudit(page, `${BASE_URL}/`, 'Homepage');

    // Hero slider check (desktop)
    await page.setViewportSize(DESKTOP);
    await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle', timeout: 35000 });
    const heroOk = await page.isVisible('.slick-slider, .pagebuilder-slider, .hero-slider, [data-content-type="slider"], .banner-slider, .owl-carousel, .swiper-wrapper').catch(() => false);
    if (!heroOk) addIssue({ page: 'Homepage', viewport: 'Desktop', severity: 'High', category: 'Visual / Hero', description: 'Hero slider/banner não encontrado ou não visível' });

    // Hamburger menu (mobile)
    await page.setViewportSize(MOBILE);
    await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle', timeout: 35000 });
    const hamburguerOk = await page.isVisible('.nav-toggle, .menu-toggle, [data-action="toggle-nav"]').catch(() => false);
    if (!hamburguerOk) addIssue({ page: 'Homepage', viewport: 'Mobile', severity: 'High', category: 'UX / Navegação', description: 'Botão hambúrguer do menu mobile não encontrado' });

    // Search bar on mobile
    const searchOk = await page.isVisible('#search, input[name="q"], .block-search').catch(() => false);
    if (!searchOk) addIssue({ page: 'Homepage', viewport: 'Mobile', severity: 'High', category: 'UX / Busca', description: 'Campo de busca não visível no mobile' });
  });

  test('2 – Categoria (Peças)', async ({ page }) => {
    // Discover a real category URL from the homepage nav
    await page.setViewportSize(DESKTOP);
    await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle', timeout: 35000 });
    const catUrl: string = await page.evaluate((base) => {
      const links = Array.from(document.querySelectorAll('nav a, .navigation a, .nav-sections a')) as HTMLAnchorElement[];
      const link = links.find(l => l.href && l.href.startsWith(base) && l.href !== base + '/' && !l.href.includes('#'));
      return link?.href || `${base}/pecas-para-motos.html`;
    }, BASE_URL);

    console.log(`  → Category URL: ${catUrl}`);
    await runAudit(page, catUrl, 'Categoria');

    // Product grid sanity
    await page.setViewportSize(DESKTOP);
    await page.goto(catUrl, { waitUntil: 'networkidle', timeout: 35000 });
    const count = await page.locator('.product-item, .item-product').count();
    if (count === 0) addIssue({ page: 'Categoria', viewport: 'Desktop', severity: 'Critical', category: 'Visual / Grade de Produtos', description: 'Nenhum card de produto encontrado na página de categoria' });
    else console.log(`  ✓ ${count} produtos encontrados`);

    // Sidebar/filters on mobile
    await page.setViewportSize(MOBILE);
    await page.goto(catUrl, { waitUntil: 'networkidle', timeout: 35000 });
    await shot(page, 'categoria_mobile_filtros');
  });

  test('3 – Produto (PDP)', async ({ page }) => {
    // Find a real product URL
    await page.setViewportSize(DESKTOP);
    await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle', timeout: 35000 });
    const pdpUrl: string = await page.evaluate((base) => {
      const links = Array.from(document.querySelectorAll('a')) as HTMLAnchorElement[];
      const link = links.find(l =>
        l.href.startsWith(base) &&
        (l.href.endsWith('.html') || !!l.closest('.item-product')) &&
        !l.href.includes('category') &&
        !l.href.includes('search') &&
        l.href !== base + '/'
      );
      return link?.href || `${base}/filtro-de-oleo.html`;
    }, BASE_URL);

    console.log(`  → PDP URL: ${pdpUrl}`);
    await runAudit(page, pdpUrl, 'PDP');

    await page.setViewportSize(DESKTOP);
    await page.goto(pdpUrl, { waitUntil: 'networkidle', timeout: 35000 });

    const addToCart = await page.isVisible('#product-addtocart-button, .action.tocart').catch(() => false);
    if (!addToCart) addIssue({ page: 'PDP', viewport: 'Desktop', severity: 'Critical', category: 'UX / Carrinho', description: 'Botão "Adicionar ao Carrinho" não visível' });

    const priceBox = await page.isVisible('.price-box, .product-info-price').catch(() => false);
    if (!priceBox) addIssue({ page: 'PDP', viewport: 'Desktop', severity: 'High', category: 'UX / Preço', description: 'Preço do produto não visível (B2B restrito?)' });

    const gallery = await page.isVisible('.fotorama, .product.media, .gallery-placeholder, .product-image-container, .product-image').catch(() => false);
    if (!gallery) addIssue({ page: 'PDP', viewport: 'Desktop', severity: 'High', category: 'Visual / Galeria', description: 'Galeria de imagens do produto não visível' });
  });

  test('4 – Busca', async ({ page }) => {
    const searchUrl = `${BASE_URL}/catalogsearch/result/?q=filtro+de+oleo`;
    await runAudit(page, searchUrl, 'Busca');

    await page.setViewportSize(DESKTOP);
    await page.goto(searchUrl, { waitUntil: 'networkidle', timeout: 35000 });
    const resultCount = await page.locator('.product-item, .item-product').count();
    const noResultsMsg = await page.isVisible('.message.notice').catch(() => false);
    console.log(`  → Resultados: ${resultCount} produtos, sem-resultado: ${noResultsMsg}`);
    if (resultCount === 0 && noResultsMsg) {
      addIssue({ page: 'Busca', viewport: 'Desktop', severity: 'Medium', category: 'UX / Resultados', description: 'Busca por "filtro de oleo" retornou 0 resultados' });
    }
  });

  test('5 – Login', async ({ page }) => {
    await runAudit(page, `${BASE_URL}/customer/account/login/`, 'Login');

    await page.setViewportSize(MOBILE);
    await page.goto(`${BASE_URL}/customer/account/login/`, { waitUntil: 'networkidle', timeout: 35000 });

    // Standard login redirects to B2B login — selectors include both standard and B2B form
    const email = await page.isVisible('#email, #b2b-email, input[name="login[username]"]').catch(() => false);
    const pass  = await page.isVisible('#pass, #b2b-pass, input[name="login[password]"]').catch(() => false);
    const btn   = await page.isVisible('#send2, .action.login, .b2b-btn-entrar, button[type="submit"]').catch(() => false);
    if (!email || !pass || !btn) {
      addIssue({ page: 'Login', viewport: 'Mobile', severity: 'Critical', category: 'UX / Formulário', description: `Formulário incompleto no mobile – email:${email} senha:${pass} botão:${btn}` });
    }
  });

  test.afterAll(async () => {
    const report = {
      auditDate: new Date().toISOString(),
      totalIssues: issues.length,
      bySeverity: {
        Critical: issues.filter(i => i.severity === 'Critical').length,
        High:     issues.filter(i => i.severity === 'High').length,
        Medium:   issues.filter(i => i.severity === 'Medium').length,
        Low:      issues.filter(i => i.severity === 'Low').length,
      },
      issues,
    };
    fs.mkdirSync(path.dirname(REPORT_PATH), { recursive: true });
    fs.writeFileSync(REPORT_PATH, JSON.stringify(report, null, 2));
    console.log('\n════════ AUDIT CONCLUÍDO ════════');
    console.log(`Total: ${report.totalIssues} issues`);
    console.log(JSON.stringify(report.bySeverity, null, 2));
    console.log(`Relatório: ${REPORT_PATH}`);
  });
});
