import { chromium } from 'playwright';

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });

  let success = false;
  for (let i = 0; i < 5; i++) {
    const response = await page.goto('https://awamotos.com/', { waitUntil: 'networkidle' });
    if (response.status() === 200) {
      success = true;
      break;
    }
    console.log(`Status ${response.status()}, retrying in 5s...`);
    await page.waitForTimeout(5000);
  }

  if (success) {
    await page.waitForTimeout(4000);
    await page.screenshot({ path: '/tmp/homepage-test-final.png' });
    console.log('Saved final screenshot');
  } else {
    console.log('Failed to load homepage');
  }

  await browser.close();
})();
