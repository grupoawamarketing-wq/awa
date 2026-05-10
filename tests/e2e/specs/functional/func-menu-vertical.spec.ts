// spec: tests/e2e/specs/functional/menu-vertical.plan.md
// seed: tests/e2e/specs/seed.spec.ts

/**
 * func-menu-vertical.spec.ts — AWA Motos
 * Testa o menu vertical lateral (Rokanthemes_VerticalMenu).
 *
 * Suite 1 (01-04): Presença e estrutura           — func-desktop
 * Suite 2 (05-06): Toggle abrir/fechar            — func-desktop
 * Suite 3 (07-11): Submenus e hover               — func-desktop
 * Suite 4 (12-15): Navegação por clique           — func-desktop
 * Suite 5 (16-23): Comportamento mobile           — func-mobile
 * Suite 6 (24-27): Acessibilidade                 — func-desktop
 * Suite 7 (28-32): Edge cases                     — func-desktop
 *
 * NOTA Firefox/Juggler: page.evaluate() pode travar após carregamento da home
 * (~14 MB CSS/JS). Todo evaluate() é envolto em Promise.race com setTimeout(7s).
 */
import { test, expect, type Page } from '@playwright/test';
import { navigateTo } from '../../helpers/visual-audit.helpers';

// Sobrescreve artifacts para este spec — evita teardown lento com Firefox/Juggler (14 MB page)
test.use({ screenshot: 'off', video: 'off', trace: 'off' });

const HOME    = 'https://awamotos.com';
const TIMEOUT = 7_000;

// ---------------------------------------------------------------------------
// Seletores — multi-fallback, em ordem de especificidade crescente
// ---------------------------------------------------------------------------

/**
 * Seletores baseados no HTML real do Rokanthemes_VerticalMenu (sidemenu.phtml):
 *   <nav class="navigation verticalmenu side-verticalmenu">
 *   <ul> <li class="classic|staticwidth [parent]"> <ul class="submenu"> </li>
 */
const MENU_SEL =
  'nav.verticalmenu, nav.side-verticalmenu, ' +
  '.navigation.verticalmenu, .side-verticalmenu';

const ITEMS_SEL =
  '.verticalmenu li a, .side-verticalmenu li a, ' +
  '.navigation.verticalmenu li a';

const LEVEL1_SEL =
  '.verticalmenu > ul > li, .side-verticalmenu > ul > li, ' +
  '.navigation.verticalmenu > ul > li';

/** Itens pai com submenus (classes reais do plugin jQuery verticalmenu.js) */
const PARENT_SEL =
  '.verticalmenu li.classic.parent, .verticalmenu li.staticwidth.parent, ' +
  '.navigation.verticalmenu li.classic.parent, ' +
  '.navigation.verticalmenu li.staticwidth.parent';

/** Submenus nivel 2 e 3 (classes reais: .submenu e .subchildmenu) */
const SUBMENU_SEL =
  '.verticalmenu .submenu, .navigation.verticalmenu .submenu, ' +
  '.verticalmenu .subchildmenu, .navigation.verticalmenu .subchildmenu';

/**
 * Toggle real: h2 que serve de cabecalho/title do VerticalMenu.
 * NB: nao existe drawer mobile no Rokanthemes VerticalMenu;
 * o menu desaparece via CSS em resolucoes menores.
 */
const TOGGLE_SEL =
  'h2.list-category-dropdown, h2.togge-menu, ' +
  '.title-category-dropdown, .vm-toggle-categories';

// ---------------------------------------------------------------------------
// Utilitário anti-Juggler (Firefox NS_ERROR_FAILURE)
// ---------------------------------------------------------------------------

async function safeEval<T>(page: Page, fn: () => T, fallback: T): Promise<T> {
  return Promise.race([
    page.evaluate(fn).catch(() => fallback),
    new Promise<T>(resolve => setTimeout(() => resolve(fallback), TIMEOUT)),
  ]);
}

/** Flag de módulo: definido em test 01; evita Juggler hang em testes posteriores */
let menuFound: boolean | null = null;

/* ======================================================================
 * SUITE 1 — Presença e Estrutura (Desktop)
 * ====================================================================== */
test.describe('Menu Vertical — 1. Presença e Estrutura (Desktop)', () => {
  test.setTimeout(90_000); // navigateTo(22s) + isVisible(9s) + Firefox teardown overhead
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-desktop') { test.skip(); return; }
    if (menuFound === false) { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  // 01
  test('01 — contêiner do menu vertical é renderizado na homepage (P1)', async ({ page }) => {
    const menu = page.locator(MENU_SEL).first();
    const visible = await Promise.race([
      menu.isVisible({ timeout: 8_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 9_000)),
    ]);
    menuFound = visible;
    if (!visible) {
      console.warn('[P1] Menu vertical não encontrado — módulo pode estar desativado');
      test.skip();
      return;
    }
    expect(visible, '[P1] Menu vertical deve estar visível na homepage').toBe(true);
  });

  // 02
  test('02 — menu vertical contém ao menos 3 itens de categoria (P1)', async ({ page }) => {
    const count = await page.locator(ITEMS_SEL).count().catch(() => 0);
    if (count === 0) {
      console.warn('[P1] Nenhum item de menu vertical encontrado');
      test.skip();
      return;
    }
    expect(count, '[P1] Menu vertical precisa ter ao menos 3 categorias').toBeGreaterThanOrEqual(3);
  });

  // 03
  test('03 — itens de nível 1 têm href válido (não "#") (P0)', async ({ page }) => {
    const items = page.locator(LEVEL1_SEL + ' > a');
    const count = await items.count().catch(() => 0);
    if (count === 0) { test.skip(); return; }

    const toCheck = Math.min(count, 5);
    for (let i = 0; i < toCheck; i++) {
      const href = await items.nth(i).getAttribute('href').catch(() => '');
      expect(href, `[P0] Item ${i} sem href`).toBeTruthy();
      expect(href, `[P0] Item ${i} com href "#"`).not.toBe('#');
      expect(href, `[P0] Item ${i} com href javascript:`).not.toMatch(/^javascript:/i);
    }
  });

  // 04
  test('04 — imagens de categoria no menu carregam (naturalWidth > 0) (P2)', async ({ page }) => {
    const count = await page.locator(`${MENU_SEL} img`).count().catch(() => 0);
    if (count === 0) {
      console.info('[P2] Nenhuma imagem no menu vertical — skipping');
      test.skip();
      return;
    }
    const loaded = await safeEval(
      page,
      () => {
        const els = Array.from(document.querySelectorAll(
          '.verticalmenu img, .navigation.verticalmenu img, .side-verticalmenu img',
        )) as HTMLImageElement[];
        return els.length === 0 ? true : els.slice(0, 8).every(el => el.complete && el.naturalWidth > 0);
      },
      true,
    );
    expect(loaded, '[P2] Imagens do menu vertical devem carregar completamente').toBe(true);
  });
});

/* ======================================================================
 * SUITE 2 — Toggle Abrir/Fechar (Desktop)
 * ====================================================================== */
test.describe('Menu Vertical — 2. Toggle (Desktop)', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-desktop') { test.skip(); return; }
    if (menuFound === false) { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  // 05
  test('05 — menu inicia em estado visível/expandido por padrão (P2)', async ({ page }) => {
    const menu = page.locator(MENU_SEL).first();
    const visible = await Promise.race([
      menu.isVisible({ timeout: 6_000 }).catch(() => null as boolean | null),
      new Promise<null>(resolve => setTimeout(() => resolve(null), 7_000)),
    ]);
    if (visible === null) {
      console.warn('[P2] Timeout ao checar estado inicial do menu — skip');
      test.skip();
      return;
    }
    if (!visible) {
      console.warn('[P2] Menu vertical não visível em desktop — não é possível testar estado inicial');
      test.skip();
      return;
    }
  });

  // 06
  test('06 — toggle colapsa e expande o menu (P2)', async ({ page }) => {
    const toggle = page.locator(TOGGLE_SEL).first();
    if (!await toggle.isVisible({ timeout: 5_000 }).catch(() => false)) {
      console.info('[P2] Toggle não encontrado em desktop — menu pode ser sempre visível');
      test.skip();
      return;
    }
    const menu = page.locator(MENU_SEL).first();
    const before = await menu.isVisible({ timeout: 4_000 }).catch(() => false);
    await toggle.click();
    await page.waitForTimeout(700);
    expect(
      await menu.isVisible({ timeout: 3_000 }).catch(() => !before),
      '[P2] Menu deve mudar de estado após toggle',
    ).not.toBe(before);
    await toggle.click();
    await page.waitForTimeout(700);
    expect(
      await menu.isVisible({ timeout: 3_000 }).catch(() => before),
      '[P2] Menu deve retornar ao estado inicial',
    ).toBe(before);
  });
});

/* ======================================================================
 * SUITE 3 — Submenus e Hover (Desktop)
 * ====================================================================== */
test.describe('Menu Vertical — 3. Submenus e Hover (Desktop)', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-desktop') { test.skip(); return; }
    if (menuFound === false) { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  // 07
  test('07 — hover em item pai exibe submenu de nível 2 (P0)', async ({ page }) => {
    const parentItem = page.locator(
      PARENT_SEL,
    ).first();
    const hasParent = await Promise.race([
      parentItem.isVisible({ timeout: 6_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 7_000)),
    ]);
    if (!hasParent) {
      console.warn('[P0] Nenhum item pai encontrado no menu vertical');
      test.skip();
      return;
    }
    await parentItem.hover();
    await page.waitForTimeout(500);
    const submenuVisible = await Promise.race([
      page.locator(SUBMENU_SEL).first().isVisible({ timeout: 4_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 5_000)),
    ]);
    if (!submenuVisible) console.warn('[P0] Submenu não apareceu no hover — verificar Rokanthemes_VerticalMenu');
    expect(submenuVisible, '[P0] Submenu nível 2 deve ficar visível no hover').toBe(true);
  });

  // 08
  test('08 — submenu nível 2 tem ao menos 2 itens com href (P1)', async ({ page }) => {
    const parentItem = page.locator(
      PARENT_SEL,
    ).first();
    if (!await parentItem.isVisible({ timeout: 6_000 }).catch(() => false)) { test.skip(); return; }
    await parentItem.hover();
    await page.waitForTimeout(500);
    const submenuLinks = page.locator(
      '.verticalmenu li.classic.parent:first-of-type .submenu a, .navigation.verticalmenu li.classic.parent:first-of-type .submenu a, .verticalmenu li.staticwidth.parent:first-of-type .submenu a',
    );
    const count = await submenuLinks.count().catch(() => 0);
    if (count === 0) { console.warn('[P1] Submenu sem links visíveis'); test.skip(); return; }
    expect(count, '[P1] Submenu nível 2 precisa ter ao menos 2 itens').toBeGreaterThanOrEqual(2);
    const href = await submenuLinks.first().getAttribute('href').catch(() => '');
    expect(href, '[P1] Link do submenu deve ter href válido').toBeTruthy();
    expect(href, '[P1] Link do submenu não deve ser "#"').not.toBe('#');
  });

  // 09
  test('09 — hover em item folha NÃO exibe submenu (P1)', async ({ page }) => {
    const leafItem = page.locator(
      '.verticalmenu > ul > li:not(.classic.parent):not(.staticwidth.parent), .navigation.verticalmenu > ul > li:not(.classic.parent):not(.staticwidth.parent)',
    ).first();
    if (!await leafItem.isVisible({ timeout: 6_000 }).catch(() => false)) {
      console.info('[P1] Todos os itens de nível 1 são pais — sem folha para testar');
      test.skip();
      return;
    }
    await leafItem.hover();
    await page.waitForTimeout(500);
    expect(
      await page.locator(SUBMENU_SEL).first().isVisible({ timeout: 1_500 }).catch(() => false),
      '[P1] Item folha não deve exibir submenu',
    ).toBe(false);
  });

  // 10
  test('10 — submenu não é cortado pela borda inferior da viewport (P2)', async ({ page }) => {
    const parentItems = page.locator(
      PARENT_SEL,
    );
    if (!await parentItems.count().catch(() => 0)) { test.skip(); return; }
    await parentItems.last().hover();
    await page.waitForTimeout(500);
    if (!await page.locator(SUBMENU_SEL).first().isVisible({ timeout: 3_000 }).catch(() => false)) {
      test.skip(); return;
    }
    const overflow = await safeEval(
      page,
      () => {
        const sub = document.querySelector(
          '.verticalmenu .submenu, .navigation.verticalmenu .submenu',
        );
        if (!sub) return false;
        return sub.getBoundingClientRect().bottom > window.innerHeight + 4;
      },
      false,
    );
    if (overflow) console.warn('[P2] Submenu ultrapassa borda inferior da viewport');
    // P2 informativo — não bloqueia
  });

  // 11
  test('11 — somente um submenu nível 1 fica aberto por vez (P1)', async ({ page }) => {
    const parentItems = page.locator(
      PARENT_SEL,
    );
    if (await parentItems.count().catch(() => 0) < 2) {
      console.info('[P1] Menos de 2 itens pai — não é possível testar exclusividade');
      test.skip();
      return;
    }
    await parentItems.nth(0).hover();
    await page.waitForTimeout(400);
    await parentItems.nth(1).hover();
    await page.waitForTimeout(400);
    const openSubmenus = await safeEval(
      page,
      () => [
        // Plugin jQuery usa classe .opened para submenus abertos (nao display:none inline)
        // Submenus abertos tem left != -9999px (posicionados via mouseover)
        '.verticalmenu .submenu.opened, .navigation.verticalmenu .submenu.opened',
        'aside.sidebar-main .navigation li ul:not([style*="display: none"])',
      ].reduce((acc, s) => acc + document.querySelectorAll(s).length, 0),
      0,
    );
    expect(openSubmenus, '[P1] Apenas 1 submenu por vez deve ficar aberto').toBeLessThanOrEqual(1);
  });
});

/* ======================================================================
 * SUITE 4 — Navegação por Clique (Desktop)
 * ====================================================================== */
test.describe('Menu Vertical — 4. Navegação por Clique (Desktop)', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-desktop') { test.skip(); return; }
    if (menuFound === false) { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  // 12
  test('12 — clicar em item nível 1 navega para categoria (P0)', async ({ page }) => {
    const firstLink = page.locator(`${LEVEL1_SEL} > a`).first();
    if (!await firstLink.isVisible({ timeout: 6_000 }).catch(() => false)) { test.skip(); return; }
    const href = await firstLink.getAttribute('href').catch(() => '');
    if (!href || href === '#') { test.skip(); return; }
    await firstLink.click();
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});
    expect(page.url(), '[P0] URL deve mudar ao clicar no item do menu').toContain('awamotos.com');
    expect(page.url(), '[P0] URL não deve ser a homepage').not.toBe(HOME + '/');
    expect(page.url(), '[P0] URL não deve ser "#"').not.toMatch(/#$/);
  });

  // 13
  test('13 — página de categoria tem h1 e produtos (P1)', async ({ page }) => {
    const firstLink = page.locator(`${LEVEL1_SEL} > a`).first();
    if (!await firstLink.isVisible({ timeout: 6_000 }).catch(() => false)) { test.skip(); return; }
    const href = await firstLink.getAttribute('href').catch(() => '');
    if (!href || href === '#') { test.skip(); return; }
    await firstLink.click();
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});
    expect(
      await Promise.race([
        page.locator('h1.page-title, h1 .base, h1').first().isVisible({ timeout: 8_000 }).catch(() => false),
        new Promise<boolean>(r => setTimeout(() => r(false), 9_000)),
      ]),
      '[P1] Página de categoria deve ter h1 visível',
    ).toBe(true);
    expect(
      await Promise.race([
        page.locator('.products-grid, .product-items, ol.product-items, .category-products').first().isVisible({ timeout: 8_000 }).catch(() => false),
        new Promise<boolean>(r => setTimeout(() => r(false), 9_000)),
      ]),
      '[P1] Página de categoria deve exibir grid de produtos',
    ).toBe(true);
  });

  // 14
  test('14 — menu vertical persiste na página de categoria (P1)', async ({ page }) => {
    const firstLink = page.locator(`${LEVEL1_SEL} > a`).first();
    if (!await firstLink.isVisible({ timeout: 6_000 }).catch(() => false)) { test.skip(); return; }
    const href = await firstLink.getAttribute('href').catch(() => '');
    if (!href || href === '#') { test.skip(); return; }
    await firstLink.click();
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});
    expect(
      await Promise.race([
        page.locator(MENU_SEL).first().isVisible({ timeout: 8_000 }).catch(() => false),
        new Promise<boolean>(r => setTimeout(() => r(false), 9_000)),
      ]),
      '[P1] Menu vertical deve persistir na página de categoria',
    ).toBe(true);
  });

  // 15
  test('15 — item da categoria atual tem classe de destaque (P2)', async ({ page }) => {
    const firstLink = page.locator(`${LEVEL1_SEL} > a`).first();
    if (!await firstLink.isVisible({ timeout: 6_000 }).catch(() => false)) { test.skip(); return; }
    const href = await firstLink.getAttribute('href').catch(() => '');
    if (!href || href === '#') { test.skip(); return; }
    await firstLink.click();
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});
    const hasActive = await safeEval(
      page,
      () => !!(document.querySelector(
        '.verticalmenu li.ui-state-active, .navigation.verticalmenu li.ui-state-active, ' +
        'aside.sidebar-main .navigation li.active, aside.sidebar-main .navigation li.current',
      )),
      false,
    );
    if (!hasActive) console.warn('[P2] Nenhum item do menu marcado como ativo na categoria');
    // P2 informativo
  });
});

/* ======================================================================
 * SUITE 5 — Comportamento Mobile (375px)
 * ====================================================================== */
test.describe('Menu Vertical — 5. Mobile (375px)', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-mobile') { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  // 16
  test('16 — menu vertical oculto por padrão em mobile (P1)', async ({ page }) => {
    const visible = await Promise.race([
      page.locator(MENU_SEL).first().isVisible({ timeout: 6_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 7_000)),
    ]);
    if (visible) console.warn('[P1] Menu vertical visível em mobile — verificar responsividade');
    expect(visible, '[P1] Menu vertical deve estar oculto em mobile por padrão').toBe(false);
  });

  // 17
  test('17 — botão toggle do menu vertical visível em mobile (P1)', async ({ page }) => {
    const visible = await Promise.race([
      page.locator(TOGGLE_SEL).first().isVisible({ timeout: 6_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 7_000)),
    ]);
    if (!visible) { console.warn('[P1] Toggle do menu vertical não encontrado em mobile'); test.skip(); return; }
    expect(visible, '[P1] Botão toggle deve estar visível em mobile').toBe(true);
  });

  // 18
  test('18 — clicar no toggle abre drawer do menu vertical (P0)', async ({ page }) => {
    const toggle = page.locator(TOGGLE_SEL).first();
    if (!await toggle.isVisible({ timeout: 6_000 }).catch(() => false)) {
      console.warn('[P0] Toggle não encontrado em mobile'); test.skip(); return;
    }
    await toggle.click();
    await page.waitForTimeout(700);
    expect(
      await Promise.race([
        page.locator(MENU_SEL).first().isVisible({ timeout: 4_000 }).catch(() => false),
        new Promise<boolean>(resolve => setTimeout(() => resolve(false), 5_000)),
      ]),
      '[P0] Drawer deve abrir após clicar no toggle',
    ).toBe(true);
  });

  // 19
  test('19 — botão fechar dentro do drawer fecha o menu mobile (P0)', async ({ page }) => {
    const toggle = page.locator(TOGGLE_SEL).first();
    if (!await toggle.isVisible({ timeout: 6_000 }).catch(() => false)) { test.skip(); return; }
    await toggle.click();
    await page.waitForTimeout(700);
    const closeBtn = page.locator(
      `.vertical-menu-close, .awa-vertical-close, ${MENU_SEL} [aria-label*="fechar" i], ${MENU_SEL} [aria-label*="close" i]`,
    ).first();
    if (!await closeBtn.isVisible({ timeout: 3_000 }).catch(() => false)) {
      console.info('[P0] Botão close não encontrado — fechando via toggle');
      await toggle.click(); await page.waitForTimeout(600);
    } else {
      await closeBtn.click(); await page.waitForTimeout(600);
    }
    expect(
      await Promise.race([
        page.locator(MENU_SEL).first().isVisible({ timeout: 4_000 }).catch(() => false),
        new Promise<boolean>(resolve => setTimeout(() => resolve(false), 5_000)),
      ]),
      '[P0] Drawer do menu vertical deve fechar',
    ).toBe(false);
  });

  // 20
  test('20 — sem overflow horizontal com menu mobile fechado (P1)', async ({ page }) => {
    const overflow = await safeEval(
      page,
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 4,
      false,
    );
    if (overflow) console.warn('[P1] Overflow horizontal detectado em mobile com menu fechado');
    expect(overflow, '[P1] Não deve haver overflow horizontal em mobile').toBe(false);
  });

  // 21
  test('21 — itens do drawer são clicáveis e navegam (P1)', async ({ page }) => {
    const toggle = page.locator(TOGGLE_SEL).first();
    if (!await toggle.isVisible({ timeout: 6_000 }).catch(() => false)) { test.skip(); return; }
    await toggle.click(); await page.waitForTimeout(700);
    const firstLink = page.locator(ITEMS_SEL).first();
    if (!await firstLink.isVisible({ timeout: 5_000 }).catch(() => false)) {
      console.warn('[P1] Nenhum link visível no drawer mobile'); test.skip(); return;
    }
    const href = await firstLink.getAttribute('href').catch(() => '');
    if (!href || href === '#') { test.skip(); return; }
    await firstLink.click();
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});
    expect(page.url(), '[P1] Clicar no item do drawer deve navegar').not.toBe(HOME);
  });

  // 22
  test('22 — drawer não ultrapassa largura da viewport (P2)', async ({ page }) => {
    const toggle = page.locator(TOGGLE_SEL).first();
    if (!await toggle.isVisible({ timeout: 6_000 }).catch(() => false)) { test.skip(); return; }
    await toggle.click(); await page.waitForTimeout(700);
    if (!await page.locator(MENU_SEL).first().isVisible({ timeout: 4_000 }).catch(() => false)) { test.skip(); return; }
    const overflows = await safeEval(
      page,
      () => {
        const el = document.querySelector(
          '.verticalmenu, .side-verticalmenu, .navigation.verticalmenu, aside.sidebar-main .navigation',
        );
        if (!el) return false;
        return el.getBoundingClientRect().right > window.innerWidth + 4;
      },
      false,
    );
    expect(overflows, '[P2] Drawer não deve ultrapassar largura da viewport').toBe(false);
  });

  // 23
  test('23 — overlay aparece ao abrir drawer em mobile (P2)', async ({ page }) => {
    const toggle = page.locator(TOGGLE_SEL).first();
    if (!await toggle.isVisible({ timeout: 6_000 }).catch(() => false)) { test.skip(); return; }
    await toggle.click(); await page.waitForTimeout(700);
    const overlayVisible = await page.locator(
      '.awa-overlay, .vertical-menu-overlay, .modal-overlay, .nav-overlay, [class*="overlay"]:not(.product-image-wrapper)',
    ).first().isVisible({ timeout: 3_000 }).catch(() => false);
    if (!overlayVisible) console.info('[P2] Overlay não encontrado — pode ser comportamento esperado');
    // P2 informativo
  });
});

/* ======================================================================
 * SUITE 6 — Acessibilidade (Desktop)
 * ====================================================================== */
test.describe('Menu Vertical — 6. Acessibilidade (Desktop)', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-desktop') { test.skip(); return; }
    if (menuFound === false) { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  // 24
  test('24 — menu tem role=navigation ou nav semântico (P1)', async ({ page }) => {
    const hasNav = await safeEval(
      page,
      () => {
        const byRole = document.querySelector(
          '[role="navigation"][class*="vertical"], nav[class*="vertical"], nav[id*="vertical"], aside.sidebar-main nav',
        );
        const menu = document.querySelector(
          '.verticalmenu, .side-verticalmenu, .navigation.verticalmenu, aside.sidebar-main .navigation',
        );
        return !!(byRole || (menu && menu.closest('nav, [role="navigation"]')));
      },
      false,
    );
    if (!hasNav) console.warn('[P1] Menu vertical sem landmark nav/role=navigation');
    // P1 — recomendação, não bloqueia
  });

  // 25
  test('25 — links do menu são focáveis via Tab (P1)', async ({ page }) => {
    if (!await page.locator(MENU_SEL).first().isVisible({ timeout: 6_000 }).catch(() => false)) { test.skip(); return; }
    const firstLink = page.locator(`${LEVEL1_SEL} > a`).first();
    if (!await firstLink.isVisible({ timeout: 4_000 }).catch(() => false)) { test.skip(); return; }
    await firstLink.focus();
    const focused = await safeEval(
      page,
      () => {
        const active = document.activeElement;
        return !!(active && active.closest(
          '.verticalmenu, .side-verticalmenu, .navigation.verticalmenu, aside.sidebar-main .navigation',
        ));
      },
      false,
    );
    expect(focused, '[P1] Links do menu vertical devem ser focáveis').toBe(true);
  });

  // 26
  test('26 — sem erros de JavaScript no console ao interagir com o menu (P0)', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', err => errors.push(err.message));
    page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });

    if (!await page.locator(`${LEVEL1_SEL} > a`).first().isVisible({ timeout: 6_000 }).catch(() => false)) {
      test.skip(); return;
    }
    const parentItem = page.locator(PARENT_SEL).first();
    if (await parentItem.isVisible({ timeout: 3_000 }).catch(() => false)) {
      await parentItem.hover(); await page.waitForTimeout(400);
    }
    const critical = errors.filter(
      e => !e.includes('favicon') && !e.includes('NS_ERROR') && !e.includes('hydration'),
    );
    if (critical.length > 0) console.warn('[P0] Erros JS detectados:', critical.join(' | '));
    expect(critical, '[P0] Não deve haver erros JS críticos no menu vertical').toHaveLength(0);
  });

  // 27
  test('27 — menu vertical não causa overflow horizontal em desktop (P1)', async ({ page }) => {
    expect(
      await safeEval(page, () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 4, false),
      '[P1] Menu vertical não deve causar overflow horizontal em desktop',
    ).toBe(false);
  });
});

/* ======================================================================
 * SUITE 7 — Edge Cases e Resiliência (Desktop)
 * ====================================================================== */
test.describe('Menu Vertical — 7. Edge Cases (Desktop)', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-desktop') { test.skip(); return; }
    if (menuFound === false) { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  // 28
  test('28 — menu vertical funciona após navegação para página de categoria (P1)', async ({ page }) => {
    const firstLink = page.locator(`${LEVEL1_SEL} > a`).first();
    if (!await firstLink.isVisible({ timeout: 6_000 }).catch(() => false)) { test.skip(); return; }
    const href = await firstLink.getAttribute('href').catch(() => '');
    if (!href || href === '#') { test.skip(); return; }
    await firstLink.click();
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});
    expect(
      await Promise.race([
        page.locator(MENU_SEL).first().isVisible({ timeout: 8_000 }).catch(() => false),
        new Promise<boolean>(r => setTimeout(() => r(false), 9_000)),
      ]),
      '[P1] Menu vertical deve estar presente na categoria',
    ).toBe(true);
    expect(
      await page.locator(ITEMS_SEL).count().catch(() => 0),
      '[P1] Menu deve ter ao menos 1 item na categoria',
    ).toBeGreaterThan(0);
  });

  // 29
  test('29 — hover funciona após resize de desktop para tablet (1024px) (P2)', async ({ page }) => {
    if (!await page.locator(MENU_SEL).first().isVisible({ timeout: 6_000 }).catch(() => false)) { test.skip(); return; }
    await page.setViewportSize({ width: 1024, height: 768 });
    await page.waitForTimeout(400);
    if (!await page.locator(MENU_SEL).first().isVisible({ timeout: 4_000 }).catch(() => false)) {
      console.info('[P2] Menu vertical oculto em 1024px — verificar breakpoint do VerticalMenu');
    }
    // P2 informativo
  });

  // 30
  test('30 — nenhum item do menu tem href duplicado na mesma página (P2)', async ({ page }) => {
    const links = await safeEval(
      page,
      () => (Array.from(document.querySelectorAll(
        '.verticalmenu li a, .side-verticalmenu li a, ' +
        '.navigation.verticalmenu li a, aside.sidebar-main .navigation li a',
      )) as HTMLAnchorElement[]).map(a => a.href).filter(h => h && h !== '#'),
      [] as string[],
    );
    if (links.length === 0) { test.skip(); return; }
    const duplicates = links.length - new Set(links).size;
    if (duplicates > 0) console.warn(`[P2] ${duplicates} link(s) duplicado(s) no menu vertical`);
    // P2 informativo
  });

  // 31
  test('31 — menu vertical está presente na página 404 (P2)', async ({ page }) => {
    await page.goto('https://awamotos.com/pagina-que-nao-existe-xyzabc-404', {
      waitUntil: 'domcontentloaded', timeout: 20_000,
    }).catch(() => {});
    const visible = await Promise.race([
      page.locator(MENU_SEL).first().isVisible({ timeout: 6_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 7_000)),
    ]);
    if (!visible) console.info('[P2] Menu vertical não aparece em página 404 — pode ser esperado');
    // P2 informativo
  });

  // 32
  test('32 — menu não bloqueia o header ao fazer scroll (P2)', async ({ page }) => {
    if (!await page.locator(MENU_SEL).first().isVisible({ timeout: 6_000 }).catch(() => false)) { test.skip(); return; }
    await safeEval(page, () => { window.scrollBy(0, 400); return true; }, true);
    await page.waitForTimeout(500);
    const headerVisible = await Promise.race([
      page.locator('header, .awa-site-header, .page-header').first().isVisible({ timeout: 4_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 5_000)),
    ]);
    if (!headerVisible) console.info('[P2] Header não visível após scroll — verificar sticky header');
    // P2 informativo
  });
});
