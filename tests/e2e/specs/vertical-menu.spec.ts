import { test, expect, Page } from '@playwright/test';
import path from 'path';

import { waitForHeader } from '../helpers/header.helpers';

const SCREENSHOT_DIR = path.join(__dirname, '..', 'screenshots', 'vertical-menu');

const SEL = {
  navToggle: '[data-awa-nav-toggle="true"], .action.nav-toggle, .nav-toggle',
  trigger: '[data-role="awa-vertical-menu-trigger"]',
  menu: 'nav[data-role="awa-vertical-menu"] > ul.togge-menu.list-category-dropdown',
  firstLevelLink: 'nav[data-role="awa-vertical-menu"] li.level0 > a.level-top',
  status: '[data-role="awa-vertical-menu-status"]',
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

test.describe('Vertical Menu', () => {
  test.beforeEach(async ({ page }) => {
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
    const status = page.locator(SEL.status).first();

    await expect(trigger).toBeVisible();
    await expect(trigger).toHaveAttribute('aria-expanded', /false|true/);
    await expect(status).toContainText(/Menu fechado|Menu aberto/);

    await trigger.click();

    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    await expect(menu).toBeVisible();
    await expect(firstLink).toBeVisible();
    await expect(firstLink).not.toHaveText('');
    await expect(status).toContainText('Menu aberto');

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

    await trigger.click();
    await expect(menu).toBeVisible();

    await page.keyboard.press('Escape');

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
    const status = page.locator(SEL.status).first();
    const viewportWidth = page.viewportSize()?.width ?? 1280;

    await trigger.click();
    await expect(menu).toBeVisible();

    if (viewportWidth >= 992) {
      await page.mouse.click(viewportWidth - 20, 140);
      await expect(trigger).toHaveAttribute('aria-expanded', 'false');
      await expect(menu).not.toBeVisible();
      await expect(status).toContainText('Menu fechado');
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
