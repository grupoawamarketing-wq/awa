const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  
  const info = await page.evaluate(() => {
    const input = document.querySelector('input#search');
    const wrapper = document.querySelector('.awa-header-search-col');
    if (!input) return 'Input not found';
    
    const csInput = window.getComputedStyle(input);
    const rect = input.getBoundingClientRect();
    const wRect = wrapper.getBoundingClientRect();
    
    return `
      Wrapper Rect Width: ${wRect.width}
      Input Rect Width: ${rect.width}
      Input Computed Width: ${csInput.width}
      Input Padding: ${csInput.paddingLeft} ${csInput.paddingRight}
      Input Border Radius: ${csInput.borderRadius}
    `;
  });
  
  console.log(info);
  await browser.close();
})();
