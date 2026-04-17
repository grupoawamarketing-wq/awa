import { test } from '@playwright/test';

test('vertical menu final screenshot', async ({ page }) => {
  await page.setViewportSize({ width: 1400, height: 800 });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(2000);

  await page.evaluate(() => {
    // Open menu
    const t = document.querySelector<HTMLElement>('button.our_categories, .title-category-dropdown');
    if (t) t.click();
    const ml = document.querySelector<HTMLElement>('.navigation.verticalmenu .togge-menu');
    if (ml) { ml.style.display = 'block'; ml.style.visibility = 'visible'; ml.style.opacity = '1'; }
  });
  await page.waitForTimeout(400);

  // Screenshot: menu list visible (no submenu)
  const menuEl = await page.$('.navigation.verticalmenu.side-verticalmenu');
  if (menuEl) {
    const box = await menuEl.boundingBox();
    if (box) {
      // Screenshot around the menu widget
      await page.screenshot({
        path: '/tmp/vm-final-list.png',
        clip: { x: Math.max(0, box.x - 5), y: Math.max(0, box.y - 5), width: Math.min(box.width + 10, 400), height: Math.min(box.height + 10, 650) }
      });
    }
  }

  // Force open first parent submenu and screenshot wider area
  await page.evaluate(() => {
    const parents = document.querySelectorAll<HTMLElement>('.navigation.verticalmenu .ui-menu-item.level0.parent');
    if (!parents.length) return;
    const first = parents[0];
    first.classList.add('_active');
    const sub = first.querySelector<HTMLElement>('.submenu, div.level0.submenu');
    if (sub) {
      // Override our CSS visibility/opacity via !important won't work, use style with priority
      sub.setAttribute('style', 'visibility: visible !important; opacity: 1 !important; display: block !important; pointer-events: auto !important;');
    }
  });
  await page.waitForTimeout(400);

  const menuEl2 = await page.$('.navigation.verticalmenu.side-verticalmenu');
  if (menuEl2) {
    const box = await menuEl2.boundingBox();
    if (box) {
      await page.screenshot({
        path: '/tmp/vm-final-submenu.png',
        clip: { x: Math.max(0, box.x - 5), y: Math.max(0, box.y - 5), width: 850, height: Math.min(box.height + 10, 650) }
      });
    }
  }

  console.log('Screenshots done');
});
