import { chromium } from 'playwright';
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(5000);
  const classesBefore = await page.evaluate(() => document.querySelector('[data-role="awa-vertical-menu-trigger"]').className);
  const parentClassesBefore = await page.evaluate(() => document.querySelector('#menu\\.vertical').className);
  
  await page.click('[data-role="awa-vertical-menu-trigger"]');
  await page.waitForTimeout(1000);
  
  const classesAfter = await page.evaluate(() => document.querySelector('[data-role="awa-vertical-menu-trigger"]').className);
  const parentClassesAfter = await page.evaluate(() => document.querySelector('#menu\\.vertical').className);
  const menuClassesAfter = await page.evaluate(() => document.querySelector('nav[data-role="awa-vertical-menu"] > ul').className);
  
  console.log("Trigger Before:", classesBefore);
  console.log("Trigger After:", classesAfter);
  console.log("Parent Before:", parentClassesBefore);
  console.log("Parent After:", parentClassesAfter);
  console.log("Menu After:", menuClassesAfter);
  
  await browser.close();
})();
