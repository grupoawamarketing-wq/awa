const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1'
  });
  const page = await context.newPage();
  
  await page.goto('https://awamotos.com/', { waitUntil: 'networkidle' });

  const ancestorHidden = await page.evaluate(() => {
    let el = document.querySelector('.minicart-wrapper');
    let hiddenBy = null;
    while (el && el !== document.body) {
      if (window.getComputedStyle(el).display === 'none') {
        hiddenBy = { tag: el.tagName, className: el.className, id: el.id };
        break;
      }
      el = el.parentElement;
    }
    return hiddenBy;
  });
  
  console.log("Hidden by:", ancestorHidden);
  await browser.close();
})();
