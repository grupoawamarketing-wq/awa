import puppeteer from 'puppeteer';
const browser = await puppeteer.launch({ headless: 'new', executablePath: '/usr/bin/google-chrome-stable', args: ['--no-sandbox','--disable-setuid-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 900 });
const client = await page.target().createCDPSession();
await client.send('DOM.enable'); await client.send('CSS.enable');
await page.goto('https://awamotos.com/', { waitUntil: 'networkidle2', timeout: 60000 });
await new Promise(r => setTimeout(r, 2500));
const { root } = await client.send('DOM.getDocument', { depth: -1 });
const { nodeId } = await client.send('DOM.querySelector', { nodeId: root.nodeId, selector: '.counter.qty.empty' });
if (!nodeId) { console.log('no empty counter node'); await browser.close(); process.exit(0); }
const matched = await client.send('CSS.getMatchedStylesForNode', { nodeId });
const rules = [];
for (const m of (matched.matchedCSSRules||[])) {
  const r = m.rule;
  const props = (r.style.cssProperties||[]).filter(p => /display|visibility|opacity/.test(p.name));
  if (props.length) {
    rules.push({ sel: r.selectorList.text.slice(0,90), props: props.map(p=>p.name+':'+p.value+(p.important?' !imp':'')), origin: r.origin });
  }
}
// inline
const inl = matched.inlineStyle?.cssProperties?.filter(p=>/display|visibility|opacity/.test(p.name)).map(p=>p.name+':'+p.value);
console.log(JSON.stringify({ rules, inline: inl }, null, 2));
await browser.close();
