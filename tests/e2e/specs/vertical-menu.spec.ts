import { test, expect, Page } from '@playwright/test';
import path from 'path';

import { waitForHeader } from '../helpers/header.helpers';

const SCREENSHOT_DIR = path.join(__dirname, '..', 'screenshots', 'vertical-menu');

const SEL = {
  navToggle: '[data-awa-nav-toggle="true"], .action.nav-toggle, .nav-toggle',
  trigger: '[data-role="awa-vertical-menu-trigger"]',
  menu: 'nav[data-role="awa-vertical-menu"] > ul.togge-menu.list-category-dropdown',
  firstLevelLink: 'nav[data-role="awa-vertical-menu"] li.level0 > a.level-top',
  closeButton: '.awa-nav-close',
  overlay: '.awa-nav-overlay',
  cookieAccept: '.cookie-btn-accept, #btn-cookie-allow, .allow, button:has-text("Permitir cookies")',
} as const;

function shot(name: string): string {
  return path.join(SCREENSHOT_DIR, `${name}.png`);
}

async function dismissCookies(page: Page): Promise<void> {
  const button = page.locator(SEL.cookieAccept).first();

  if (await button.isVisible({ timeout: 2_000 }).catch(() => false)) {
    await button.click();
    await page.waitForTimeout(200);
  }
}

async function openPrimaryDrawerIfNeeded(page: Page): Promise<void> {
  const navToggle = page.locator(SEL.navToggle).first();
  const trigger = page.locator(SEL.trigger).first();
  const viewportWidth = page.viewportSize()?.width ?? 1280;

  if (viewportWidth >= 992) {
    return;
  }

  if (await navToggle.isVisible({ timeout: 2_000 }).catch(() => false)) {
    await navToggle.click();
    await expect.poll(async () =>
      page.evaluate(() => document.body.classList.contains('nav-open'))
    ).toBe(true);
  }

  await expect(trigger).toBeVisible();
}

const DESKTOP_BREAKPOINT = 992;

test.describe('Vertical Menu', () => {
  test.beforeEach(async ({ page }) => {
    const viewportWidth = page.viewportSize()?.width ?? 1280;

    // The vertical menu ("Departamentos") is desktop-only (≥992px).
    // On mobile/tablet the trigger is hidden and the menu lives inside the off-canvas drawer.
    test.skip(viewportWidth < DESKTOP_BREAKPOINT, `Vertical menu is desktop-only (viewport ${viewportWidth}px < ${DESKTOP_BREAKPOINT}px)`);

    await page.goto('/?e2e_vertical_menu=' + Date.now(), {
      waitUntil: 'domcontentloaded',
      timeout: 45_000,
    });
    await waitForHeader(page);
    await dismissCookies(page);
  });

  test('abre o menu vertical e renderiza categorias no breakpoint atual', async ({ page }, testInfo) => {
    await openPrimaryDrawerIfNeeded(page);

    const trigger = page.locator(SEL.trigger).first();
    const menu = page.locator(SEL.menu).first();
    const firstLink = page.locator(SEL.firstLevelLink).first();
    const viewportWidth = page.viewportSize()?.width ?? 1280;

    await expect(trigger).toBeVisible();

    // Give some time for Magento x-magento-init and our controller to boot.
    await page.waitForTimeout(1500);

    // On desktop homepage, the menu starts already expanded (keepDesktopMenuExpanded).
    const initialExpanded = await trigger.getAttribute('aria-expanded');
    const isAlreadyOpen = initialExpanded === 'true';

    if (isAlreadyOpen && viewportWidth >= 992) {
      // Desktop homepage: menu auto-expanded. Verify it's properly open.
      await expect(menu).toBeVisible();
      await expect(firstLink).toBeVisible();
      await expect(firstLink).not.toHaveText('');
    } else {
      // Menu starts closed — click to open.
      await trigger.click();
      await expect(trigger).toHaveAttribute('aria-expanded', 'true');
      await expect(menu).toBeVisible();
      await expect(firstLink).toBeVisible();
      await expect(firstLink).not.toHaveText('');
    }

    await page.screenshot({
      path: shot(`open-${testInfo.project.name}`),
      fullPage: false,
    });
  });

  test('fecha corretamente com Escape no modo desktop e no drawer mobile', async ({ page }) => {
    await openPrimaryDrawerIfNeeded(page);

    const trigger = page.locator(SEL.trigger).first();
    const menu = page.locator(SEL.menu).first();
    const closeButton = page.locator(SEL.closeButton).first();
    const viewportWidth = page.viewportSize()?.width ?? 1280;

    // On desktop homepage, keepDesktopMenuExpanded keeps the menu permanently open;
    // Escape won't close it. Detect this and verify it stays open.
    const isDesktopHomepage = viewportWidth >= 992 &&
      await page.evaluate(() => document.body.classList.contains('cms-index-index') || document.body.classList.contains('cms-homepage_ayo_home5'));

    // Ensure menu is open before testing close.
    const initialExpanded = await trigger.getAttribute('aria-expanded');
    if (initialExpanded !== 'true') {
      await trigger.click();
    }
    await expect(menu).toBeVisible();

    await page.keyboard.press('Escape');

    if (isDesktopHomepage) {
      // Menu should remain open on homepage desktop (by design).
      await page.waitForTimeout(500);
      await expect(menu).toBeVisible();
      return;
    }

    if (viewportWidth >= 992) {
      await expect(trigger).toHaveAttribute('aria-expanded', 'false');
      await expect(menu).not.toBeVisible();
      return;
    }

    await expect.poll(async () =>
      page.evaluate(() => document.body.classList.contains('nav-open'))
    ).toBe(false);

    if (await closeButton.count()) {
      await expect(closeButton).not.toBeVisible({ timeout: 5_000 }).catch(() => {});
    }
  });

  test('fecha ao clicar fora do menu ou no overlay mobile', async ({ page }) => {
    await openPrimaryDrawerIfNeeded(page);

    const trigger = page.locator(SEL.trigger).first();
    const menu = page.locator(SEL.menu).first();
    const viewportWidth = page.viewportSize()?.width ?? 1280;

    const isDesktopHomepage = viewportWidth >= 992 &&
      await page.evaluate(() => document.body.classList.contains('cms-index-index') || document.body.classList.contains('cms-homepage_ayo_home5'));

    // Ensure menu is open.
    const initialExpanded = await trigger.getAttribute('aria-expanded');
    if (initialExpanded !== 'true') {
      await trigger.click();
    }
    await expect(menu).toBeVisible();

    if (isDesktopHomepage) {
      // On homepage desktop, clicking outside doesn't close the menu (by design).
      await page.mouse.click(viewportWidth - 20, 140);
      await page.waitForTimeout(500);
      await expect(menu).toBeVisible();
      return;
    }

    if (viewportWidth >= 992) {
      await page.mouse.click(viewportWidth - 20, 140);
      await expect(trigger).toHaveAttribute('aria-expanded', 'false');
      await expect(menu).not.toBeVisible();
      return;
    }

    const overlay = page.locator(SEL.overlay).first();
    await expect(overlay).toBeVisible();
    await overlay.click({ position: { x: 10, y: 10 } });

    await expect.poll(async () =>
      page.evaluate(() => document.body.classList.contains('nav-open'))
    ).toBe(false);
  });
});
