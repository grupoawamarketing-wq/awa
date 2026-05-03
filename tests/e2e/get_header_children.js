const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  
  const innerHtml = await page.evaluate(() => {
    const inner = document.querySelector('.awa-main-header__inner');
    if (!inner) return 'Not found';
    let res = '';
    for (let child of inner.children) {
       res += `<${child.tagName.toLowerCase()} class="${child.className}">\n`;
       if (child.className.includes('primary-row')) {
           for (let sub of child.children) {
               res += `  <${sub.tagName.toLowerCase()} class="${sub.className}">\n`;
               if (sub.className.includes('awa-header-mobile-toggle')) {
                   res += `    Toggle Button Text: ${sub.innerText.trim()}\n`;
               }
           }
       }
    }
    return res;
  });
  
  console.log(innerHtml);
  await browser.close();
})();
