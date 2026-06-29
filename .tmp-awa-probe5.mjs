import puppeteer from 'puppeteer';
const browser = await puppeteer.launch({ headless: 'new', executablePath: '/usr/bin/google-chrome-stable', args: ['--no-sandbox','--disable-setuid-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 768, height: 1024, deviceScaleFactor: 1 });
await page.goto('https://awamotos.com/', { waitUntil: 'networkidle2', timeout: 60000 });
await new Promise(r => setTimeout(r, 3500));

const out = await page.evaluate(() => {
  // 1) can we actually scroll horizontally?
  const htmlOX = getComputedStyle(document.documentElement).overflowX;
  const bodyOX = getComputedStyle(document.body).overflowX;
  window.scrollTo(200, 0);
  const scrolledX = window.scrollX;
  window.scrollTo(0, 0);

  // 2) circular elements anywhere in body (outline OR fill), small, near top region
  const circles = [];
  document.querySelectorAll('body *').forEach(e => {
    const c = getComputedStyle(e); const r = e.getBoundingClientRect();
    if (r.width < 6 || r.width > 36 || Math.abs(r.width - r.height) > 6) return;
    const br = parseFloat(c.borderTopLeftRadius) || 0;
    const round = c.borderRadius === '50%' || br >= Math.min(r.width, r.height) / 2 - 2;
    if (!round) return;
    const hasBorder = c.borderStyle !== 'none' && parseFloat(c.borderTopWidth) > 0;
    const hasBg = c.backgroundColor && c.backgroundColor !== 'rgba(0, 0, 0, 0)';
    const hasShadow = c.boxShadow && c.boxShadow !== 'none';
    if (!(hasBorder || hasBg || hasShadow)) return;
    circles.push({ tag: e.tagName.toLowerCase(), cls: (e.className||'').toString().slice(0,55), id: e.id||'', w: Math.round(r.width), h: Math.round(r.height), top: Math.round(r.top), left: Math.round(r.left), bg: c.backgroundColor, border: parseFloat(c.borderTopWidth)+'px '+c.borderStyle+' '+c.borderTopColor, parent: e.parentElement ? (e.parentElement.className||e.parentElement.tagName).toString().slice(0,40) : '' });
  });
  return { htmlOX, bodyOX, scrolledX, docScrollW: document.documentElement.scrollWidth, clientW: document.documentElement.clientWidth, circles: circles.slice(0, 20) };
});
console.log(JSON.stringify(out, null, 2));
await browser.close();
