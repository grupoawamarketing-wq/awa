const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  
  const info = await page.evaluate(() => {
    const inputs = document.querySelectorAll('input[type="text"]');
    let res = '';
    inputs.forEach((input, i) => {
        const rect = input.getBoundingClientRect();
        if (rect.width > 0 && rect.height > 0) {
            res += `Input ${i}: id="${input.id}" class="${input.className}" placeholder="${input.placeholder}" Rect: ${rect.width}x${rect.height} pos: ${rect.x},${rect.y}\n`;
            let parent = input.parentElement;
            while(parent && parent.tagName !== 'BODY') {
                res += `  Parent: ${parent.tagName} class="${parent.className}"\n`;
                parent = parent.parentElement;
            }
        }
    });
    return res;
  });
  
  console.log(info);
  await browser.close();
})();
