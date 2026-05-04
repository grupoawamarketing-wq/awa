const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1'
  });
  const page = await context.newPage();
  
  await page.goto('https://awamotos.com/', { waitUntil: 'networkidle' });

  const html = await page.evaluate(() => {
    const minicart = document.querySelector('.minicart-wrapper');
    if (!minicart) return 'No minicart';
    
    // Log styles that might hide it
    const showcart = minicart.querySelector('.showcart');
    const compStyle = showcart ? window.getComputedStyle(showcart) : null;
    const beforeStyle = showcart ? window.getComputedStyle(showcart, '::before') : null;
    const afterStyle = showcart ? window.getComputedStyle(showcart, '::after') : null;
    
    return {
      outerHTML: minicart.outerHTML,
      showcartSize: compStyle ? `${compStyle.width} x ${compStyle.height}` : 'N/A',
      beforeSize: beforeStyle ? `${beforeStyle.width} x ${beforeStyle.height}` : 'N/A',
      beforeContent: beforeStyle ? beforeStyle.content : 'N/A',
      afterSize: afterStyle ? `${afterStyle.width} x ${afterStyle.height}` : 'N/A',
      afterContent: afterStyle ? afterStyle.content : 'N/A',
      position: compStyle ? compStyle.position : 'N/A'
    };
  });
  
  console.log(JSON.stringify(html, null, 2));
  await browser.close();
})();
