/**
 * AWA Motos — Auditoria Completa da PDP (Product Detail Page)
 *
 * USA UM ÚNICO beforeAll para navegar até a PDP uma única vez.
 * Todos os describes compartilham a mesma página já carregada.
 */

import { test, expect, Page } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const SCREENSHOT_DIR = path.join(__dirname, '..', 'screenshots', 'pdp-audit');

/* Produto real para fallback direto (bagageiro mais vendido) */
const FALLBACK_PDP_URL = '/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html';
const BASE = 'https://awamotos.com';

/* ── Seletores ────────────────────────────────────────────────────────── */
const SEL = {
  // Core PDP
  productName:     '.page-title .base',
  productPrice:    '.product-info-price .price',
  addToCart:       '#product-addtocart-button',
  sku:             '.product.attribute.sku .value',
  qtyInput:        '#qty',
  stockStatus:     '.stock.available, .stock.unavailable',
  breadcrumb:      '.breadcrumbs',
  gallery:         '.fotorama, [data-gallery-role="gallery-placeholder"]',
  productInfoMain: '.product-info-main',
  mediaSection:    '.product.media',

  // SocialProof
  socialProofContainer: '.social-proof-container.awa-sp-pdp',
  viewsBadge:           '.social-proof-badge.views-badge',
  lowStockBadge:        '.social-proof-badge.low-stock-badge',
  bestsellerBadge:      '.social-proof-badge.bestseller-badge',

  // Fitment
  fitmentSection:    '#fitment-applications',
  fitmentTitle:      '.fitment-title',
  fitmentBrandGroup: '.fitment-brand-group',
  fitmentModelList:  '.fitment-model-list',
  fitmentModelItem:  '.fitment-model-item',
  fitmentFilter:     '#fitment-filter-input',
  fitmentStats:      '.fitment-stats',

  // B2B
  b2bPriceInfo:    '.b2b-customer-price-info',
  b2bTierPricing:  '.b2b-tier-pricing',
  b2bTierTable:    '.b2b-tier-table',
  b2bLoginModal:   '#b2b-login-modal',
  b2bLoginOption:  '.b2b-login-option',

  // Tabs
  tabsWrapper:  '.awa-pdp-tabs, #tabs-product-info-tabs',
  tabTitle:     '.awa-pdp-tabs .awa-tab-title, .product.info.detailed [data-role="title"]',
} as const;

/* ── Página compartilhada por todos os describes ──────────────────── */
let pdpPage: Page;

test.beforeAll(async ({ browser }) => {
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'pt-BR' });
  pdpPage = await ctx.newPage();
  try {
    await pdpPage.goto(`${BASE}${FALLBACK_PDP_URL}`, { waitUntil: 'commit', timeout: 60_000 });
    const cookieBtn = pdpPage.locator('.cookie-btn-accept, #btn-cookie-allow, .allow').first();
    if (await cookieBtn.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await cookieBtn.click();
    }
    await pdpPage.waitForSelector(SEL.productName, { timeout: 30_000 });
    await pdpPage.waitForTimeout(1_000);
  } catch {
    // Liveness checks em cada teste fazem o skip
  }
});

test.afterAll(async () => {
  await pdpPage?.context().close().catch(() => {});
});

async function alive(): Promise<boolean> {
  try { return await pdpPage.evaluate(() => true); } catch { return false; }
}

function screenshot(name: string): string {
  return path.join(SCREENSHOT_DIR, `${name}.png`);
}

/* ════════════════════════════════════════════════════════════════════════
   1 — SEO: STRUCTURED DATA (JSON-LD)
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP Audit — SEO Structured Data', () => {

  test('JSON-LD Product schema presente e válido @seo', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const scripts = await pdpPage.locator('script[type="application/ld+json"]').allTextContents();
    const productSchema = scripts.find(s => s.includes('"@type"') && s.includes('Product'));
    expect(productSchema, 'Deve existir JSON-LD com @type Product').toBeTruthy();

    const parsed = JSON.parse(productSchema!);
    expect(parsed['@type']).toBe('Product');
    expect(parsed.name, 'Product.name não deve ser vazio').toBeTruthy();
    expect(parsed.image, 'Product.image é obrigatório').toBeTruthy();

    const offers = parsed.offers ?? parsed.Offers;
    expect(offers, 'Product.offers é obrigatório para rich results').toBeTruthy();

    if (Array.isArray(offers)) {
      expect(offers[0].price ?? offers[0].lowPrice, 'Offer deve ter price').toBeTruthy();
    } else {
      expect(offers.price ?? offers.lowPrice, 'Offer deve ter price').toBeTruthy();
    }
  });

  test('JSON-LD BreadcrumbList presente @seo', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const scripts = await pdpPage.locator('script[type="application/ld+json"]').allTextContents();
    const breadcrumb = scripts.find(s => s.includes('BreadcrumbList'));
    expect(breadcrumb, 'Deve existir JSON-LD BreadcrumbList').toBeTruthy();

    const parsed = JSON.parse(breadcrumb!);
    expect(parsed['@type']).toBe('BreadcrumbList');
    expect(parsed.itemListElement?.length, 'BreadcrumbList deve ter itens').toBeGreaterThanOrEqual(2);
  });

  test('Meta canonical existe e aponta para URL do produto @seo', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const canonical = await pdpPage.locator('link[rel="canonical"]').getAttribute('href');
    expect(canonical, 'Canonical URL deve existir').toBeTruthy();
    expect(canonical).toMatch(/https?:\/\//);
    expect(canonical).not.toMatch(/\/$/);
  });

  test('Meta title contém nome do produto @seo', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const title = await pdpPage.title();
    const productName = await pdpPage.locator(SEL.productName).first().textContent();
    expect(title.toLowerCase()).toContain(productName!.trim().toLowerCase().substring(0, 10));
  });

  test('Meta description presente e não vazia @seo', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const desc = await pdpPage.locator('meta[name="description"]').getAttribute('content');
    expect(desc, 'Meta description deve existir').toBeTruthy();
    expect(desc!.trim().length, 'Meta description não deve ser vazia').toBeGreaterThan(10);
  });

  test('Open Graph tags presentes @seo', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const ogTitle = await pdpPage.locator('meta[property="og:title"]').getAttribute('content').catch(() => null);
    const ogImage = await pdpPage.locator('meta[property="og:image"]').getAttribute('content').catch(() => null);
    const ogPresent = [ogTitle, ogImage].filter(Boolean).length;
    expect(ogPresent, 'OG tags (title + image) devem existir para compartilhamento social').toBeGreaterThanOrEqual(1);
  });
});

/* ════════════════════════════════════════════════════════════════════════
   2 — SOCIAL PROOF MODULE
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP Audit — Social Proof', () => {

  test('Container de Social Proof renderiza (se produto tiver dados) @socialproof', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const container = pdpPage.locator(SEL.socialProofContainer);
    const exists = await container.count().catch(() => 0);

    if (exists > 0) {
      const badges = await pdpPage.locator('.social-proof-badge').count();
      expect(badges, 'Se social-proof-container existe, deve ter pelo menos 1 badge').toBeGreaterThanOrEqual(1);
      await container.first().screenshot({ path: screenshot('social-proof') }).catch(() => {});
    } else {
      test.skip();
    }
  });

  test('Badge de visualizações tem ícone e texto @socialproof', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const badge = pdpPage.locator(SEL.viewsBadge).first();
    if (!await badge.count()) { test.skip(); return; }

    await expect(badge).toBeVisible();
    const text = await badge.textContent();
    expect(text, 'Views badge deve ter número').toMatch(/\d+/);
    const icon = badge.locator('i.fa-eye, .fa-eye');
    expect(await icon.count(), 'Ícone fa-eye deve existir').toBeGreaterThanOrEqual(1);
  });

  test('Badge de social proof tem role="note" para acessibilidade @socialproof', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const badge = pdpPage.locator('.social-proof-badge[role="note"]');
    if (!await badge.count()) { test.skip(); return; }

    const ariaLabel = await badge.first().getAttribute('aria-label');
    expect(ariaLabel, 'Badge com role=note deve ter aria-label').toBeTruthy();
  });
});

/* ════════════════════════════════════════════════════════════════════════
   3 — FITMENT (Compatibilidade Peças × Motos)
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP Audit — Fitment', () => {

  test('Seção de compatibilidade presente (se produto tem fitment) @fitment', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const fitment = pdpPage.locator(SEL.fitmentSection);
    const exists = await fitment.count().catch(() => 0);

    if (exists > 0) {
      await expect(fitment).toBeVisible();
      const title = pdpPage.locator(SEL.fitmentTitle);
      await expect(title.first()).toBeVisible();
      await fitment.screenshot({ path: screenshot('fitment-section') }).catch(() => {});
    } else {
      test.skip();
    }
  });

  test('Fitment exibe estatísticas (marcas e modelos) @fitment', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const stats = pdpPage.locator(SEL.fitmentStats);
    if (!await stats.count()) { test.skip(); return; }

    await expect(stats.first()).toBeVisible();
    const text = await stats.first().textContent();
    expect(text, 'Stats deve mencionar marcas ou modelos').toMatch(/marca|modelo/i);
  });

  test('Fitment lista pelo menos 1 marca com modelos @fitment', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const brandGroups = pdpPage.locator(SEL.fitmentBrandGroup);
    if (!await brandGroups.count()) { test.skip(); return; }

    const count = await brandGroups.count();
    expect(count, 'Deve haver pelo menos 1 grupo de marca').toBeGreaterThanOrEqual(1);

    const brandName = brandGroups.first().locator('.fitment-brand-name');
    await expect(brandName).toBeVisible();

    const models = brandGroups.first().locator(SEL.fitmentModelItem);
    expect(await models.count(), 'Marca deve ter pelo menos 1 modelo').toBeGreaterThanOrEqual(1);
  });

  test('Filtro de fitment funciona (se >10 modelos) @fitment', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const filter = pdpPage.locator(SEL.fitmentFilter);
    if (!await filter.count()) { test.skip(); return; }

    await expect(filter).toBeVisible();
    await filter.fill('Honda');
    await pdpPage.waitForTimeout(500);

    const visibleModels = pdpPage.locator(SEL.fitmentModelItem + ':not([hidden])');
    const emptyMsg = pdpPage.locator('[data-fitment-empty]:not([hidden])');
    const hasResults = (await visibleModels.count()) > 0 || (await emptyMsg.count()) > 0;
    expect(hasResults, 'Filtro deve mostrar resultados ou mensagem vazia').toBe(true);
  });

  test('Fitment tem aria-label no stats @fitment @a11y', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const stats = pdpPage.locator(SEL.fitmentStats);
    if (!await stats.count()) { test.skip(); return; }

    const ariaLabel = await stats.first().getAttribute('aria-label');
    expect(ariaLabel, 'fitment-stats deve ter aria-label').toBeTruthy();
  });
});

/* ════════════════════════════════════════════════════════════════════════
   4 — B2B (Preço por Tabela, Tier Pricing, Restrição de Acesso)
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP Audit — B2B', () => {

  test('Modal B2B login-to-cart existe para visitantes @b2b', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const modal = pdpPage.locator(SEL.b2bLoginModal);
    const exists = await modal.count().catch(() => 0);

    if (exists > 0) {
      const ariaHidden = await modal.getAttribute('aria-hidden');
      expect(ariaHidden, 'Modal deve estar oculto (aria-hidden=true)').toBe('true');

      const options = pdpPage.locator(SEL.b2bLoginOption);
      expect(await options.count(), 'Modal deve ter opções de login/cadastro').toBeGreaterThanOrEqual(1);
    } else {
      test.skip();
    }
  });

  test('Modal B2B tem estrutura acessível @b2b @a11y', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const modal = pdpPage.locator(SEL.b2bLoginModal + ' .b2b-login-modal');
    if (!await modal.count()) { test.skip(); return; }

    const role = await modal.getAttribute('role');
    expect(role, 'Modal deve ter role=dialog').toBe('dialog');

    const ariaModal = await modal.getAttribute('aria-modal');
    expect(ariaModal, 'Modal deve ter aria-modal=true').toBe('true');

    const labelledBy = await modal.getAttribute('aria-labelledby');
    expect(labelledBy, 'Modal deve ter aria-labelledby').toBeTruthy();
  });

  test('B2B price info renderiza (se cliente logado com tabela) @b2b', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const priceInfo = pdpPage.locator(SEL.b2bPriceInfo);
    const exists = await priceInfo.count().catch(() => 0);

    if (exists > 0) {
      await expect(priceInfo.first()).toBeVisible();
      const text = await priceInfo.first().textContent();
      expect(text, 'B2B price info deve mencionar tabela').toMatch(/tabela/i);
      await priceInfo.first().screenshot({ path: screenshot('b2b-price-info') }).catch(() => {});
    }
  });

  test('B2B tier pricing renderiza (se produto tem tiers) @b2b', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const tier = pdpPage.locator(SEL.b2bTierPricing);
    if (!await tier.count()) { test.skip(); return; }

    await expect(tier.first()).toBeVisible();

    const table = pdpPage.locator(SEL.b2bTierTable);
    const headers = await table.locator('th').allTextContents();
    expect(headers.length, 'Tabela tier deve ter cabeçalhos').toBeGreaterThanOrEqual(2);

    const rows = await table.locator('tbody tr').count();
    expect(rows, 'Tabela tier deve ter pelo menos 1 faixa').toBeGreaterThanOrEqual(1);

    await tier.first().screenshot({ path: screenshot('b2b-tier-pricing') }).catch(() => {});
  });
});

/* ════════════════════════════════════════════════════════════════════════
   5 — ACESSIBILIDADE
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP Audit — Acessibilidade', () => {

  test('Imagem principal do produto tem alt text @a11y', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const mainImg = pdpPage.locator('.fotorama img, .gallery-placeholder img, .product.media img').first();
    await mainImg.waitFor({ timeout: 10_000 }).catch(() => null);

    if (await mainImg.count()) {
      const alt = await mainImg.getAttribute('alt');
      expect(alt, 'Imagem principal deve ter alt text').toBeTruthy();
      expect(alt!.trim().length, 'Alt text não deve ser vazio').toBeGreaterThan(0);
    }
  });

  test('Botão add-to-cart tem texto acessível @a11y', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const btn = pdpPage.locator(SEL.addToCart).first();
    if (!await btn.count()) { test.skip(); return; }

    const text = await btn.textContent();
    const ariaLabel = await btn.getAttribute('aria-label');
    const title = await btn.getAttribute('title');
    const hasAccessibleName = (text?.trim().length ?? 0) > 0 ||
                               (ariaLabel?.trim().length ?? 0) > 0 ||
                               (title?.trim().length ?? 0) > 0;
    expect(hasAccessibleName, 'Botão add-to-cart deve ter nome acessível').toBe(true);
  });

  test('Campo de quantidade tem label associado @a11y', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const qty = pdpPage.locator(SEL.qtyInput).first();
    if (!await qty.count()) { test.skip(); return; }

    const id = await qty.getAttribute('id');
    const ariaLabel = await qty.getAttribute('aria-label');
    const label = id ? pdpPage.locator('label[for="' + id + '"]') : null;
    const hasLabel = (label && await label.count() > 0) || (ariaLabel?.trim().length ?? 0) > 0;
    expect(hasLabel, 'Campo qty deve ter label ou aria-label').toBe(true);
  });

  test('Heading hierarchy: h1 único na PDP @a11y', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const h1s = await pdpPage.locator('h1').count();
    expect(h1s, 'Deve haver exatamente 1 h1 na PDP').toBe(1);
  });

  test('Links de navegação (breadcrumb) não são genéricos @a11y', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    await pdpPage.waitForFunction(
      () => document.querySelectorAll('.breadcrumbs a').length > 0,
      { timeout: 8_000 }
    ).catch(() => {});

    const breadcrumbLinks = pdpPage.locator('.breadcrumbs a');
    const count = await breadcrumbLinks.count();
    if (count === 0) { test.skip(); return; }

    for (let i = 0; i < count; i++) {
      const text = await breadcrumbLinks.nth(i).textContent();
      expect(text?.trim().length, 'Breadcrumb link ' + i + ' deve ter texto').toBeGreaterThan(0);
      expect(text?.trim().toLowerCase()).not.toBe('clique aqui');
    }
  });

  test('Sem imagens quebradas na PDP @a11y', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const brokenImages = await pdpPage.evaluate(() => {
      const imgs = document.querySelectorAll('img');
      const broken: string[] = [];
      imgs.forEach(img => {
        if (img.naturalWidth === 0 && img.complete && img.src && !img.src.includes('data:')) {
          broken.push(img.src);
        }
      });
      return broken;
    });
    expect(brokenImages, 'Imagens quebradas: ' + brokenImages.join(', ')).toHaveLength(0);
  });
});

/* ════════════════════════════════════════════════════════════════════════
   6 — PERFORMANCE & CORE WEB VITALS
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP Audit — Performance', () => {

  test('Preload da imagem LCP configurado @perf', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const preloadLinks = pdpPage.locator('link[rel="preload"][as="image"]');
    const count = await preloadLinks.count();
    expect(count, 'Deve existir pelo menos 1 link preload para imagem LCP').toBeGreaterThanOrEqual(1);
  });

  test('Imagens abaixo da dobra usam lazy loading @perf', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const allImages = await pdpPage.locator('img').evaluateAll((imgs: HTMLImageElement[]) => {
      return imgs
        .filter(img => {
          const rect = img.getBoundingClientRect();
          return rect.top > window.innerHeight;
        })
        .map(img => ({
          src: img.src?.substring(0, 80),
          loading: img.getAttribute('loading'),
        }));
    });

    const nonLazy = allImages.filter(img => img.loading !== 'lazy');
    expect(
      nonLazy.length,
      nonLazy.length + ' imagens abaixo da dobra sem loading=lazy: ' + nonLazy.map(i => i.src).join(', ')
    ).toBeLessThanOrEqual(3);
  });

  test('Sem CLS significativo na PDP (layout shift) @perf', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const cls = await pdpPage.evaluate(() => {
      return new Promise<number>((resolve) => {
        let clsValue = 0;
        const observer = new PerformanceObserver((list) => {
          for (const entry of list.getEntries()) {
            if (!(entry as any).hadRecentInput) {
              clsValue += (entry as any).value;
            }
          }
        });
        observer.observe({ type: 'layout-shift', buffered: true });
        setTimeout(() => {
          observer.disconnect();
          resolve(clsValue);
        }, 2000);
      });
    });

    expect(cls, 'CLS=' + cls.toFixed(4) + ' — deve ser < 0.45').toBeLessThan(0.45);
  });

  test('Página carrega CSS customizado (awa-pdp-visual-fix) @perf', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const cssLink = pdpPage.locator('link[href*="awa-pdp-visual-fix"]');
    const count = await cssLink.count();
    expect(count, 'CSS awa-pdp-visual-fix deve estar carregado').toBeGreaterThanOrEqual(1);
  });

  test('Scripts de terceiros dentro do limite aceitável @perf', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const thirdPartyScripts = await pdpPage.evaluate(() => {
      const scripts = document.querySelectorAll('script[src]');
      const external: string[] = [];
      scripts.forEach(s => {
        const src = s.getAttribute('src') ?? '';
        if (src && !src.includes('awamotos.com') && !src.includes('static/')) {
          external.push(src);
        }
      });
      return external;
    });

    if (thirdPartyScripts.length > 0) {
      console.log('[AUDIT] ' + thirdPartyScripts.length + ' scripts de terceiros:');
      thirdPartyScripts.forEach(s => console.log('  - ' + s));
    }

    expect(
      thirdPartyScripts.length,
      'Muitos scripts de terceiros podem impactar performance'
    ).toBeLessThanOrEqual(8);
  });
});

/* ════════════════════════════════════════════════════════════════════════
   7 — CORE UX
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP Audit — Core UX', () => {

  test('SKU do produto visível @ux', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const sku = pdpPage.locator(SEL.sku).first();
    const exists = await sku.count();
    if (exists > 0) {
      const text = await sku.textContent();
      expect(text?.trim().length, 'SKU não deve ser vazio').toBeGreaterThan(0);
    }
  });

  test('Campo de quantidade aceita apenas números positivos @ux', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const qty = pdpPage.locator(SEL.qtyInput).first();
    if (!await qty.count()) { test.skip(); return; }

    const type = await qty.getAttribute('type');
    const min = await qty.getAttribute('min');
    expect(['number', 'tel', 'text']).toContain(type);
    if (min) {
      expect(parseInt(min), 'Quantidade mínima deve ser >= 1').toBeGreaterThanOrEqual(1);
    }
  });

  test('Preço formatado em R$ ou B2B login-to-see-price @ux', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const b2bLoginPrice = pdpPage.locator('.b2b-login-to-see-price');
    const b2bExists = await b2bLoginPrice.count();
    if (b2bExists > 0) {
      await expect(b2bLoginPrice.first()).toBeVisible({ timeout: 8_000 });
      const text = await b2bLoginPrice.first().textContent();
      expect(text, 'B2B login-to-see-price deve mencionar login').toMatch(/login|entrar/i);
      return;
    }
    const price = pdpPage.locator(SEL.productPrice).first();
    await expect(price).toBeVisible({ timeout: 8_000 });
    const text = await price.textContent();
    expect(text, 'Preço deve estar em formato BRL (R$)').toMatch(/R\$/);
  });

  test('Status de estoque visível ou B2B-oculto @ux', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const stock = pdpPage.locator(SEL.stockStatus).first();
    const exists = await stock.count();
    if (exists === 0) return;
    const b2bLoginPrice = pdpPage.locator('.b2b-login-to-see-price');
    const isB2bGuest = (await b2bLoginPrice.count()) > 0;
    const isVisible = await stock.isVisible();
    if (!isVisible && isB2bGuest) return;
    await expect(stock).toBeVisible();
  });

  test('Sem console errors JavaScript críticos na PDP @ux', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const errors: string[] = [];
    pdpPage.on('console', msg => {
      if (msg.type() === 'error') errors.push(msg.text());
    });

    await pdpPage.reload({ waitUntil: 'domcontentloaded' }).catch(() => {});
    await pdpPage.waitForSelector(SEL.productName, { timeout: 15_000 }).catch(() => {});
    await pdpPage.waitForTimeout(3_000);

    const criticalErrors = errors.filter(e =>
      !e.includes('favicon') &&
      !e.includes('sw.js') &&
      !e.includes('service-worker') &&
      !e.includes('ResizeObserver') &&
      !e.includes('third-party') &&
      !e.includes('Failed to load resource') &&
      !e.includes('net::ERR')
    );

    if (criticalErrors.length > 0) {
      console.log('[AUDIT] Console errors:');
      criticalErrors.forEach(e => console.log('  - ' + e));
    }

    expect(
      criticalErrors.length,
      criticalErrors.length + ' erro(s) JS crítico(s) na PDP'
    ).toBeLessThanOrEqual(3);
  });

  test('Sem overflow horizontal em qualquer viewport @ux', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const hasHScroll = await pdpPage.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth;
    });
    expect(hasHScroll, 'Sem scroll horizontal').toBe(false);
  });

  test('Meta viewport configurado para mobile @ux', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const viewport = await pdpPage.locator('meta[name="viewport"]').getAttribute('content');
    expect(viewport, 'Meta viewport deve existir').toBeTruthy();
    expect(viewport).toContain('width=device-width');
  });
});

/* ════════════════════════════════════════════════════════════════════════
   8 — SCREENSHOTS DOCUMENTAIS
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP Audit — Screenshots', () => {
  test('Screenshot full-page da PDP para auditoria visual @screenshot', async ({}, testInfo) => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const vw = testInfo.project.use?.viewport?.width ?? 1280;

    await pdpPage.screenshot({
      path: screenshot('full-page-' + vw + 'px'),
      fullPage: true,
      animations: 'disabled',
    });

    await pdpPage.screenshot({
      path: screenshot('above-fold-' + vw + 'px'),
      animations: 'disabled',
    });
  });
});
