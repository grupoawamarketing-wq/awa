const { chromium } = require('@playwright/test');

async function autoScroll(page){
    await page.evaluate(async () => {
        await new Promise((resolve) => {
            let totalHeight = 0;
            let distance = 100;
            let timer = setInterval(() => {
                let scrollHeight = document.body.scrollHeight;
                window.scrollBy(0, distance);
                totalHeight += distance;

                if(totalHeight >= scrollHeight){
                    clearInterval(timer);
                    resolve();
                }
            }, 100);
        });
    });
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  
  // Desktop
  const desktopContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const desktopPage = await desktopContext.newPage();
  await desktopPage.goto('https://awamotos.com/', { waitUntil: 'networkidle' });
  await autoScroll(desktopPage);
  await desktopPage.screenshot({ path: 'awamotos_desktop.png', fullPage: true });
  await desktopContext.close();

  // Mobile
  const mobileContext = await browser.newContext({ viewport: { width: 375, height: 812 }, isMobile: true, hasTouch: true });
  const mobilePage = await mobileContext.newPage();
  await mobilePage.goto('https://awamotos.com/', { waitUntil: 'networkidle' });
  await autoScroll(mobilePage);
  await mobilePage.screenshot({ path: 'awamotos_mobile.png', fullPage: true });
  await mobileContext.close();

  await browser.close();
})();
