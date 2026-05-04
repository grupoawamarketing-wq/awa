const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1'
  });
  const page = await context.newPage();
  
  await page.goto('https://awamotos.com/', { waitUntil: 'networkidle' });

  const cartInfo = await page.evaluate(() => {
    const minicart = document.querySelector('.minicart-wrapper');
    if (!minicart) return 'No minicart wrapper found';
    
    const showcart = minicart.querySelector('.showcart');
    
    return {
      wrapperStyle: minicart ? window.getComputedStyle(minicart).display : 'N/A',
      wrapperClass: minicart ? minicart.className : 'N/A',
      wrapperRect: minicart ? minicart.getBoundingClientRect() : 'N/A',
      showcartStyle: showcart ? window.getComputedStyle(showcart).display : 'N/A',
      showcartClass: showcart ? showcart.className : 'N/A',
      showcartRect: showcart ? showcart.getBoundingClientRect() : 'N/A',
    };
  });
  
  console.log(cartInfo);
  await browser.close();
})();
