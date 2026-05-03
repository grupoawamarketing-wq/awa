const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const data = await page.evaluate(() => {
    const topbar = document.querySelector('.top-header');
    const logo = document.querySelector('.logo');
    const logoImg = document.querySelector('.logo img');
    const search = document.querySelector('.block-search');
    const searchInput = document.querySelector('#search');

    return {
      topbar: topbar ? window.getComputedStyle(topbar).height : null,
      logoContainer: logo ? window.getComputedStyle(logo).width : null,
      logoImg: logoImg ? { width: window.getComputedStyle(logoImg).width, height: window.getComputedStyle(logoImg).height } : null,
      searchContainer: search ? window.getComputedStyle(search).height : null,
      searchInput: searchInput ? window.getComputedStyle(searchInput).height : null
    };
  });

  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
