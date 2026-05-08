const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('https://awamotos.com', { waitUntil: 'networkidle', timeout: 30000 });
  
  // Screenshot homepage desktop full
  await page.screenshot({ path: 'ss_home_desktop.png', fullPage: true });

  // Check vertical menu computed styles
  const menuState = await page.evaluate(() => {
    const ul = document.querySelector('ul.togge-menu.list-category-dropdown');
    const items = document.querySelectorAll('ul.togge-menu.list-category-dropdown > li.level0');
    const trigger = document.querySelector('.menu_left_home1, .header-control.awa-nav-bar, [data-role="awa-vertical-menu"]');
    if (!ul) return { error: 'ul.togge-menu not found' };
    const s = getComputedStyle(ul);
    return {
      ulDisplay: s.display,
      ulVisibility: s.visibility,
      ulOpacity: s.opacity,
      ulHeight: s.height,
      ulMaxHeight: s.maxHeight,
      ulPosition: s.position,
      itemCount: items.length,
      triggerFound: !!trigger,
      triggerClass: trigger ? trigger.className : 'none'
    };
  });
  console.log('MENU STATE:', JSON.stringify(menuState, null, 2));

  // Check for JS errors
  const errors = [];
  page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });

  // Hover over Departamentos button to trigger menu
  const deptBtn = await page.$('[data-role="awa-vertical-menu"], .vertical-menu, .menu_left_home1');
  if (deptBtn) {
    await deptBtn.hover();
    await page.waitForTimeout(500);
    await page.screenshot({ path: 'ss_home_hover.png', fullPage: false });
    const afterHover = await page.evaluate(() => {
      const ul = document.querySelector('ul.togge-menu.list-category-dropdown');
      if (!ul) return { error: 'not found' };
      const s = getComputedStyle(ul);
      return { display: s.display, visibility: s.visibility, opacity: s.opacity, height: s.height };
    });
    console.log('AFTER HOVER:', JSON.stringify(afterHover, null, 2));
  } else {
    console.log('Departamentos trigger NOT found');
  }

  // Mobile check
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('https://awamotos.com', { waitUntil: 'networkidle', timeout: 30000 });
  await page.screenshot({ path: 'ss_home_mobile.png', fullPage: true });

  await browser.close();
  console.log('DONE');
})();
