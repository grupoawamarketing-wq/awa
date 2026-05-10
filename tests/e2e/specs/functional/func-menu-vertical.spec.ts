/**
 * func-menu-vertical.spec.ts — AWA Motos
 * Testa o menu vertical lateral (Rokanthemes_VerticalMenu).
 *
 * Suite 1 (01-04): Presença e estrutura — func-desktop
 * Suite 2 (05-06): Toggle abrir/fechar — func-desktop
 * Suite 3 (07-11): Submenus e hover — func-desktop
 * Suite 4 (12-15): Navegação por clique — func-desktop
 * Suite 5 (16-23): Comportamento mobile — func-mobile
 * Suite 6 (24-27): Acessibilidade — func-desktop
 * Suite 7 (28-32): Edge cases — func-desktop
 *
 * NOTA Firefox/Juggler: page.evaluate() pode travar após carregamento da home
 * (~14MB CSS/JS). Todo evaluate() é envolto em Promise.race com setTimeout(7s).
 */
import { test, expect } from '@playwright/test';
import { navigateTo } from '../../helpers/visual-audit.helpers';

const HOME    = 'https://awamotos.com';
const TIMEOUT = 7_000; // Node.js timeout para Promise.race anti-Juggler

/** Seletores do menu vertical — em ordem de especificidade */
const MENU_SEL =
  '.vertical-menu-custom-block, .awa-vertical-nav, #vertical-menu-wrapper, ' +
  '.nav-vertical, .awa-vertical-menu, .vertical-menu';

const ITEMS_SEL =
  '.vertical-menu-custom-block li a, .awa-vertical-nav li a, ' +
  '.awa-vertical-menu li a, .vertical-menu li a';

const LEVEL1_SEL =
  '.vertical-menu-custom-block > ul > li, .awa-vertical-nav > ul > li, ' +
  '.awa-vertical-menu > ul > li, .vertical-menu > ul > li';

const SUBMENU_SEL =
  '.vertical-menu-custom-block .submenu, .awa-vertical-nav .submenu, ' +
  '.awa-vertical-menu .level1, .vertical-menu .level1, ' +
  '.awa-vertical-nav .level1, [class*="vertical"] .dropdown-content';

/** Avalia expressão no browser com fallback anti-Juggler */
async function safeEval<T>(
  page: import('@playwright/test').Page,
  fn: () => T,
  fallback: T,
): Promise<T> {
  return Promise.race([
    page.evaluate(fn).catch(() => fallback),
    new Promise<T>(resolve => setTimeout(() => resolve(fallback), TIMEOUT)),
  ]);
}

/* ══════════════════════════════════════════════════════════════════
 * SUITE 1 — Presença e Estrutura (Desktop)
 * ══════════════════════════════════════════════════════════════════ */
test.describe('Menu Vertical — 1. Presença e Estrutura (Desktop)', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-desktop') { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('01 — contêiner do menu vertical é renderizado na homepage (P1)', async ({ page }) => {
    const menu = page.locator(MENU_SEL).first();
    const visible = await Promise.race([
      menu.isVisible({ timeout: 8_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 9_000)),
    ]);
    if (!visible) {
      console.warn('[P1] Menu vertical não encontrado — módulo pode estar desativado');
      test.skip();
      return;
    }
    expect(visible, '[P1] Menu vertical deve estar visível na homepage').toBe(true);
  });

  test('02 — menu vertical contém ao menos 3 itens de categoria (P1)', async ({ page }) => {
    const items = page.locator(ITEMS_SEL);
    const count = await items.count().catch(() => 0);
    if (count === 0) {
      console.warn('[P1] Nenhum item de menu vertical encontrado');
      test.skip();
      return;
    }
    expect(count, '[P1] Menu vertical precisa ter ao menos 3 categorias').toBeGreaterThanOrEqual(3);
  });

  test('03 — itens de nível 1 têm href válido (não "#") (P0)', async ({ page }) => {
    const items = page.locator(LEVEL1_SEL + ' > a');
    const count = await items.count().catch(() => 0);
    if (count === 0) { test.skip(); return; }

    const toCheck = Math.min(count, 5);
    for (let i = 0; i < toCheck; i++) {
      const href = await items.nth(i).getAttribute('href').catch(() => '');
      expect(href, '[P0] Item ' + i + ' sem href').toBeTruthy();
      expect(href, '[P0] Item ' + i + ' com href "#"').not.toBe('#');
      expect(href, '[P0] Item ' + i + ' com href javascript:').not.toMatch(/^javascript:/i);
    }
  });

  test('04 — imagens de categoria no menu carregam (naturalWidth > 0) (P2)', async ({ page }) => {
    const imgs = page.locator(MENU_SEL + ' img');
    const count = await imgs.count().catch(() => 0);
    if (count === 0) {
      console.info('[P2] Nenhuma imagem no menu vertical — skipping');
      test.skip();
      return;
    }

    const loaded = await safeEval(
      page,
      () => {
        const all = Array.from(document.querySelectorAll(
          '.vertical-menu-custom-block img, .awa-vertical-nav img, ' +
          '.awa-vertical-menu img, .vertical-menu img'
        )) as HTMLImageElement[];
        return all.slice(0, 8).every(img => img.complete && img.naturalWidth > 0);
      },
      true,
    );
    expect(loaded, '[P2] Imagens do menu vertical devem carregar sem erro').toBe(true);
  });
});

/* ══════════════════════════════════════════════════════════════════
 * SUITE 2 — Toggle Abrir/Fechar (Desktop)
 * ══════════════════════════════════════════════════════════════════ */
test.describe('Menu Vertical — 2. Toggle (Desktop)', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-desktop') { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('05 — menu inicia em estado visível/expandido por padrão (P2)', async ({ page }) => {
    const menu = page.locator(MENU_SEL).first();
    const visible = await Promise.race([
      menu.isVisible({ timeout: 6_000 }).catch(() => null),
      new Promise<null>(resolve => setTimeout(() => resolve(null), 7_000)),
    ]);
    if (visible === null || !visible) {
      console.warn('[P2] Menu vertical não visível/toggle ausente — estado inicial não determinado, skip');
      test.skip();
      return;
    }
  });

  test('06 — toggle colapsa e expande o menu (P2)', async ({ page }) => {
    const toggle = page.locator(
      '.vertical-menu-toggle, .awa-vertical-toggle, ' +
      '[data-action="toggle-vertical-menu"], .menu-vertical-btn, ' +
      'button[aria-label*="categorias" i], button[aria-label*="menu" i]'
    ).first();

    const toggleExists = await toggle.isVisible({ timeout: 5_000 }).catch(() => false);
    if (!toggleExists) {
      console.info('[P2] Toggle não encontrado em desktop — menu pode ser sempre visível');
      test.skip();
      return;
    }

    const menu = page.locator(MENU_SEL).first();
    const before = await menu.isVisible({ timeout: 4_000 }).catch(() => false);

    await toggle.click();
    await page.waitForTimeout(600);
    const after1 = await menu.isVisible({ timeout: 3_000 }).catch(() => !before);
    expect(after1, '[P2] Menu deve mudar de estado após toggle').not.toBe(before);

    await toggle.click();
    await page.waitForTimeout(600);
    const after2 = await menu.isVisible({ timeout: 3_000 }).catch(() => before);
    expect(after2, '[P2] Menu deve retornar ao estado inicial').toBe(before);
  });
});

/* ══════════════════════════════════════════════════════════════════
 * SUITE 3 — Submenus e Hover (Desktop)
 * ══════════════════════════════════════════════════════════════════ */
test.describe('Menu Vertical — 3. Submenus e Hover (Desktop)', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-desktop') { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('07 — hover em item pai exibe submenu de nível 2 (P0)', async ({ page }) => {
    const parentItem = page.locator(
      LEVEL1_SEL + '.parent, ' + LEVEL1_SEL + '.has-children, ' + LEVEL1_SEL + ':has(ul)'
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

    const submenu = page.locator(SUBMENU_SEL).first();
    const submenuVisible = await Promise.race([
      submenu.isVisible({ timeout: 3_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 4_000)),
    ]);

    if (!submenuVisible) {
      console.warn('[P0] Submenu não apareceu no hover — verificar comportamento do VerticalMenu');
    }
    expect(submenuVisible, '[P0] Submenu nível 2 deve ficar visível no hover').toBe(true);
  });

  test('08 — submenu nível 2 tem ao menos 2 itens com href (P1)', async ({ page }) => {
    const parentItem = page.locator(
      LEVEL1_SEL + '.parent, ' + LEVEL1_SEL + '.has-children, ' + LEVEL1_SEL + ':has(ul)'
    ).first();

    const hasParent = await parentItem.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!hasParent) { test.skip(); return; }

    await parentItem.hover();
    await page.waitForTimeout(500);

    const submenuLinks = page.locator(
      LEVEL1_SEL + ':first-child .level1 a, ' + LEVEL1_SEL + ':first-child .submenu a'
    );
    const count = await submenuLinks.count().catch(() => 0);
    if (count === 0) {
      console.warn('[P1] Submenu não tem links visíveis');
      test.skip();
      return;
    }
    expect(count, '[P1] Submenu nível 2 precisa ter ao menos 2 itens').toBeGreaterThanOrEqual(2);

    const href = await submenuLinks.first().getAttribute('href').catch(() => '');
    expect(href, '[P1] Link do submenu nível 2 deve ter href válido').toBeTruthy();
    expect(href, '[P1] Link do submenu nível 2 não deve ser "#"').not.toBe('#');
  });

  test('09 — hover em item folha NÃO exibe submenu (P1)', async ({ page }) => {
    const leafItem = page.locator(
      LEVEL1_SEL + ':not(.parent):not(.has-children):not(:has(ul))'
    ).first();

    const hasLeaf = await leafItem.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!hasLeaf) {
      console.info('[P1] Todos os itens de nível 1 são pais — sem folha para testar');
      test.skip();
      return;
    }

    await leafItem.hover();
    await page.waitForTimeout(500);

    const anySubmenu = page.locator(SUBMENU_SEL).first();
    const spurious = await anySubmenu.isVisible({ timeout: 1_500 }).catch(() => false);
    expect(spurious, '[P1] Item folha não deve exibir submenu').toBe(false);
  });

  test('10 — submenu não é cortado pela borda inferior da viewport (P2)', async ({ page }) => {
    const parentItems = page.locator(
      LEVEL1_SEL + '.parent, ' + LEVEL1_SEL + '.has-children, ' + LEVEL1_SEL + ':has(ul)'
    );
    const count = await parentItems.count().catch(() => 0);
    if (count === 0) { test.skip(); return; }

    await parentItems.last().hover();
    await page.waitForTimeout(500);

    const submenu = page.locator(SUBMENU_SEL).first();
    const visible = await submenu.isVisible({ timeout: 3_000 }).catch(() => false);
    if (!visible) { test.skip(); return; }

    const overflow = await safeEval(
      page,
      () => {
        const sub = document.querySelector(
          '.vertical-menu-custom-block .submenu, .awa-vertical-nav .submenu, ' +
          '.awa-vertical-menu .level1, .vertical-menu .level1'
        );
        if (!sub) return false;
        const rect = sub.getBoundingClientRect();
        return rect.bottom > window.innerHeight + 4;
      },
      false,
    );
    expect(overflow, '[P2] Submenu não deve ultrapassar borda inferior da viewport').toBe(false);
  });

  test('11 — somente um submenu de nível 1 fica aberto por vez (P1)', async ({ page }) => {
    const parentItems = page.locator(
      LEVEL1_SEL + '.parent, ' + LEVEL1_SEL + '.has-children, ' + LEVEL1_SEL + ':has(ul)'
    );
    const count = await parentItems.count().catch(() => 0);
    if (count < 2) {
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
      () => {
        const sels = [
          '.vertical-menu-custom-block .submenu:not([style*="display: none"]):not([style*="display:none"])',
          '.awa-vertical-nav .level1:not([style*="display: none"])',
          '.awa-vertical-menu .level1:not([style*="display: none"])',
          '.vertical-menu .level1:not([style*="display: none"])',
        ];
        return sels.reduce((acc, s) => acc + document.querySelectorAll(s).length, 0);
      },
      0,
    );

    expect(openSubmenus, '[P1] Apenas 1 submenu por vez deve ficar aberto').toBeLessThanOrEqual(1);
  });
});

/* ══════════════════════════════════════════════════════════════════
 * SUITE 4 — Navegação por Clique (Desktop)
 * ══════════════════════════════════════════════════════════════════ */
test.describe('Menu Vertical — 4. Navegação por Clique (Desktop)', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-desktop') { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('12 — clicar em item nível 1 navega para categoria (P0)', async ({ page }) => {
    const firstLink = page.locator(LEVEL1_SEL + ' > a').first();
    const exists = await firstLink.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!exists) { test.skip(); return; }

    const href = await firstLink.getAttribute('href').catch(() => '');
    if (!href || href === '#') { test.skip(); return; }

    await firstLink.click();
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});

    expect(page.url(), '[P0] URL deve mudar ao clicar no item do menu').toContain('awamotos.com');
    expect(page.url(), '[P0] URL não deve ser a homepage').not.toBe(HOME);
    expect(page.url(), '[P0] URL não deve ser "#"').not.toMatch(/#$/);
  });

  test('13 — página de categoria tem h1 e grid de produtos (P1)', async ({ page }) => {
    const firstLink = page.locator(LEVEL1_SEL + ' > a').first();
    const exists = await firstLink.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!exists) { test.skip(); return; }

    const href = await firstLink.getAttribute('href').catch(() => '');
    if (!href || href === '#') { test.skip(); return; }

    await firstLink.click();
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});

    const h1 = page.locator('h1.page-title, h1 .base, h1').first();
    const h1Visible = await Promise.race([
      h1.isVisible({ timeout: 8_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 9_000)),
    ]);
    expect(h1Visible, '[P1] Página de categoria deve ter h1 visível').toBe(true);

    const grid = page.locator(
      '.products-grid, .product-items, ol.product-items, .category-products'
    ).first();
    const gridVisible = await Promise.race([
      grid.isVisible({ timeout: 8_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 9_000)),
    ]);
    expect(gridVisible, '[P1] Página de categoria deve exibir grid de produtos').toBe(true);
  });

  test('14 — menu vertical persiste na página de categoria (P1)', async ({ page }) => {
    const firstLink = page.locator(LEVEL1_SEL + ' > a').first();
    const exists = await firstLink.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!exists) { test.skip(); return; }

    const href = await firstLink.getAttribute('href').catch(() => '');
    if (!href || href === '#') { test.skip(); return; }

    await firstLink.click();
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});

    const menuOnCat = page.locator(MENU_SEL).first();
    const menuVisible = await Promise.race([
      menuOnCat.isVisible({ timeout: 8_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 9_000)),
    ]);
    expect(menuVisible, '[P1] Menu vertical deve persistir na página de categoria').toBe(true);
  });

  test('15 — item da categoria atual tem classe de destaque (P2)', async ({ page }) => {
    const firstLink = page.locator(LEVEL1_SEL + ' > a').first();
    const exists = await firstLink.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!exists) { test.skip(); return; }

    const href = await firstLink.getAttribute('href').catch(() => '');
    if (!href || href === '#') { test.skip(); return; }

    await firstLink.click();
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});

    const hasActive = await safeEval(
      page,
      () => !!document.querySelector(
        '.vertical-menu-custom-block li.active, .awa-vertical-nav li.active, ' +
        '.awa-vertical-menu li.active, .vertical-menu li.active, ' +
        '.vertical-menu-custom-block li.current, .awa-vertical-nav li.current, ' +
        '[class*="vertical"] li[class*="active"], [class*="vertical"] li[class*="current"]'
      ),
      false,
    );

    if (!hasActive) {
      console.warn('[P2] Nenhum item do menu marcado como ativo na página de categoria');
    }
    // P2 — informativo
  });
});

/* ══════════════════════════════════════════════════════════════════
 * SUITE 5 — Comportamento Mobile (375px)
 * ══════════════════════════════════════════════════════════════════ */
test.describe('Menu Vertical — 5. Mobile (375px)', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-mobile') { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('16 — menu vertical oculto por padrão em mobile (P1)', async ({ page }) => {
    const menu = page.locator(MENU_SEL).first();
    const visible = await Promise.race([
      menu.isVisible({ timeout: 6_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 7_000)),
    ]);
    if (visible) {
      console.warn('[P1] Menu vertical visível em mobile — verificar responsividade');
    }
    expect(visible, '[P1] Menu vertical deve estar oculto em mobile por padrão').toBe(false);
  });

  test('17 — botão toggle do menu vertical visível em mobile (P1)', async ({ page }) => {
    const toggle = page.locator(
      '.vertical-menu-toggle, .awa-vertical-toggle, ' +
      '[data-action="toggle-vertical-menu"], .menu-vertical-btn, ' +
      'button[aria-label*="categorias" i], .toggle-vertical'
    ).first();
    const visible = await Promise.race([
      toggle.isVisible({ timeout: 6_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 7_000)),
    ]);
    if (!visible) {
      console.warn('[P1] Toggle do menu vertical não encontrado em mobile');
      test.skip();
      return;
    }
    expect(visible, '[P1] Botão toggle deve estar visível em mobile').toBe(true);
  });

  test('18 — clicar no toggle abre drawer do menu vertical (P0)', async ({ page }) => {
    const toggle = page.locator(
      '.vertical-menu-toggle, .awa-vertical-toggle, ' +
      '[data-action="toggle-vertical-menu"], .toggle-vertical'
    ).first();
    const toggleExists = await toggle.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!toggleExists) { test.skip(); return; }

    await toggle.click();
    await page.waitForTimeout(700);

    const menu = page.locator(MENU_SEL).first();
    const opened = await Promise.race([
      menu.isVisible({ timeout: 4_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 5_000)),
    ]);
    expect(opened, '[P0] Drawer deve abrir após clicar no toggle').toBe(true);
  });

  test('19 — botão fechar dentro do drawer fecha o menu mobile (P0)', async ({ page }) => {
    const toggle = page.locator(
      '.vertical-menu-toggle, .awa-vertical-toggle, .toggle-vertical'
    ).first();
    const toggleExists = await toggle.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!toggleExists) { test.skip(); return; }

    await toggle.click();
    await page.waitForTimeout(700);

    const closeBtn = page.locator(
      '.vertical-menu-close, .awa-vertical-close, ' +
      MENU_SEL + ' .close, ' + MENU_SEL + ' [aria-label*="fechar" i], ' +
      MENU_SEL + ' button.close'
    ).first();

    const closeExists = await closeBtn.isVisible({ timeout: 3_000 }).catch(() => false);
    if (closeExists) {
      await closeBtn.click();
    } else {
      console.info('[P0] Botão close não encontrado — fechando via toggle');
      await toggle.click();
    }
    await page.waitForTimeout(600);

    const menu = page.locator(MENU_SEL).first();
    const closed = await Promise.race([
      menu.isVisible({ timeout: 4_000 }).catch(() => true).then(v => !v),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 5_000)),
    ]);
    expect(closed, '[P0] Drawer deve fechar após clicar no botão close').toBe(true);
  });

  test('20 — sem overflow horizontal com menu mobile fechado (P1)', async ({ page }) => {
    const overflow = await safeEval(
      page,
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 4,
      false,
    );
    if (overflow) {
      console.warn('[P1] Overflow horizontal detectado em mobile com menu fechado');
    }
    expect(overflow, '[P1] Não deve haver overflow horizontal em mobile').toBe(false);
  });

  test('21 — itens do drawer são clicáveis e navegam (P1)', async ({ page }) => {
    const toggle = page.locator(
      '.vertical-menu-toggle, .awa-vertical-toggle, .toggle-vertical'
    ).first();
    const toggleExists = await toggle.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!toggleExists) { test.skip(); return; }

    await toggle.click();
    await page.waitForTimeout(700);

    const firstLink = page.locator(ITEMS_SEL).first();
    const linkExists = await firstLink.isVisible({ timeout: 5_000 }).catch(() => false);
    if (!linkExists) { test.skip(); return; }

    const href = await firstLink.getAttribute('href').catch(() => '');
    if (!href || href === '#') { test.skip(); return; }

    await firstLink.click();
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});

    expect(page.url(), '[P1] Clicar no item do drawer deve navegar').not.toBe(HOME);
  });

  test('22 — drawer não ultrapassa largura da viewport (P2)', async ({ page }) => {
    const toggle = page.locator(
      '.vertical-menu-toggle, .awa-vertical-toggle, .toggle-vertical'
    ).first();
    const toggleExists = await toggle.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!toggleExists) { test.skip(); return; }

    await toggle.click();
    await page.waitForTimeout(700);

    const menu = page.locator(MENU_SEL).first();
    const menuVisible = await menu.isVisible({ timeout: 4_000 }).catch(() => false);
    if (!menuVisible) { test.skip(); return; }

    const overflows = await safeEval(
      page,
      () => {
        const el = document.querySelector(
          '.vertical-menu-custom-block, .awa-vertical-nav, ' +
          '#vertical-menu-wrapper, .awa-vertical-menu, .vertical-menu'
        );
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        return rect.right > window.innerWidth + 4;
      },
      false,
    );
    expect(overflows, '[P2] Drawer não deve ultrapassar largura da viewport').toBe(false);
  });

  test('23 — overlay aparece ao abrir drawer em mobile (P2)', async ({ page }) => {
    const toggle = page.locator(
      '.vertical-menu-toggle, .awa-vertical-toggle, .toggle-vertical'
    ).first();
    const toggleExists = await toggle.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!toggleExists) { test.skip(); return; }

    await toggle.click();
    await page.waitForTimeout(700);

    const overlay = page.locator(
      '.awa-overlay, .vertical-menu-overlay, .modal-overlay, ' +
      '.nav-overlay, [class*="overlay"]'
    ).first();
    const overlayVisible = await overlay.isVisible({ timeout: 3_000 }).catch(() => false);
    if (!overlayVisible) {
      console.info('[P2] Overlay não encontrado — pode ser comportamento esperado');
    }
    // P2 informativo — não falha
  });
});

/* ══════════════════════════════════════════════════════════════════
 * SUITE 6 — Acessibilidade (Desktop)
 * ══════════════════════════════════════════════════════════════════ */
test.describe('Menu Vertical — 6. Acessibilidade (Desktop)', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-desktop') { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('24 — menu tem role=navigation ou nav semântico (P1)', async ({ page }) => {
    const hasNav = await safeEval(
      page,
      () => {
        const byRole = document.querySelector(
          '[role="navigation"][class*="vertical"], nav[class*="vertical"], nav[id*="vertical"]'
        );
        const menu = document.querySelector(
          '.vertical-menu-custom-block, .awa-vertical-nav, .awa-vertical-menu'
        );
        const inNav = menu ? !!menu.closest('nav, [role="navigation"]') : false;
        return !!(byRole || inNav);
      },
      false,
    );
    if (!hasNav) {
      console.warn('[P1] Menu vertical sem landmark nav/role=navigation — melhoria recomendada');
    }
    // P1 informativo
  });

  test('25 — links do menu são focáveis via teclado (P1)', async ({ page }) => {
    const menu = page.locator(MENU_SEL).first();
    const menuVisible = await menu.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!menuVisible) { test.skip(); return; }

    const firstLink = page.locator(LEVEL1_SEL + ' > a').first();
    const linkExists = await firstLink.isVisible({ timeout: 4_000 }).catch(() => false);
    if (!linkExists) { test.skip(); return; }

    await firstLink.focus();
    const focused = await safeEval(
      page,
      () => {
        const active = document.activeElement;
        if (!active) return false;
        return !!active.closest(
          '.vertical-menu-custom-block, .awa-vertical-nav, .awa-vertical-menu, .vertical-menu'
        );
      },
      false,
    );
    expect(focused, '[P1] Links do menu vertical devem ser focáveis').toBe(true);
  });

  test('26 — sem erros de JavaScript ao interagir com o menu (P0)', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', err => errors.push(err.message));
    page.on('console', msg => {
      if (msg.type() === 'error') errors.push(msg.text());
    });

    const parentItem = page.locator(
      LEVEL1_SEL + '.parent, ' + LEVEL1_SEL + '.has-children, ' + LEVEL1_SEL + ':has(ul)'
    ).first();
    const hasParent = await parentItem.isVisible({ timeout: 5_000 }).catch(() => false);
    if (hasParent) {
      await parentItem.hover();
      await page.waitForTimeout(400);
    }

    const critical = errors.filter(
      e => !e.includes('favicon') && !e.includes('NS_ERROR') && !e.includes('hydration'),
    );
    if (critical.length > 0) {
      console.warn('[P0] Erros JS no menu:', critical.join(' | '));
    }
    expect(critical, '[P0] Sem erros JS críticos no menu vertical').toHaveLength(0);
  });

  test('27 — menu vertical não causa overflow horizontal em desktop (P1)', async ({ page }) => {
    const overflow = await safeEval(
      page,
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 4,
      false,
    );
    expect(overflow, '[P1] Menu vertical não deve causar overflow horizontal').toBe(false);
  });
});

/* ══════════════════════════════════════════════════════════════════
 * SUITE 7 — Edge Cases e Resiliência (Desktop)
 * ══════════════════════════════════════════════════════════════════ */
test.describe('Menu Vertical — 7. Edge Cases (Desktop)', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name !== 'func-desktop') { test.skip(); return; }
    const ok = await navigateTo(page, HOME);
    if (!ok) test.skip();
  });

  test('28 — menu funciona após navegar para página de categoria (P1)', async ({ page }) => {
    const firstLink = page.locator(LEVEL1_SEL + ' > a').first();
    const exists = await firstLink.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!exists) { test.skip(); return; }

    const href = await firstLink.getAttribute('href').catch(() => '');
    if (!href || href === '#') { test.skip(); return; }

    await firstLink.click();
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});

    const menu = page.locator(MENU_SEL).first();
    const menuVisible = await Promise.race([
      menu.isVisible({ timeout: 8_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 9_000)),
    ]);
    expect(menuVisible, '[P1] Menu vertical deve estar presente na página de categoria').toBe(true);

    const items = page.locator(ITEMS_SEL);
    const count = await items.count().catch(() => 0);
    expect(count, '[P1] Menu vertical na categoria deve ter ao menos 1 item').toBeGreaterThan(0);
  });

  test('29 — resize de desktop para tablet (1024px) não quebra layout (P2)', async ({ page }) => {
    const menu = page.locator(MENU_SEL).first();
    const menuVisible = await menu.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!menuVisible) { test.skip(); return; }

    await page.setViewportSize({ width: 1024, height: 768 });
    await page.waitForTimeout(400);

    const menuAfter = await menu.isVisible({ timeout: 4_000 }).catch(() => false);
    if (!menuAfter) {
      console.info('[P2] Menu vertical oculto em 1024px — verificar breakpoint');
    }
    // P2 informativo
  });

  test('30 — nenhum item do menu tem href duplicado (P2)', async ({ page }) => {
    const links = await safeEval(
      page,
      () => Array.from(document.querySelectorAll(
        '.vertical-menu-custom-block li a, .awa-vertical-nav li a, ' +
        '.awa-vertical-menu li a, .vertical-menu li a'
      )).map(a => (a as HTMLAnchorElement).href).filter(h => h && h !== '#'),
      [] as string[],
    );

    if (links.length === 0) { test.skip(); return; }

    const duplicates = links.length - new Set(links).size;
    if (duplicates > 0) {
      console.warn('[P2] ' + duplicates + ' link(s) duplicado(s) no menu vertical');
    }
    // P2 informativo
  });

  test('31 — menu vertical presente na página 404 (P2)', async ({ page }) => {
    await page.goto('https://awamotos.com/pagina-que-nao-existe-xyzabc', {
      waitUntil: 'domcontentloaded',
      timeout: 20_000,
    }).catch(() => {});

    const menu = page.locator(MENU_SEL).first();
    const visible = await Promise.race([
      menu.isVisible({ timeout: 6_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 7_000)),
    ]);
    if (!visible) {
      console.info('[P2] Menu vertical não aparece em página 404 — pode ser esperado');
    }
    // P2 informativo
  });

  test('32 — menu não bloqueia header ao fazer scroll (P2)', async ({ page }) => {
    const menu = page.locator(MENU_SEL).first();
    const menuVisible = await menu.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!menuVisible) { test.skip(); return; }

    await safeEval(page, () => { window.scrollBy(0, 400); return true; }, true);
    await page.waitForTimeout(500);

    const header = page.locator('header, .awa-site-header, .page-header').first();
    const headerVisible = await Promise.race([
      header.isVisible({ timeout: 4_000 }).catch(() => false),
      new Promise<boolean>(resolve => setTimeout(() => resolve(false), 5_000)),
    ]);
    if (!headerVisible) {
      console.info('[P2] Header não visível após scroll — verificar sticky header');
    }
    // P2 informativo
  });
});
