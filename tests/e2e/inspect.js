const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('https://awamotos.com', { waitUntil: 'networkidle' });
  const styles = await page.evaluate(() => {
    const el = document.querySelector('li.level0.ui-menu-item');
    if (!el) return 'Element not found';
    const style = window.getComputedStyle(el);
    return `display: ${style.display}, visibility: ${style.visibility}, opacity: ${style.opacity}, height: ${style.height}, position: ${style.position}`;
  });
  console.log('Styles:', styles);
  
  const menuStyles = await page.evaluate(() => {
    const el = document.querySelector('ul.togge-menu.list-category-dropdown');
    if (!el) return 'Menu not found';
    const style = window.getComputedStyle(el);
    return `Menu display: ${style.display}, height: ${style.height}, visibility: ${style.visibility}, opacity: ${style.opacity}`;
  });
  console.log(menuStyles);

  await browser.close();
})();
