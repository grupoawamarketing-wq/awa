const { firefox } = require('playwright');

const PAGES = [
  { name: 'home', url: 'https://awamotos.com/' },
  { name: 'category', url: 'https://awamotos.com/pecas-de-moto/bagageiros.html' },
  { name: 'pdp', url: 'https://awamotos.com/bagageiro-cg-160-titan-fan-start-20-a-24-macaco.html' },
  { name: 'search', url: 'https://awamotos.com/catalogsearch/result/?q=bagageiro' },
  { name: 'cart', url: 'https://awamotos.com/checkout/cart/' },
  { name: 'login', url: 'https://awamotos.com/customer/account/login/' },
  { name: 'b2b-landing', url: 'https://awamotos.com/b2b' },
];

const DIR = '/tmp/audit-screenshots';

(async () => {
  const browser = await firefox.launch({ headless: true });
  
  for (const vp of [
    { name: 'desktop', width: 1366, height: 900 },
    { name: 'mobile', width: 390, height: 844 },
  ]) {
    const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
    const page = await ctx.newPage();
    
    for (const p of PAGES) {
      try {
        await page.goto(p.url, { waitUntil: 'networkidle', timeout: 90000 });
        await page.waitForTimeout(2000);
        await page.screenshot({ path: `${DIR}/${vp.name}-${p.name}.png`, fullPage: true });
        console.log('OK: ' + vp.name + '-' + p.name);
      } catch(e) {
        console.log('FAIL: ' + vp.name + '-' + p.name + ': ' + e.message.slice(0,100));
      }
    }
    await ctx.close();
  }
  
  await browser.close();
  console.log('Done. Screenshots in ' + DIR);
})();
