/**
 * Probe do checkout B2B: login, adiciona produto, abre checkout,
 * captura erros de console, requests com falha e screenshots.
 *
 * Uso: node checkout-probe.mjs
 */
import { chromium } from 'playwright';

const BASE = 'https://awamotos.com';
const EMAIL = 'b2btest@awamotos.com';
const PASS = 'AwaTest!2026e2e';
const PRODUCT = `${BASE}/ret-biz-100-cr-redondo-universal-2220.html`;
const OUT = '/tmp/awa-checkout-probe';

const consoleErrors = [];
const failedRequests = [];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({
    viewport: { width: 1366, height: 900 },
    locale: 'pt-BR',
    serviceWorkers: 'block'
});
const page = await ctx.newPage();

page.on('console', (msg) => {
    if (msg.type() === 'error') {
        consoleErrors.push(msg.text().slice(0, 400));
    }
});
page.on('requestfailed', (req) => {
    failedRequests.push(`${req.failure()?.errorText} ${req.url().slice(0, 160)}`);
});
page.on('response', (res) => {
    if (res.status() >= 400) {
        failedRequests.push(`HTTP ${res.status()} ${res.url().slice(0, 160)}`);
    }
});

try {
    // 1. Login (form B2B custom)
    await page.goto(`${BASE}/b2b/account/login/`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.locator('input[placeholder*="seu@email.com"]').first().fill(EMAIL);
    await page.locator('input[type="password"]').first().fill(PASS);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }),
        page.getByRole('button', { name: 'Entrar' }).first().click()
    ]);
    console.log('LOGIN OK ->', page.url());

    // Garante carrinho com itens (sessão pode estar vazia entre execuções)
    await page.goto(`${BASE}/ret-biz-100-cr-redondo-universal-2220.html`, {
        waitUntil: 'domcontentloaded',
        timeout: 60000
    });
    await page.evaluate(() => {
        const form = document.querySelector('#product_addtocart_form');
        if (form) {
            const qty = form.querySelector('input[name="qty"]');
            if (qty) { qty.value = '100'; }
            form.submit();
        }
    });
    await page.waitForTimeout(6000);

    // 2. Adiciona produto ao carrinho (qty alta p/ superar pedido mínimo)
    await page.goto(PRODUCT, { waitUntil: 'domcontentloaded', timeout: 60000 });
    const qtyInput = page.locator('#qty, input[name="qty"]').first();
    if (await qtyInput.count()) {
        await qtyInput.fill('200');
    }
    // Submete o form de add-to-cart programaticamente (botão visual é custom/B2B)
    const submitted = await page.evaluate(() => {
        const form = document.querySelector('#product_addtocart_form, form[action*="checkout/cart/add"]');
        if (!form) { return 'form ausente'; }
        const qty = form.querySelector('input[name="qty"]');
        if (qty) { qty.value = '200'; }
        form.submit();
        return 'submetido';
    });
    console.log('ADD TO CART:', submitted);
    await page.waitForTimeout(8000);

    await page.goto(`${BASE}/checkout/cart/`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(4000);
    const cartInfo = await page.evaluate(() => ({
        url: location.href,
        rows: document.querySelectorAll('.cart.item, .cart-item, tbody.cart.item').length,
        empty: !!document.querySelector('.cart-empty'),
        subtotal: document.querySelector('.cart-summary .grand.totals, .cart-summary')?.textContent.replace(/\s+/g, ' ').trim().slice(0, 200) || '-'
    }));
    console.log('CART:', JSON.stringify(cartInfo));

    // Garante subtotal acima do pedido mínimo (R$ 1.500): qty 100 via form do carrinho
    const qtyUpdate = await page.evaluate(() => {
        const input = document.querySelector('.cart.item input.qty, input[name*="[qty]"]');
        const form = document.querySelector('form.form-cart, #form-validate');
        if (!input || !form) { return 'inputs ausentes'; }
        input.value = '100';
        const updateBtn = form.querySelector('button[name="update_cart_action"], .action.update');
        if (updateBtn) { updateBtn.click(); } else { form.submit(); }
        return 'qty 100 enviado';
    });
    console.log('QTY UPDATE:', qtyUpdate);
    await page.waitForTimeout(8000);
    const cartInfo2 = await page.evaluate(() =>
        document.querySelector('.cart-summary')?.textContent.replace(/\s+/g, ' ').trim().slice(0, 160) || '-');
    console.log('CART v2:', cartInfo2);

    // 3. Checkout
    await page.goto(`${BASE}/checkout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(8000);

    // Aceita cookies se o banner estiver visível
    const cookieBtn = page.getByRole('button', { name: /Permitir cookies/i }).first();
    if (await cookieBtn.count() && await cookieBtn.isVisible().catch(() => false)) {
        await cookieBtn.click().catch(() => {});
    }

    // Aguarda máscaras de loading sumirem
    await page.waitForFunction(() => {
        const masks = [...document.querySelectorAll('.loading-mask, ._block-content-loading')];
        return masks.every((m) => m.offsetParent === null || getComputedStyle(m).display === 'none');
    }, { timeout: 45000 }).catch(() => console.log('AVISO: loading mask persistiu'));
    await page.waitForTimeout(5000);
    console.log('CHECKOUT URL ->', page.url());
    console.log('BODY CLASS ->', await page.evaluate(() => document.body.className));

    const probe = await page.evaluate(() => {
        const q = (s) => document.querySelector(s);
        const vis = (s) => {
            const el = q(s);
            if (!el) { return 'ausente'; }
            const r = el.getBoundingClientRect();
            const st = getComputedStyle(el);
            return (r.width > 0 && r.height > 0 && st.display !== 'none' && st.visibility !== 'hidden')
                ? `visivel ${Math.round(r.width)}x${Math.round(r.height)}`
                : 'oculto';
        };
        const banner = q('.awa-b2b-min-order-progress[data-awa-component="checkout-sidebar-min-order"]');
        return {
            steps: vis('#checkoutSteps'),
            shipping: vis('#shipping'),
            payment: vis('#payment'),
            sidebar: vis('.opc-block-summary'),
            bottomNav: vis('nav.fixed-bottom'),
            shippingMethodBtns: vis('#shipping-method-buttons-container'),
            minOrderBanner: banner
                ? (banner.hasAttribute('hidden') ? 'hidden attr' : 'visivel: ' + banner.textContent.replace(/\s+/g, ' ').trim().slice(0, 120))
                : 'ausente',
            itemsTitle: (q('.items-in-cart .title') || {}).textContent?.replace(/\s+/g, ' ').trim() || 'ausente',
            summaryFont: q('.opc-block-summary') ? getComputedStyle(q('.opc-block-summary')).fontFamily.slice(0, 60) : '-',
            brokenImgs: [...document.querySelectorAll('.opc-block-summary img')].filter((i) => !i.complete || i.naturalWidth === 0).length,
            imgSrcs: [...document.querySelectorAll('.opc-block-summary img')].map((i) => i.src.slice(0, 120))
        };
    });
    console.log('PROBE:', JSON.stringify(probe, null, 2));

    const btnInfo = await page.evaluate(() => {
        const el = document.querySelector('#shipping-method-buttons-container');
        if (!el) { return 'ausente'; }
        return {
            inline: el.getAttribute('style'),
            display: getComputedStyle(el).display,
            cls: el.className,
            parent: el.parentElement ? `${el.parentElement.tagName}#${el.parentElement.id}.${el.parentElement.className}`.slice(0, 80) : null,
            html: el.outerHTML.slice(0, 220)
        };
    });
    console.log('BTN:', JSON.stringify(btnInfo, null, 1));

    const layout = await page.evaluate(() => {
        const cs = (s, props) => {
            const el = document.querySelector(s);
            if (!el) { return null; }
            const st = getComputedStyle(el);
            const out = { rect: el.getBoundingClientRect().toJSON() };
            props.forEach((p) => { out[p] = st.getPropertyValue(p); });
            return out;
        };
        return {
            wrapper: cs('.opc-wrapper', ['display', 'float', 'width', 'order', 'grid-column']),
            sidebar: cs('.opc-sidebar', ['display', 'float', 'width', 'order', 'position']),
            container: cs('#checkout, .checkout-container', ['display', 'grid-template-columns', 'flex-direction']),
            titleFont: cs('.opc-block-summary .title strong', ['font-family']),
            markFont: cs('.opc-block-summary .table-totals .mark, .opc-block-summary .mark', ['font-family']),
            sheets: [...document.styleSheets].map((s) => {
                let n = -1;
                try { n = s.cssRules.length; } catch (e) { /* cross-origin */ }
                return `${(s.href || 'inline').split('/').slice(-1)[0].slice(0, 60)} rules=${n}`;
            })
        };
    });
    console.log('LAYOUT:', JSON.stringify(layout, null, 1));

    const gridKids = await page.evaluate(() => {
        const wrapper = document.querySelector('.opc-wrapper');
        if (!wrapper) { return 'opc-wrapper ausente'; }
        const grid = wrapper.parentElement;
        const st0 = getComputedStyle(grid);
        return {
            container: `${grid.tagName}#${grid.id || '-'}.${String(grid.className).slice(0, 60)} disp=${st0.display} cols=${st0.gridTemplateColumns}`,
            kids: [...grid.children].map((c) => {
                const st = getComputedStyle(c);
                const r = c.getBoundingClientRect();
                return `${c.tagName}#${c.id || '-'}.${String(c.className).slice(0, 40)} | ${Math.round(r.width)}x${Math.round(r.height)} @y${Math.round(r.top)} | col=${st.gridColumnStart}/${st.gridColumnEnd} row=${st.gridRowStart}/${st.gridRowEnd} disp=${st.display} pos=${st.position}`;
            })
        };
    });
    console.log('GRID KIDS:', JSON.stringify(gridKids, null, 1));

    await page.screenshot({ path: `${OUT}-desktop.png`, fullPage: true });
    await page.setViewportSize({ width: 375, height: 812 });
    await page.waitForTimeout(2500);
    await page.screenshot({ path: `${OUT}-mobile.png`, fullPage: true });
} catch (e) {
    console.error('FALHA:', e.message);
    await page.screenshot({ path: `${OUT}-error.png` }).catch(() => {});
}

console.log('\nCONSOLE ERRORS (' + consoleErrors.length + '):');
consoleErrors.slice(0, 15).forEach((e) => console.log(' -', e));
console.log('\nFAILED REQUESTS (' + failedRequests.length + '):');
[...new Set(failedRequests)].slice(0, 20).forEach((e) => console.log(' -', e));

await browser.close();
