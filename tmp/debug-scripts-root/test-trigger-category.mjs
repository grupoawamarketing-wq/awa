import { chromium } from 'playwright';
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/pecas.html');
  await page.waitForTimeout(5000);
  const classesBefore = await page.evaluate(() => document.querySelector('[data-role="awa-vertical-menu-trigger"]').className);
  
  await page.click('[data-role="awa-vertical-menu-trigger"]');
  await page.waitForTimeout(1000);
  
  const classesAfter = await page.evaluate(() => document.querySelector('[data-role="awa-vertical-menu-trigger"]').className);
  
  console.log("Category Page:");
  console.log("Trigger Before:", classesBefore);
  console.log("Trigger After:", classesAfter);
  
  await browser.close();
})();
