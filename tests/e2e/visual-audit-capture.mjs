import pkg from '@playwright/test';
import { mkdirSync } from 'fs';

const { chromium, firefox } = pkg;
const OUT = '/tmp/visual-audit-2026/screenshots';
mkdirSync(OUT, { recursive: true });

const PAGES = [
  { name: 'home', url: 'https://awamotos.com/' },
  { name: 'category', url: 'https://awamotos.com/barras-de-guidao.html' },
  { name: 'pdp', url: 'https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html' },
  { name: 'search', url: 'https://awamotos.com/catalogsearch/result/?q=bagageiro' },
];
const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'tablet', width: 768, height: 1024 },
  { name: 'mobile', width: 375, height: 812 },
];

const browser = await firefox.launch({
  executablePath: '/home/deploy/.cache/ms-playwright/firefox-1511/firefox/firefox',
  headless: true,
});

for (const vp of VIEWPORTS) {
  const ctx = await browser.newContext({
    viewport: { width: vp.width, height: vp.height },
  });
  const page = await ctx.newPage();
  
  for (const pg of PAGES) {
    try {
      console.log(`Capturing ${vp.name} - ${pg.name}...`);
      await Promise.race([
        page.goto(pg.url, { waitUntil: 'domcontentloaded', timeout: 60000 }),
        new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 55000))
      ]);
      await page.waitForTimeout(2000);
      const fname = `${OUT}/${vp.name}-${pg.name}.png`;
      await page.screenshot({ path: fname, fullPage: true });
      console.log(`  ✅ ${fname}`);
    } catch(e) {
      console.log(`  ❌ ${vp.name}-${pg.name}: ${e.message}`);
    }
  }
  await ctx.close();
}

await browser.close();
console.log('\nDone!');
