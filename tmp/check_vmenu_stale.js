const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(3500);
  await page.hover('.navigation.verticalmenu .title-category-dropdown');
  await page.waitForTimeout(500);

  await page.evaluate(() => {
    const items = Array.from(document.querySelectorAll('.navigation.verticalmenu .togge-menu > li.level0.parent')).slice(0, 5);
    items.forEach((li) => li.classList.add('_active', 'is-open'));
  });

  await page.hover('.navigation.verticalmenu .togge-menu > li:nth-child(1)');
  await page.waitForTimeout(300);

  const out = await page.evaluate(() => {
    const all = Array.from(document.querySelectorAll('.navigation.verticalmenu .togge-menu > li.level0'));
    const visible = all
      .filter((node) => {
        const sub = node.querySelector(':scope > .submenu');
        if (!sub) return false;
        const cs = getComputedStyle(sub);
        return cs.display !== 'none' && cs.visibility !== 'hidden' && parseFloat(cs.opacity || '1') > 0;
      })
      .map((n) => n.getAttribute('data-menu'));

    const stale = all
      .filter((node) => node.classList.contains('_active') || node.classList.contains('is-open'))
      .map((n) => n.getAttribute('data-menu'));

    const active = all
      .filter((node) => node.classList.contains('vmm-active'))
      .map((n) => n.getAttribute('data-menu'));

    return { visible, stale, active };
  });

  console.log(JSON.stringify(out, null, 2));
  await page.screenshot({ path: 'tmp_vmenu_stale_after_merged_clear.png' });
  await browser.close();
})();
