const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('https://awamotos.com', { waitUntil: 'networkidle' });
  const client = await page.context().newCDPSession(page);
  await client.send('DOM.enable');
  await client.send('CSS.enable');
  
  const { root: { nodeId } } = await client.send('DOM.getDocument');
  const { nodeId: menuNodeId } = await client.send('DOM.querySelector', { nodeId, selector: 'ul.togge-menu.list-category-dropdown' });
  
  const { matchedCSSRules } = await client.send('CSS.getMatchedStylesForNode', { nodeId: menuNodeId });
  console.log("Matched CSS Rules for ul:");
  for (const rule of matchedCSSRules) {
    console.log(rule.rule.selectorList.text);
    for (const prop of rule.rule.style.cssProperties) {
      if (prop.name === 'display' || prop.name === 'opacity' || prop.name === 'visibility') {
        console.log(`  ${prop.name}: ${prop.value}`);
      }
    }
  }

  await browser.close();
})();
