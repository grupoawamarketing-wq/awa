const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  
  const info = await page.evaluate(() => {
    const input = document.querySelector('input#search');
    if (!input) return 'Not found';
    
    let rules = '';
    const cs = window.getComputedStyle(input);
    rules += `Width: ${cs.width}, Height: ${cs.height}, MaxWidth: ${cs.maxWidth}, MinWidth: ${cs.minWidth}, Border-radius: ${cs.borderRadius}, Padding: ${cs.padding}\n`;
    
    return rules;
  });
  
  console.log(info);
  await browser.close();
})();
