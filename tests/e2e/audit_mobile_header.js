const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  
  console.log('Navigating to homepage...');
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(3000);
  await page.screenshot({ path: 'mobile_home_header.png', fullPage: false });
  console.log('Saved mobile_home_header.png');
  
  const menuBtn = await page.$('.action.nav-toggle, .mobile-menu-icon, .header-menu-icon, .nav-toggle, [data-action="toggle-nav"]');
  if (menuBtn) {
      console.log('Clicking mobile menu...');
      await menuBtn.click();
      await page.waitForTimeout(2000);
      await page.screenshot({ path: 'mobile_menu_open.png', fullPage: false });
      console.log('Saved mobile_menu_open.png');
  } else {
      console.log('Mobile menu button not found.');
  }

  console.log('Navigating to a PLP (Category Page)...');
  await page.goto('https://awamotos.com/catalogsearch/result/?q=retrovisor');
  await page.waitForTimeout(3000);
  await page.screenshot({ path: 'mobile_plp.png', fullPage: false });
  console.log('Saved mobile_plp.png');
  
  await browser.close();
})();
