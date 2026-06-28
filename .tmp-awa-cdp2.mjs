import puppeteer from 'puppeteer';
const browser = await puppeteer.launch({ headless: 'new', executablePath: '/usr/bin/google-chrome-stable', args: ['--no-sandbox','--disable-setuid-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 900 });
const client = await page.target().createCDPSession();
await client.send('DOM.enable'); await client.send('CSS.enable');
await page.goto('https://awamotos.com/bauletos.html', { waitUntil: 'networkidle2', timeout: 60000 });
await new Promise(r => setTimeout(r, 2500));
const { root } = await client.send('DOM.getDocument', { depth: -1 });

async function rulesFor(sel, propRe) {
  const { nodeId } = await client.send('DOM.querySelector', { nodeId: root.nodeId, selector: sel });
  if (!nodeId) return { sel, error: 'node not found' };
  const matched = await client.send('CSS.getMatchedStylesForNode', { nodeId });
  const out = [];
  for (const m of (matched.matchedCSSRules || [])) {
    const r = m.rule;
    const props = (r.style.cssProperties || []).filter(p => propRe.test(p.name) && p.value);
    if (props.length) out.push({ sel: r.selectorList.text.slice(0,120), props: props.map(p => p.name+':'+p.value+(p.important?'!imp':'')) });
  }
  return { sel, rules: out };
}
console.log('CONTENT BG:', JSON.stringify(await rulesFor('.awa-category-hero__content', /^background(-color)?$/), null, 2));
console.log('TITLE COLOR:', JSON.stringify(await rulesFor('.awa-category-hero__title', /^color$/), null, 2));
await browser.close();
