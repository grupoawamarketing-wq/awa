import puppeteer from 'puppeteer';
import fs from 'fs';

const OUT = '/tmp/awa-shots';
fs.mkdirSync(OUT, { recursive: true });

const targets = [
  { name: 'home', url: 'https://awamotos.com/' },
];
// allow extra urls via argv
for (let i = 2; i < process.argv.length; i++) {
  const a = process.argv[i];
  const [name, url] = a.split('::');
  targets.push({ name, url });
}

const viewports = [
  { label: 'desktop', width: 1440, height: 900 },
  { label: 'wide', width: 1920, height: 1080 },
  { label: 'tablet', width: 768, height: 1024 },
  { label: 'mobile', width: 390, height: 844 },
];

const browser = await puppeteer.launch({
  headless: 'new',
  executablePath: '/usr/bin/google-chrome-stable',
  args: ['--no-sandbox', '--disable-setuid-sandbox', '--hide-scrollbars'],
});

for (const t of targets) {
  for (const v of viewports) {
    const page = await browser.newPage();
    await page.setViewport({ width: v.width, height: v.height, deviceScaleFactor: 1 });
    let diag = {};
    try {
      await page.goto(t.url, { waitUntil: 'networkidle2', timeout: 60000 });
      // give sliders/lazy content a beat
      await new Promise(r => setTimeout(r, 2500));
      // scroll to bottom to trigger lazy load, then back to top
      await page.evaluate(async () => {
        await new Promise(res => {
          let y = 0; const step = () => { window.scrollBy(0, 800); y += 800;
            if (y < document.body.scrollHeight) setTimeout(step, 60); else res(); };
          step();
        });
      });
      await new Promise(r => setTimeout(r, 1200));
      // de-lazy: force all images eager and wait for them so screenshots reflect real rendered state
      await page.evaluate(() => {
        document.querySelectorAll('img').forEach(i => { i.loading = 'eager'; if (i.dataset && i.dataset.src && !i.src.includes(i.dataset.src)) i.src = i.dataset.src; });
      });
      await page.evaluate(async () => {
        await Promise.all([...document.images].filter(i => !i.complete).map(i => new Promise(res => { i.onload = i.onerror = res; setTimeout(res, 4000); })));
      });
      await page.evaluate(() => window.scrollTo(0, 0));
      await new Promise(r => setTimeout(r, 900));

      diag = await page.evaluate(() => {
        const docW = document.documentElement.clientWidth;
        const overflowers = [];
        document.querySelectorAll('body *').forEach(el => {
          const r = el.getBoundingClientRect();
          if (r.width > 0 && (r.right > docW + 2 || r.left < -2)) {
            // only report sizeable offenders
            if (r.width > 30 && r.height > 8) {
              overflowers.push({
                tag: el.tagName.toLowerCase(),
                cls: (el.className && el.className.toString) ? el.className.toString().slice(0,80) : '',
                right: Math.round(r.right), left: Math.round(r.left), w: Math.round(r.width),
              });
            }
          }
        });
        // dedup-ish: cap
        const sheets = [...document.querySelectorAll('link[rel=stylesheet]')].map(l => l.href.split('/').pop());
        return {
          scrollW: document.documentElement.scrollWidth,
          clientW: docW,
          hasHScroll: document.documentElement.scrollWidth > docW + 1,
          overflowCount: overflowers.length,
          overflowers: overflowers.slice(0, 25),
          sheets,
          title: document.title,
        };
      });
    } catch (e) {
      diag = { error: String(e) };
    }
    const base = `${OUT}/${t.name}-${v.label}`;
    try {
      await page.screenshot({ path: `${base}.png`, fullPage: v.label === 'desktop' || v.label === 'mobile' });
    } catch (e) { diag.shotError = String(e); }
    fs.writeFileSync(`${base}.json`, JSON.stringify(diag, null, 2));
    console.log(`[${t.name}/${v.label}] hscroll=${diag.hasHScroll} overflow=${diag.overflowCount} title=${(diag.title||'').slice(0,40)} ${diag.error||''}`);
    await page.close();
  }
}

await browser.close();
console.log('DONE');
