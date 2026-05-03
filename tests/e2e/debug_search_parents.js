const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  
  const info = await page.evaluate(() => {
    const input = document.querySelector('input#search');
    if (!input) return 'Not found';
    
    let res = '';
    let curr = input;
    while(curr && !curr.className.includes('awa-header-search-col')) {
        const rect = curr.getBoundingClientRect();
        const cs = window.getComputedStyle(curr);
        res += `${curr.tagName}.${curr.className} - Width: ${rect.width} | Flex: ${cs.flex} | Disp: ${cs.display} | Pos: ${cs.position}\n`;
        curr = curr.parentElement;
    }
    const rect = curr.getBoundingClientRect();
    const cs = window.getComputedStyle(curr);
    res += `${curr.tagName}.${curr.className} - Width: ${rect.width} | Flex: ${cs.flex} | Disp: ${cs.display} | Pos: ${cs.position}\n`;
    
    return res;
  });
  
  console.log(info);
  await browser.close();
})();
