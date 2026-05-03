const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(3000);
  await page.hover('.navigation.verticalmenu .title-category-dropdown');
  await page.waitForTimeout(500);

  const selectors = await page.$$eval('.navigation.verticalmenu .togge-menu > li.level0.parent', (els) => {
    return els.slice(0, 4).map((el) => {
      const idx = Array.from(el.parentElement.children).indexOf(el) + 1;
      return `.navigation.verticalmenu .togge-menu > li:nth-child(${idx})`;
    });
  });

  const results = [];

  for (const sel of selectors) {
    await page.hover(sel);
    await page.waitForTimeout(300);

    const state = await page.evaluate(() => {
      const all = Array.from(document.querySelectorAll('.navigation.verticalmenu .togge-menu > li.level0'));
      const visible = all.filter((node) => {
        const sub = node.querySelector(':scope > .submenu');
        if (!sub) return false;
        const cs = getComputedStyle(sub);
        return cs.display !== 'none' && cs.visibility !== 'hidden' && parseFloat(cs.opacity || '1') > 0;
      }).map((n) => n.getAttribute('data-menu'));

      const active = all
        .filter((node) => node.classList.contains('vmm-active'))
        .map((n) => n.getAttribute('data-menu'));

      return { visible, active };
    });

    results.push({ sel, ...state });
  }

  console.log(JSON.stringify(results, null, 2));
  await page.screenshot({ path: 'tmp_vmenu_hover_seq_after_fix.png' });
  await browser.close();
})();
