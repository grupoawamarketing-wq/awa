const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(3500);
  await page.hover('.navigation.verticalmenu .title-category-dropdown');
  await page.waitForTimeout(500);

  const seq = [];
  for (const n of [1, 3, 5, 6]) {
    await page.hover('.navigation.verticalmenu .togge-menu > li:nth-child(' + n + ')');
    await page.waitForTimeout(260);

    const visible = await page.evaluate(() => {
      const all = Array.from(document.querySelectorAll('.navigation.verticalmenu .togge-menu > li.level0'));
      return all
        .filter((node) => {
          const sub = node.querySelector(':scope > .submenu');
          if (!sub) return false;
          const cs = getComputedStyle(sub);
          return cs.display !== 'none' && cs.visibility !== 'hidden' && parseFloat(cs.opacity || '1') > 0;
        })
        .map((n) => n.getAttribute('data-menu'));
    });

    seq.push({ item: n, visible });
  }

  console.log(JSON.stringify(seq, null, 2));
  await page.screenshot({ path: 'tmp_vmenu_after_merged_clear.png' });
  await browser.close();
})();
