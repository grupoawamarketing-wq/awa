import { test, expect } from '@playwright/test';

test('vertical menu screenshots', async ({ page }) => {
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(3000);
  
  const url = page.url();
  console.log('URL:', url);
  
  if (url.includes('login')) {
    // B2B redirect — go to a category page that has the vertical menu in sidebar
    await page.goto('https://awamotos.com/bauletos.html', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForTimeout(2500);
  }
  
  await page.screenshot({ path: '/tmp/vm-01-page.png', fullPage: false });
  console.log('Screenshot 1: page');
  
  // Try to find the vertical menu
  const menuExists = await page.$('.navigation.verticalmenu.side-verticalmenu');
  console.log('Menu exists:', !!menuExists);
  
  if (menuExists) {
    // Hover on trigger
    const trigger = await page.$('.our_categories.title-category-dropdown');
    if (trigger) {
      await trigger.hover();
      await page.waitForTimeout(500);
      await page.screenshot({ path: '/tmp/vm-02-trigger-hover.png', fullPage: false });
      console.log('Screenshot 2: trigger hover');
    }
    
    // Check if menu list is visible
    const menuList = await page.$('.navigation.verticalmenu .togge-menu');
    const isVisible = await menuList?.isVisible().catch(() => false);
    console.log('Menu list visible:', isVisible);
    
    // Hover on first parent category
    const parentItems = await page.$$('.navigation.verticalmenu .ui-menu-item.level0');
    console.log('Level0 items found:', parentItems.length);
    
    if (parentItems.length > 0) {
      await parentItems[0].hover();
      await page.waitForTimeout(700);
      await page.screenshot({ path: '/tmp/vm-03-cat-hover.png', fullPage: false });
      console.log('Screenshot 3: first category hover');
      
      // Check if submenu appeared
      const submenu = await page.$('.navigation.verticalmenu .ui-menu-item.level0 .submenu');
      const submenuVisible = await submenu?.isVisible().catch(() => false);
      console.log('Submenu visible:', submenuVisible);
    }
  }
});
