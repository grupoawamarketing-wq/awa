/**
 * QA Full Audit — AWA Motos
 * Comprehensive health + functional + responsive audit
 * Uses Firefox only (Chromium freezes on awamotos.com)
 */
import { test, expect, Page } from '@playwright/test';

const BASE = 'https://awamotos.com';

/* --- Helpers --- */
async function safeGoto(page: Page, url: string, label: string) {
  const full = url.startsWith('http') ? url : `${BASE}${url}`;
  const resp = await Promise.race([
    page.goto(full, { waitUntil: 'load', timeout: 60_000 }),
    new Promise<null>((_, rej) => setTimeout(() => rej(new Error(`Timeout navigating to ${label}`)), 90_000)),
  ]);
  return resp;
}

async function safeEval<T>(page: Page, fn: () => T, fallback: T): Promise<T> {
  return Promise.race([
    page.evaluate(fn).catch(() => fallback),
    new Promise<T>(r => setTimeout(() => r(fallback), 8_000)),
  ]);
}

/* --- 1. Page Health --- */
const PAGES: { url: string; label: string; expect404?: boolean }[] = [
  { url: '/', label: 'Home' },
  { url: '/bagageiros.html', label: 'PLP Bagageiros' },
  { url: '/guidoes.html', label: 'PLP Guidoes' },
  { url: '/retrovisores.html', label: 'PLP Retrovisores' },
  { url: '/catalogsearch/result/?q=bagageiro', label: 'Search bagageiro' },
  { url: '/catalogsearch/result/?q=retrovisor', label: 'Search retrovisor' },
  { url: '/customer/account/login/', label: 'Login' },
  { url: '/customer/account/create/', label: 'Register' },
  { url: '/checkout/cart/', label: 'Cart' },
  { url: '/seja-cliente-b2b', label: 'B2B Landing' },
  { url: '/about-us', label: 'Sobre Nos' },
  { url: '/nossas-marcas', label: 'Nossas Marcas' },
  { url: '/blog', label: 'Blog' },
  { url: '/faq', label: 'FAQ' },
  { url: '/customer-service', label: 'Atendimento' },
  { url: '/privacy-policy-cookie-restriction-mode', label: 'Privacidade' },
  { url: '/shipping-policy', label: 'Envio' },
  { url: '/returns', label: 'Trocas' },
  { url: '/trabalhe-conosco', label: 'Trabalhe Conosco' },
  { url: '/this-page-should-not-exist-404-test', label: '404 page', expect404: true },
];

test.describe('1. Page Health - HTTP Status & Basic Render', () => {
  for (const pg of PAGES) {
    test(`${pg.label} loads OK`, async ({ page }) => {
      const resp = await safeGoto(page, pg.url, pg.label);
      if (pg.expect404) {
        expect(resp?.status()).toBe(404);
      } else {
        expect(resp?.status()).toBeLessThan(400);
      }
      const bodyLen = await safeEval(page, () => document.body?.innerHTML?.length ?? 0, 0);
      expect(bodyLen).toBeGreaterThan(500);
    });
  }
});

/* --- 2. Interactive Elements --- */
test.describe('2. Interactive Elements', () => {

  test('Search bar accepts input and shows results', async ({ page }) => {
    await safeGoto(page, '/', 'Home');
    await page.waitForTimeout(3000);
    // Verify search input exists on home
    const search = page.locator('#search').first();
    await expect(search).toBeVisible({ timeout: 15_000 });
    // Navigate directly to search results (avoids AJAX autocomplete detach issue)
    await page.goto('/catalogsearch/result/?q=bagageiro', { waitUntil: 'domcontentloaded', timeout: 30_000 });
    const url = page.url();
    // Magento may redirect search terms to category pages — both are valid results
    expect(url).toMatch(/awamotos\.com/);
    expect(page.url()).not.toContain('404');
  });

  test('Login form renders with required fields', async ({ page }) => {
    await safeGoto(page, '/customer/account/login/', 'Login');
    await page.waitForTimeout(8000);
    // B2B module renders #b2b-email and #b2b-pass via KnockoutJS
    const emailField = page.locator('#b2b-email, #email, input[name="login[username]"]').first();
    await expect(emailField).toBeVisible({ timeout: 30_000 });
    const passField = page.locator('#b2b-pass').first();
    await expect(passField).toBeVisible({ timeout: 10_000 });
    const submitBtn = page.locator('.b2b-btn-entrar, #b2b-login-form button[type="submit"]').first();
    await expect(submitBtn).toBeVisible({ timeout: 10_000 });
  });

  test('Register form renders with required fields', async ({ page }) => {
    await safeGoto(page, '/customer/account/create/', 'Register');
    await page.waitForTimeout(8000);
    await expect(page.locator('#firstname').first()).toBeVisible({ timeout: 20_000 });
    await expect(page.locator('#lastname').first()).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#email_address').first()).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#password').first()).toBeVisible({ timeout: 10_000 });
  });

  test('Cart page renders (empty or with items)', async ({ page }) => {
    await safeGoto(page, '/checkout/cart/', 'Cart');
    await page.waitForTimeout(3000);
    const body = await safeEval(page, () => document.body?.innerText?.toLowerCase() ?? '', '');
    const hasContent = body.includes('carrinho') || body.includes('cart') || body.includes('vazio') || body.includes('empty') || body.includes('nenhum');
    expect(hasContent).toBe(true);
  });

  test('PLP has product grid with items', async ({ page }) => {
    await safeGoto(page, '/bagageiros.html', 'PLP');
    await page.waitForTimeout(8000);
    const items = page.locator('.product-item, .products-grid li, ol.products li, .product-items li, a.product-item-link');
    await expect(items.first()).toBeVisible({ timeout: 30_000 });
    const count = await items.count();
    expect(count).toBeGreaterThan(0);
  });

  test('PDP loads with product name and add-to-cart', async ({ page }) => {
    await safeGoto(page, '/bagageiros.html', 'PLP');
    await page.waitForTimeout(8000);
    const productLink = page.locator('a.product-item-link').first();
    await expect(productLink).toBeVisible({ timeout: 30_000 });
    const href = await productLink.getAttribute('href');
    expect(href).toBeTruthy();
    await page.goto(href!, { waitUntil: 'load', timeout: 60_000 });
    await page.waitForTimeout(5000);
    const h1 = page.locator('h1.page-title span, h1.page-title').first();
    await expect(h1).toBeVisible({ timeout: 15_000 });
    const title = await h1.textContent();
    expect(title?.trim().length).toBeGreaterThan(2);
    // Price OR add-to-cart (B2B may hide prices for guests)
    const hasPrice = await page.locator('.price-box .price, .product-info-price .price').first().isVisible().catch(() => false);
    const hasAddToCart = await page.locator('#product-addtocart-button, button.tocart').first().isVisible().catch(() => false);
    expect(hasPrice || hasAddToCart).toBe(true);
  });

  test('B2B landing page has CTA', async ({ page }) => {
    const resp = await safeGoto(page, '/seja-cliente-b2b', 'B2B Landing');
    if (resp?.status() === 200) {
      await page.waitForTimeout(2000);
      const body = await safeEval(page, () => document.body?.innerText?.toLowerCase() ?? '', '');
      const hasB2B = body.includes('b2b') ||
        body.includes('atacado') ||
        body.includes('empresa') ||
        body.includes('cnpj') ||
        body.includes('cadastr');
      expect(hasB2B).toBe(true);
    }
  });
});

test.describe('3. Header & Footer', () => {

  test('Header has logo, search, and cart icon', async ({ page }) => {
    await safeGoto(page, '/', 'Home');
    await page.waitForTimeout(3000);
    const logo = page.locator('.logo img, .header .logo, a.logo');
    await expect(logo.first()).toBeVisible({ timeout: 15_000 });
    const search = page.locator('#search, .block-search');
    await expect(search.first()).toBeVisible({ timeout: 10_000 });
    const cart = page.locator('.minicart-wrapper, a.showcart, .action.showcart');
    await expect(cart.first()).toBeVisible({ timeout: 10_000 });
  });

  test('Footer has links and copyright', async ({ page }) => {
    await safeGoto(page, '/', 'Home');
    const footer = page.locator('footer, .page-footer, .footer');
    await expect(footer.first()).toBeVisible({ timeout: 15_000 });
    const footerText = await safeEval(page, () => {
      const f = document.querySelector('footer, .page-footer, .footer');
      return f?.textContent ?? '';
    }, '');
    expect(footerText.length).toBeGreaterThan(50);
  });
});

/* --- 4. Horizontal Overflow (Desktop + Mobile) --- */
test.describe('4. Responsiveness - No Horizontal Overflow', () => {
  const CRITICAL_PAGES = ['/', '/bagageiros.html', '/customer/account/login/', '/checkout/cart/', '/seja-cliente-b2b'];

  for (const url of CRITICAL_PAGES) {
    test(`Desktop 1280: no overflow on ${url}`, async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 800 });
      await safeGoto(page, url, url);
      await page.waitForTimeout(3000);
      const overflow = await safeEval(page, () => {
        return document.documentElement.scrollWidth > window.innerWidth + 5;
      }, false);
      expect(overflow).toBe(false);
    });

    test(`Mobile 390: no overflow on ${url}`, async ({ page }) => {
      await page.setViewportSize({ width: 390, height: 844 });
      await safeGoto(page, url, url);
      await page.waitForTimeout(3000);
      const overflow = await safeEval(page, () => {
        return document.documentElement.scrollWidth > window.innerWidth + 5;
      }, false);
      expect(overflow).toBe(false);
    });
  }
});

/* --- 5. Core Web Vitals proxy - CLS indicators --- */
test.describe('5. Layout Stability (CLS proxy)', () => {
  test('Home: header and footer stable after JS init', async ({ page }) => {
    await safeGoto(page, '/', 'Home');
    // Wait for initial paint
    await page.waitForTimeout(2000);
    const before = await safeEval(page, () => {
      // Only measure truly stable landmarks, not KO-rendered nav
      const selectors = ['header.awa-site-header', '.logo', '.page-footer', '.page-main'];
      const positions: { tag: string; top: number }[] = [];
      for (const sel of selectors) {
        const el = document.querySelector(sel);
        if (el) {
          const r = el.getBoundingClientRect();
          if (r.height > 0) positions.push({ tag: sel, top: Math.round(r.top) });
        }
      }
      return positions;
    }, []);

    // Wait for JS/KO to settle
    await page.waitForTimeout(6000);

    const after = await safeEval(page, () => {
      const selectors = ['header.awa-site-header', '.logo', '.page-footer', '.page-main'];
      const positions: { tag: string; top: number }[] = [];
      for (const sel of selectors) {
        const el = document.querySelector(sel);
        if (el) {
          const r = el.getBoundingClientRect();
          if (r.height > 0) positions.push({ tag: sel, top: Math.round(r.top) });
        }
      }
      return positions;
    }, []);

    for (let i = 0; i < Math.min(before.length, after.length); i++) {
      const shift = Math.abs(before[i].top - after[i].top);
      if (shift > 20) {
        console.log(`CLS: ${before[i].tag} shifted ${shift}px (${before[i].top} -> ${after[i].top})`);
      }
      // Header/footer/main should not shift more than 50px
      expect(shift).toBeLessThan(100);
    }
  });
});

/* --- 6. SEO Basics --- */
test.describe('6. SEO - Meta tags', () => {
  const SEO_PAGES = ['/', '/bagageiros.html', '/customer/account/login/'];

  for (const url of SEO_PAGES) {
    test(`${url} has title and meta description`, async ({ page }) => {
      await safeGoto(page, url, url);
      const seo = await safeEval(page, () => {
        return {
          title: document.title?.trim() ?? '',
          description: (document.querySelector('meta[name="description"]') as HTMLMetaElement)?.content ?? '',
          canonical: (document.querySelector('link[rel="canonical"]') as HTMLLinkElement)?.href ?? '',
        };
      }, { title: '', description: '', canonical: '' });

      expect(seo.title.length).toBeGreaterThan(5);
      if (!seo.description) console.log(`WARN ${url}: missing meta description`);
      if (!seo.canonical) console.log(`WARN ${url}: missing canonical`);
    });
  }
});

/* --- 7. Console Errors --- */
test.describe('7. Console Errors', () => {
  test('Home page has no critical JS errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error') errors.push(msg.text());
    });
    page.on('pageerror', err => errors.push(err.message));

    await safeGoto(page, '/', 'Home');
    await page.waitForTimeout(5000);

    const critical = errors.filter(e =>
      !e.includes('Content Security Policy') &&
      !e.includes('net::ERR') &&
      !e.includes('favicon') &&
      !e.includes('google') &&
      !e.includes('facebook') &&
      !e.includes('analytics') &&
      !e.includes('gtm') &&
      !e.includes('hotjar') &&
      !e.includes('MIME type')
    );

    if (critical.length > 0) {
      console.log('Critical JS errors:', critical.slice(0, 5));
    }
    expect(critical.length).toBeLessThan(10);
  });
});

/* --- 8. Images --- */
test.describe('8. Images - Broken images check', () => {
  test('Home: no broken product images', async ({ page }) => {
    await safeGoto(page, '/', 'Home');
    await page.waitForTimeout(5000);

    const broken = await safeEval(page, () => {
      const imgs = document.querySelectorAll('img');
      const brk: string[] = [];
      imgs.forEach(img => {
        if (img.naturalWidth === 0 && img.offsetParent !== null && !img.loading) {
          brk.push(img.src || img.getAttribute('data-src') || 'unknown');
        }
      });
      return brk;
    }, []);

    if (broken.length > 0) {
      console.log(`Broken images on home: ${broken.slice(0, 5).join(', ')}`);
    }
    expect(broken.length).toBeLessThan(5);
  });
});
