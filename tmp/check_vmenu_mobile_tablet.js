const { chromium } = require('playwright');

async function run(viewport, label) {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage({ viewport });

  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(3500);

  const navToggle = page.locator('.action.nav-toggle, .nav-toggle').first();
  if (await navToggle.count()) {
    await navToggle.click({ force: true });
    await page.waitForTimeout(700);
  }

  const vTitle = page.locator('.navigation.verticalmenu .title-category-dropdown').first();
  if (await vTitle.count() && await vTitle.isVisible()) {
    await vTitle.click();
    await page.waitForTimeout(450);
  }

  const state1 = await page.evaluate(() => {
    const menu = document.querySelector('.navigation.verticalmenu .togge-menu');
    if (!menu) return { menuFound: false };
    const cs = getComputedStyle(menu);
    return {
      menuFound: true,
      menuVisible: cs.display !== 'none' && cs.visibility !== 'hidden',
      openClass: menu.classList.contains('menu-open') || menu.classList.contains('vmm-open')
    };
  });

  const firstToggle = page.locator('.navigation.verticalmenu .togge-menu > li.level0.parent .open-children-toggle').first();
  if (await firstToggle.count() && await firstToggle.isVisible()) {
    await firstToggle.click();
    await page.waitForTimeout(350);
  }

  const state2 = await page.evaluate(() => {
    const firstParent = document.querySelector('.navigation.verticalmenu .togge-menu > li.level0.parent');
    const panel = firstParent ? firstParent.querySelector(':scope > .submenu') : null;
    if (!firstParent || !panel) return { parentFound: false };
    const cps = getComputedStyle(panel);
    return {
      parentFound: true,
      parentActive: firstParent.classList.contains('_active') || firstParent.classList.contains('vmm-active') || firstParent.classList.contains('is-open'),
      panelVisible: cps.display !== 'none' && cps.visibility !== 'hidden' && parseFloat(cps.opacity || '1') > 0
    };
  });

  await page.screenshot({ path: `tmp_vmenu_${label}.png`, fullPage: false });
  await browser.close();
  return { label, viewport, state1, state2 };
}

(async () => {
  const results = [];
  results.push(await run({ width: 375, height: 812 }, 'mobile_375'));
  results.push(await run({ width: 768, height: 1024 }, 'tablet_768'));
  console.log(JSON.stringify(results, null, 2));
})();
