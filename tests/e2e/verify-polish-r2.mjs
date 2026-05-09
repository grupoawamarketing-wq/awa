import pkg from '@playwright/test';
const { firefox } = pkg;

const browser = await firefox.launch({
  executablePath: '/home/deploy/.cache/ms-playwright/firefox-1511/firefox/firefox',
  headless: true,
});

async function checkPage(url, name, vp = { width: 1440, height: 900 }) {
  const ctx = await browser.newContext({ viewport: vp });
  const page = await ctx.newPage();
  
  try {
    await Promise.race([
      page.goto(url, { waitUntil: 'domcontentloaded', timeout: 55000 }),
      new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), 50000))
    ]);
    await page.waitForTimeout(2500);
    
    const checks = await page.evaluate(() => {
      const sw = (sel, prop) => {
        const el = document.querySelector(sel);
        return el ? getComputedStyle(el)[prop] : null;
      };
      
      // Check footer border
      const footerBorder = sw('.page-footer', 'borderTop');
      const footerBorderColor = sw('.page-footer', 'borderTopColor');
      const footerBorderWidth = sw('.page-footer', 'borderTopWidth');
      
      // Check product card shadow
      const cardShadowItem = sw('.product-item', 'boxShadow');
      const cardShadowAlt = sw('.item-product', 'boxShadow');
      const cardBorder = sw('.product-item', 'borderColor');
      
      // Check breadcrumb
      const breadcrumbPad = sw('.breadcrumbs', 'padding');
      
      // Check inputs
      const inputBorder = sw('input[type="text"]', 'borderColor') || 
                          sw('input[type="search"]', 'borderColor');
      
      // Check if polish CSS is loaded
      const polishLoaded = Array.from(document.styleSheets).some(ss => 
        ss.href && ss.href.includes('awa-visual-polish-r2')
      );
      
      return {
        footerBorder, footerBorderColor, footerBorderWidth,
        cardShadowItem, cardShadowAlt, cardBorder,
        breadcrumbPad, inputBorder, polishLoaded,
      };
    });
    
    const footerOK = checks.footerBorderWidth === '1px' && 
                      !checks.footerBorderColor?.includes('183, 51, 55');
    const cardHasShadow = checks.cardShadowItem !== 'none' && checks.cardShadowItem !== null;
    
    console.log(`\n=== ${name.toUpperCase()} (${vp.width}px) ===`);
    console.log(`Polish CSS loaded: ${checks.polishLoaded ? '✅' : '❌'}`);
    console.log(`Footer border: ${footerOK ? '✅' : '❌'} width=${checks.footerBorderWidth} color=${checks.footerBorderColor}`);
    console.log(`Card shadow (.product-item): ${cardHasShadow ? '✅' : '⚠️ none'} = "${checks.cardShadowItem?.substring(0,50)}"`);
    console.log(`Card shadow (.item-product): "${checks.cardShadowAlt?.substring(0,50) || 'null'}"`);
    console.log(`Card border color: ${checks.cardBorder}`);
    if (checks.breadcrumbPad) console.log(`Breadcrumb pad: ${checks.breadcrumbPad}`);
    if (checks.inputBorder) console.log(`Input border: ${checks.inputBorder}`);
    
    await ctx.close();
    return { footerOK, cardHasShadow, polishLoaded: checks.polishLoaded };
  } catch(e) {
    await ctx.close();
    console.log(`${name}: ERROR - ${e.message}`);
    return { error: true };
  }
}

const pages = [
  { url: 'https://awamotos.com/', name: 'home' },
  { url: 'https://awamotos.com/barras-de-guidao.html', name: 'category' },
  { url: 'https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html', name: 'pdp' },
];

let allOK = true;
for (const p of pages) {
  const r = await checkPage(p.url, p.name);
  if (r.error || !r.polishLoaded || !r.footerOK) allOK = false;
  
  // Also check mobile
  if (p.name === 'home') {
    await checkPage(p.url, `${p.name}-mobile`, { width: 375, height: 812 });
  }
}

await browser.close();
console.log(`\n${allOK ? '🎉 ALL CHECKS PASSED' : '⚠️  SOME CHECKS FAILED'}`);
