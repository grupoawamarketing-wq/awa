const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  
  const info = await page.evaluate(() => {
    const inputs = document.querySelectorAll('input');
    let res = '';
    inputs.forEach((input, i) => {
        const rect = input.getBoundingClientRect();
        if (rect.width > 0 && rect.height > 0) {
            res += `Input ${i}: type="${input.type}" id="${input.id}" class="${input.className}" placeholder="${input.placeholder}" Rect: ${rect.width}x${rect.height} pos: ${rect.x},${rect.y}\n`;
        }
    });
    return res;
  });
  
  console.log(info);
  await browser.close();
})();
