const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto('https://awamotos.com/');
    
    // Wait for the header to load
    await page.waitForSelector('.awa-site-header');

    const rules = await page.evaluate(() => {
        const el = document.querySelector('.awa-header-account-prompt');
        if (!el) return null;
        
        let result = [];
        
        function processRules(rulesList, sheetHref, media = '') {
            if (!rulesList) return;
            for (let i = 0; i < rulesList.length; i++) {
                const rule = rulesList[i];
                if (rule.type === CSSRule.STYLE_RULE) {
                    if (el.matches && rule.selectorText && el.matches(rule.selectorText)) {
                        if (rule.style.flexDirection) {
                            result.push({
                                selector: rule.selectorText,
                                flexDirection: rule.style.flexDirection,
                                media: media,
                                sheet: sheetHref
                            });
                        }
                    }
                } else if (rule.type === CSSRule.MEDIA_RULE) {
                    processRules(rule.cssRules, sheetHref, rule.media.mediaText);
                }
            }
        }
        
        const sheets = document.styleSheets;
        for (let i = 0; i < sheets.length; i++) {
            try {
                processRules(sheets[i].cssRules, sheets[i].href);
            } catch (e) { continue; }
        }
        return result;
    });

    console.log(rules);
    await browser.close();
})();