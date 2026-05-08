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
  for (const rule of matchedCSSRules) {
    let hasNone = false;
    for (const prop of rule.rule.style.cssProperties) {
      if (prop.name === 'display' && prop.value.includes('none')) hasNone = true;
    }
    if (hasNone) {
      console.log("MATCHED SELECTOR:", rule.rule.selectorList.text);
      if (rule.rule.origin === 'regular') {
        const header = rule.rule.style.styleSheetId ? await client.send('CSS.getStyleSheetText', { styleSheetId: rule.rule.style.styleSheetId }) : null;
        if (header) console.log("TEXT EXTRACT:", header.text.substring(0, 200));
      }
    }
  }

  await browser.close();
})();
