const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 } // Mobile
  });
  const page = await context.newPage();
  
  await page.goto('https://awamotos.com/');
  await page.waitForTimeout(5000); // Wait for load

  // Check layout issues
  const bodyWidth = await page.evaluate(() => document.body.scrollWidth);
  const viewportWidth = await page.evaluate(() => window.innerWidth);
  
  console.log(`Body Width: ${bodyWidth}, Viewport Width: ${viewportWidth}`);
  if (bodyWidth > viewportWidth) {
    console.log("HORIZONTAL OVERFLOW DETECTED!");
  }
  
  // Find specific overflowing elements
  const overflowingElements = await page.evaluate(() => {
    const elements = document.querySelectorAll('*');
    const overflowing = [];
    elements.forEach(el => {
      const rect = el.getBoundingClientRect();
      if (rect.right > window.innerWidth || rect.left < 0) {
        overflowing.push({
          tag: el.tagName,
          className: el.className,
          id: el.id,
          right: rect.right,
          left: rect.left
        });
      }
    });
    return overflowing;
  });
  
  console.log("Overflowing Elements:");
  console.log(JSON.stringify(overflowingElements.slice(0, 10), null, 2));

  // Screenshot for artifacts if possible
  await page.screenshot({ path: '/home/deploy/.gemini/antigravity/artifacts/awamotos-mobile.png' });
  console.log("Saved screenshot to artifacts/awamotos-mobile.png");

  await browser.close();
})();
