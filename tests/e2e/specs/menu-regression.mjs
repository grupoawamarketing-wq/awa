/**
 * Menu regression — home / busca / categoria / mobile / B2B guest.
 * Run: cd tests/e2e && node specs/menu-regression.mjs
 */
import { chromium } from 'playwright';

const BASE = process.env.AWA_BASE_URL || 'https://awamotos.com';
const DESKTOP_PAGES = [
    { id: 'home', url: `${BASE}/`, expectB2b: false },
    { id: 'search', url: `${BASE}/catalogsearch/result/?q=oleo`, expectB2b: true },
    { id: 'category', url: `${BASE}/bauletos.html`, expectB2b: false },
];
const MOBILE_PAGES = [
    { id: 'home', url: `${BASE}/` },
    { id: 'category', url: `${BASE}/bauletos.html` },
];

const DESKTOP = { width: 1280, height: 800 };
const MOBILE = { width: 375, height: 812 };

function assert(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

function bodyHasMobileDrawerOpen(classes) {
    return classes.includes('nav-open')
        || classes.includes('awa-menu-drawer-open')
        || classes.includes('awa-nav-preflight');
}

async function navMetrics(page, waitB2b = false) {
    if (waitB2b) {
        await page.waitForFunction(
            () => document.body.classList.contains('b2b-guest-mode')
                || document.body.classList.contains('b2b-restricted-mode'),
            { timeout: 12000 }
        ).catch(() => {});
    }
    return page.evaluate(() => {
        const nav = document.querySelector('.header-control.awa-nav-bar');
        const trigger = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
        const toggle = document.querySelector('[data-awa-nav-toggle="true"]');
        const navCs = nav ? getComputedStyle(nav) : null;
        const b2b = [...document.body.classList].filter((c) => c.startsWith('b2b'));
        return {
            navDisplay: navCs ? navCs.display : null,
            navVisible: navCs ? navCs.visibility : null,
            navPe: navCs ? navCs.pointerEvents : null,
            hasDeptTrigger: !!trigger,
            hasMobileToggle: !!toggle,
            b2b: b2b.join(','),
        };
    });
}

async function testDesktopDept(page) {
    const trigger = page.locator('[data-role="awa-vertical-menu-trigger"]');
    if (!(await trigger.count())) {
        return { ok: false, reason: 'no-trigger' };
    }
    await trigger.click({ timeout: 10000 });
    await page.waitForTimeout(1500);
    return page.evaluate(() => {
        const panel = document.querySelector('[data-role="awa-vertical-menu-panel"]');
        const cs = panel ? getComputedStyle(panel) : null;
        const stateOpen =
            document.body.classList.contains('awa-menu-dept-open')
            || panel?.classList.contains('vmm-open')
            || panel?.classList.contains('menu-open')
            || panel?.getAttribute('data-awa-menu-state') === 'open'
            || panel?.getAttribute('aria-hidden') === 'false';
        return {
            ok: stateOpen && cs && cs.display !== 'none' && cs.visibility !== 'hidden',
            reason: stateOpen ? 'open' : 'closed',
            display: cs ? cs.display : null,
        };
    });
}

async function testMobileDrawer(page) {
    const toggle = page.locator('[data-awa-nav-toggle="true"]');
    if (!(await toggle.count()) || !(await toggle.isVisible())) {
        return { ok: false, escapeOk: false, reason: 'toggle-hidden' };
    }

    await toggle.click({ timeout: 10000 });
    await page.waitForTimeout(800);

    let classes = await page.evaluate(() => [...document.body.classList]);
    if (!bodyHasMobileDrawerOpen(classes)) {
        await toggle.click({ timeout: 10000 });
        await page.waitForTimeout(600);
        classes = await page.evaluate(() => [...document.body.classList]);
    }

    const openAfter = bodyHasMobileDrawerOpen(classes);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(400);

    const closed = await page.evaluate(() => {
        const cls = document.body.classList;
        return !cls.contains('nav-open') && !cls.contains('awa-menu-drawer-open');
    });

    return {
        ok: openAfter,
        escapeOk: closed,
        reason: openAfter ? 'drawer-open' : 'drawer-fail',
    };
}

let failed = 0;

const desktopBrowser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
for (const p of DESKTOP_PAGES) {
    const page = await desktopBrowser.newPage();
    await page.setViewportSize(DESKTOP);
    await page.goto(p.url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(p.id === 'search' ? 5500 : 4000);

    const metrics = await navMetrics(page, p.expectB2b);
    console.log(`[${p.id} desktop]`, JSON.stringify(metrics));

    try {
        assert(metrics.navDisplay !== 'none', `${p.id}: nav display:none`);
        assert(metrics.navVisible !== 'hidden', `${p.id}: nav visibility:hidden`);
        assert(metrics.navPe !== 'none', `${p.id}: nav pointer-events:none`);
        if (p.expectB2b) {
            assert(
                metrics.b2b.includes('b2b-guest') || metrics.b2b.includes('b2b-restricted'),
                `${p.id}: missing b2b guest classes`
            );
        }
        const dept = await testDesktopDept(page);
        assert(dept.ok, `${p.id}: Departamentos não abriu (${dept.reason}, display=${dept.display})`);
        console.log(`  OK desktop + dept`);
    } catch (e) {
        console.error(`  FAIL:`, e.message);
        failed += 1;
    }
    await page.close();
}
await desktopBrowser.close();

const mobileBrowser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
for (const p of MOBILE_PAGES) {
    const page = await mobileBrowser.newPage();
    await page.setViewportSize(MOBILE);
    try {
        await page.goto(p.url, { waitUntil: 'domcontentloaded', timeout: 60000 });
        await page.waitForTimeout(p.id === 'home' ? 5000 : 4000);
        const drawer = await testMobileDrawer(page);
        assert(drawer.ok, `${p.id} mobile: drawer não abriu (${drawer.reason})`);
        console.log(`[${p.id} mobile] OK drawer (escape=${drawer.escapeOk})`);
    } catch (e) {
        console.error(`[${p.id} mobile] FAIL:`, e.message);
        failed += 1;
    } finally {
        await page.close().catch(() => {});
    }
}
await mobileBrowser.close();

if (failed > 0) {
    console.error(`\n${failed} assertion(s) failed`);
    process.exit(1);
}
console.log('\nAll menu regression checks passed.');
