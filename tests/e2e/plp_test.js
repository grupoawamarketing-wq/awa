const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 1024 });
  await page.goto('https://awamotos.com/catalogsearch/result/?q=moto', { waitUntil: 'networkidle' });
  
  await page.evaluate(() => {
    const style = document.createElement('style');
    style.innerHTML = `
      html body.catalogsearch-result-index .page-title-wrapper,
      html body.page-products .page-title-wrapper,
      html body.catalogsearch-result-index .nav-breadcrumbs > .container,
      html body.page-products .nav-breadcrumbs > .container,
      html body.catalogsearch-result-index .columns,
      html body.page-products .columns {
          max-width: 1440px !important;
          margin: 0 auto !important;
          padding-left: 24px !important;
          padding-right: 24px !important;
          box-sizing: border-box !important;
          width: 100% !important;
      }
      html body.catalogsearch-result-index .page-main,
      html body.page-products .page-main {
          padding-left: 0 !important;
          padding-right: 0 !important;
      }
      html body .breadcrumbs { padding-left: 0 !important; }
    `;
    document.head.appendChild(style);
  });

  const data = await page.evaluate(() => {
    const title = document.querySelector('h1')?.getBoundingClientRect();
    const bread = document.querySelector('.breadcrumbs li')?.getBoundingClientRect();
    const cols = document.querySelector('.columns');
    const c1 = cols?.children[0]?.getBoundingClientRect();
    return {
      titleLeft: title?.left,
      breadLeft: bread?.left,
      colLeft: c1?.left,
      titleWidth: title?.width
    };
  });
  console.log(data);
  await page.screenshot({ path: 'plp_fixed.png', fullPage: true });
  await browser.close();
})();
