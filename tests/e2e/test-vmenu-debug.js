const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto('https://awamotos.com/?e2e_vmenu=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(5000);
  
  // Check trigger state
  const info = await page.evaluate(() => {
    const trigger = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
    const titleDrop = document.querySelector('.title-category-dropdown');
    
    if (!trigger && !titleDrop) return { found: false };
    
    return {
      triggerTag: trigger?.tagName,
      triggerClass: trigger?.className,
      triggerAriaExpanded: trigger?.getAttribute('aria-expanded'),
      triggerBound: trigger?.getAttribute('data-awa-vmenu-hotfix-title-bound'),
      titleDropTag: titleDrop?.tagName,
      titleDropClass: titleDrop?.className,
      titleDropAriaExpanded: titleDrop?.getAttribute('aria-expanded'),
      titleDropBound: titleDrop?.getAttribute('data-awa-vmenu-hotfix-title-bound'),
      areSame: trigger === titleDrop,
      parentOverflow: trigger ? getComputedStyle(trigger.closest('[data-content-type="vmm_navigation"]') || trigger.parentElement).overflow : null,
      parentMaxHeight: trigger ? getComputedStyle(trigger.closest('[data-content-type="vmm_navigation"]') || trigger.parentElement).maxHeight : null,
      triggerDisplay: trigger ? getComputedStyle(trigger).display : null,
      triggerVisibility: trigger ? getComputedStyle(trigger).visibility : null,
      triggerPointerEvents: trigger ? getComputedStyle(trigger).pointerEvents : null,
      triggerRect: trigger ? { x: trigger.getBoundingClientRect().x, y: trigger.getBoundingClientRect().y, w: trigger.getBoundingClientRect().width, h: trigger.getBoundingClientRect().height } : null,
    };
  });
  console.log('Pre-click info:', JSON.stringify(info, null, 2));
  
  // Try clicking
  const trigger = page.locator('[data-role="awa-vertical-menu-trigger"]').first();
  const visible = await trigger.isVisible().catch(() => false);
  console.log('Trigger visible?', visible);
  
  if (visible) {
    await trigger.click({ force: true });
    await page.waitForTimeout(1000);
    
    const afterClick = await page.evaluate(() => {
      const el = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
      const list = document.querySelector('ul.togge-menu.list-category-dropdown');
      return {
        ariaExpanded: el?.getAttribute('aria-expanded'),
        listVisible: list ? getComputedStyle(list).display !== 'none' : null,
        listHasMenuOpen: list?.classList.contains('menu-open'),
        listHasVmmOpen: list?.classList.contains('vmm-open'),
      };
    });
    console.log('After click:', JSON.stringify(afterClick, null, 2));
  }
  
  await browser.close();
})();
