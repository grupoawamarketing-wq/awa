const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto('https://awamotos.com/');
    
    // Wait for the header to load
    await page.waitForSelector('.awa-site-header');

    const flexRules = await page.evaluate(() => {
        const el = document.querySelector('.awa-header-account-prompt');
        if (!el) return null;
        
        // Get matched CSS rules
        const sheets = document.styleSheets;
        let result = [];
        for (let i in sheets) {
            let rules;
            try {
                rules = sheets[i].rules || sheets[i].cssRules;
            } catch (e) { continue; }
            if (!rules) continue;
            for (let r in rules) {
                if (el.matches && rules[r].selectorText && el.matches(rules[r].selectorText)) {
                    if (rules[r].style && rules[r].style.flexDirection) {
                        result.push({
                            selector: rules[r].selectorText,
                            flexDirection: rules[r].style.flexDirection,
                            sheet: sheets[i].href
                        });
                    }
                }
            }
        }
        return result;
    });

    console.log(flexRules);

    const maxWidthRules = await page.evaluate(() => {
        const el = document.querySelector('.awa-header-account-prompt');
        if (!el) return null;
        
        const sheets = document.styleSheets;
        let result = [];
        for (let i in sheets) {
            let rules;
            try {
                rules = sheets[i].rules || sheets[i].cssRules;
            } catch (e) { continue; }
            if (!rules) continue;
            for (let r in rules) {
                if (el.matches && rules[r].selectorText && el.matches(rules[r].selectorText)) {
                    if (rules[r].style && rules[r].style.maxWidth) {
                        result.push({
                            selector: rules[r].selectorText,
                            maxWidth: rules[r].style.maxWidth,
                            sheet: sheets[i].href
                        });
                    }
                }
            }
        }
        return result;
    });

    console.log(maxWidthRules);

    await browser.close();
})();