/**
 * Auditoria visual do checkout — multi-viewport, métricas de layout e regressões.
 * Uso: node checkout-visual-audit.mjs
 */
import { chromium, firefox, webkit } from 'playwright';
import { writeFileSync } from 'fs';

const BASE = 'https://awamotos.com';
const EMAIL = 'b2btest@awamotos.com';
const PASS = 'AwaTest!2026e2e';
const OUT = '/tmp/awa-checkout-audit.json';

const VIEWPORTS = [
    { name: 'mobile', width: 375, height: 812 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1366, height: 900 }
];

const BROWSERS = [
    { name: 'chromium', launcher: chromium },
    { name: 'firefox', launcher: firefox },
    { name: 'webkit', launcher: webkit }
];

const issues = [];

function report(severity, code, message, meta = {}) {
    issues.push({ severity, code, message, ...meta });
}

async function loginAndCheckout(page) {
    await page.goto(`${BASE}/b2b/account/login/`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.locator('input[placeholder*="seu@email.com"]').first().fill(EMAIL);
    await page.locator('input[type="password"]').first().fill(PASS);
    const loginBtn = page.getByRole('button', { name: 'Entrar' }).first();
    await loginBtn.click();
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 90000 }).catch(async () => {
        await page.goto(`${BASE}/b2b/account/dashboard/`, { waitUntil: 'domcontentloaded' });
    });

    await page.goto(`${BASE}/ret-biz-100-cr-redondo-universal-2220.html`, { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
        const form = document.querySelector('#product_addtocart_form');
        if (form) {
            const qty = form.querySelector('input[name="qty"]');
            if (qty) { qty.value = '100'; }
            form.submit();
        }
    });
    await page.waitForTimeout(6000);

    await page.goto(`${BASE}/expresscheckout.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(6000);
    await page.locator('.opc-block-summary').first().scrollIntoViewIfNeeded().catch(() => {});
    await page.waitForFunction(() => {
        const imgs = [...document.querySelectorAll('.opc-block-summary img')];
        return !imgs.length || imgs.every((i) => i.complete && i.naturalWidth > 0);
    }, { timeout: 20000 }).catch(() => {});

    const cookieBtn = page.getByRole('button', { name: /Permitir cookies/i }).first();
    if (await cookieBtn.count() && await cookieBtn.isVisible().catch(() => false)) {
        await cookieBtn.click().catch(() => {});
        await page.waitForTimeout(800);
    }

    await page.waitForFunction(() => {
        const masks = [...document.querySelectorAll('.loading-mask, ._block-content-loading')];
        return masks.every((m) => m.offsetParent === null || getComputedStyle(m).display === 'none');
    }, { timeout: 30000 }).catch(() => {});
}

async function auditPage(page, browserName, vp) {
    const tag = `${browserName}/${vp.name}`;
    const metrics = await page.evaluate(() => {
        const q = (s) => document.querySelector(s);
        const vis = (el) => {
            if (!el) { return false; }
            const r = el.getBoundingClientRect();
            const st = getComputedStyle(el);
            return r.width > 0 && r.height > 0 && st.display !== 'none' && st.visibility !== 'hidden';
        };
        const rect = (s) => {
            const el = q(s);
            return el ? el.getBoundingClientRect().toJSON() : null;
        };
        const cs = (s, p) => {
            const el = q(s);
            return el ? getComputedStyle(el).getPropertyValue(p) : null;
        };

        const grandMark = q('.opc-block-summary tr.grand.totals .mark, .opc-block-summary tr.grand.totals th');
        const tel = q('.shipping-address-item a[href^="tel:"]');
        const title = q('.opc-block-summary .title strong');
        const toolbar = q('.awa-place-order-toolbar');
        const cookie = q('#awa-cookie-banner, .awa-cookie-banner, .message.global.cookie');
        const shipInfo = q('#opc-sidebar > .opc-block-shipping-information, .opc-sidebar > .opc-block-shipping-information');
        const bottomNav = q('nav.fixed-bottom, .awa-bottom-nav, .fixed-bottom');
        const steps = q('#checkoutSteps');
        const productName = q('.opc-block-summary .product-item-name');

        const overlaps = [];
        const pairs = [
            ['.opc-block-summary tr.grand.totals .mark', '.opc-block-summary tr.grand.totals .amount'],
            ['.awa-place-order-toolbar', '.awa-opc-trust-strip']
        ];
        pairs.forEach(([aSel, bSel]) => {
            const a = q(aSel);
            const b = q(bSel);
            if (!a || !b) { return; }
            const ar = a.getBoundingClientRect();
            const br = b.getBoundingClientRect();
            const overlapW = Math.min(ar.right, br.right) - Math.max(ar.left, br.left);
            const overlapH = Math.min(ar.bottom, br.bottom) - Math.max(ar.top, br.top);
            if (overlapW > 2 && overlapH > 2 && vis(a) && vis(b)) {
                overlaps.push(`${aSel} x ${bSel}`);
            }
        });

        return {
            url: location.href,
            bodyClass: document.body.className,
            stepsVisible: vis(steps),
            stepsSize: rect('#checkoutSteps'),
            sidebarShipInfo: shipInfo ? getComputedStyle(shipInfo).display : 'missing',
            bottomNavVisible: vis(bottomNav),
            telColor: tel ? getComputedStyle(tel).color : null,
            titleFont: title ? getComputedStyle(title).fontFamily.split(',')[0].trim() : null,
            grandMarkWidth: grandMark ? grandMark.getBoundingClientRect().width : 0,
            toolbarPosition: toolbar ? getComputedStyle(toolbar).position : null,
            cookieVisible: vis(cookie),
            cookieZ: cookie ? getComputedStyle(cookie).zIndex : null,
            brokenImgs: [...document.querySelectorAll('.opc-block-summary img')].filter((i) => !i.complete || i.naturalWidth === 0).length,
            productLineClamp: productName ? getComputedStyle(productName).webkitLineClamp : null,
            productDisplay: productName ? getComputedStyle(productName).display : null,
            opcEstimatedVisible: vis(q('.opc-estimated-wrapper')),
            overlaps,
            minOrderHidden: (() => {
                const b = q('.awa-b2b-min-order-progress--checkout');
                return b ? b.hasAttribute('hidden') || getComputedStyle(b).display === 'none' : 'absent';
            })()
        };
    });

    if (!metrics.stepsVisible) {
        report('critical', 'STEPS_HIDDEN', `${tag}: coluna principal vazia`, { tag, metrics });
    }
    if (metrics.bottomNavVisible) {
        report('high', 'BOTTOM_NAV', `${tag}: bottom nav visível no checkout`, { tag });
    }
    if (metrics.sidebarShipInfo !== 'none') {
        report('medium', 'EMPTY_SHIP_INFO', `${tag}: opc-block-shipping-information visível`, { tag, display: metrics.sidebarShipInfo });
    }
    if (metrics.grandMarkWidth > 0 && metrics.grandMarkWidth < 40) {
        report('critical', 'TOTALS_COLLAPSED', `${tag}: rótulo do total colapsado (${Math.round(metrics.grandMarkWidth)}px)`, { tag, width: metrics.grandMarkWidth });
    }
    if (metrics.telColor && metrics.telColor.includes('0.155')) {
        report('medium', 'TEL_RED', `${tag}: telefone ainda vermelho`, { tag, color: metrics.telColor });
    }
    if (metrics.titleFont && !/rubik/i.test(metrics.titleFont)) {
        report('medium', 'FONT_TITLE', `${tag}: título não usa Rubik (${metrics.titleFont})`, { tag });
    }
    if (vp.name === 'mobile' && metrics.toolbarPosition !== 'fixed') {
        report('high', 'CTA_NOT_FIXED', `${tag}: CTA mobile não está fixed`, { tag, pos: metrics.toolbarPosition });
    }
    if (metrics.brokenImgs > 0) {
        const imgW = await page.evaluate(() => {
            const img = document.querySelector('.opc-block-summary img');
            return img ? img.getBoundingClientRect().width : 0;
        });
        if (imgW < 20) {
            report('high', 'BROKEN_IMG', `${tag}: ${metrics.brokenImgs} imagem(ns) quebrada(s)`, { tag });
        }
    }
    if (metrics.overlaps.length) {
        report('high', 'OVERLAP', `${tag}: sobreposição ${metrics.overlaps.join(', ')}`, { tag });
    }
    if (metrics.cookieVisible && vp.name === 'mobile') {
        report('low', 'COOKIE_VISIBLE', `${tag}: banner de cookies ainda visível após aceite`, { tag });
    }

    await page.screenshot({ path: `/tmp/awa-audit-${browserName}-${vp.name}.png`, fullPage: true });
    return metrics;
}

for (const { name: browserName, launcher } of BROWSERS) {
    let browser;
    try {
        browser = await launcher.launch({ headless: true });
    } catch (e) {
        report('low', 'BROWSER_SKIP', `Não foi possível iniciar ${browserName}: ${e.message}`, { browser: browserName });
        continue;
    }

    for (const vp of VIEWPORTS) {
        const ctx = await browser.newContext({
            viewport: { width: vp.width, height: vp.height },
            locale: 'pt-BR',
            serviceWorkers: 'block'
        });
        const page = await ctx.newPage();
        const consoleErrors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') { consoleErrors.push(msg.text().slice(0, 200)); }
        });

        try {
            await loginAndCheckout(page);
            const metrics = await auditPage(page, browserName, vp);
            if (consoleErrors.length) {
                const sri = consoleErrors.filter((e) => /integrity|digest|SRI/i.test(e));
                if (sri.length) {
                    report('critical', 'SRI_ERROR', `${browserName}/${vp.name}: erro SRI`, { errors: sri.slice(0, 3) });
                }
            }
            console.log(`OK ${browserName}/${vp.name}`, JSON.stringify(metrics, null, 0).slice(0, 280));
        } catch (e) {
            report('critical', 'AUDIT_FAIL', `${browserName}/${vp.name}: ${e.message}`, { browser: browserName, vp: vp.name });
        }
        await ctx.close();
    }
    await browser.close();
}

const summary = {
    timestamp: new Date().toISOString(),
    total: issues.length,
    critical: issues.filter((i) => i.severity === 'critical').length,
    high: issues.filter((i) => i.severity === 'high').length,
    medium: issues.filter((i) => i.severity === 'medium').length,
    low: issues.filter((i) => i.severity === 'low').length,
    issues
};

writeFileSync(OUT, JSON.stringify(summary, null, 2));
console.log('\n=== AUDIT SUMMARY ===');
console.log(`Total: ${summary.total} | critical: ${summary.critical} | high: ${summary.high} | medium: ${summary.medium}`);
issues.forEach((i) => console.log(`[${i.severity}] ${i.code}: ${i.message}`));
process.exit(summary.critical > 0 ? 1 : 0);
