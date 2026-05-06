import { chromium } from 'playwright';
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(5000);
  
  const data = await page.evaluate(() => {
    const el = document.querySelector('.top-search');
    let c = el;
    let parents = [];
    while(c && c.tagName !== 'BODY') {
      parents.push({
        class: c.className,
        hasData: c.hasAttribute('data-awa-header-content')
      });
      c = c.parentElement;
    }
    return parents;
  });
  
  console.log(JSON.stringify(data));
  await browser.close();
})();
