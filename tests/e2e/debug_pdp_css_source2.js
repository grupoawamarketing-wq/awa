const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1440, height: 900 });
    
    await page.goto('https://awamotos.com/protetor-de-carter-xre-190-mod-2016-2022-preto-fosco-3098.html', { waitUntil: 'networkidle' });
    
    const info = await page.evaluate(() => {
        const tabs = document.querySelector('.product.data.items');
        if (!tabs) return null;
        
        // Find which stylesheet applies the height
        const rules = [];
        for (let i = 0; i < document.styleSheets.length; i++) {
            const sheet = document.styleSheets[i];
            try {
                for (let j = 0; j < sheet.cssRules.length; j++) {
                    const rule = sheet.cssRules[j];
                    if (rule.selectorText && tabs.matches(rule.selectorText) && rule.style.height) {
                        rules.push({
                            selector: rule.selectorText,
                            height: rule.style.height,
                            href: sheet.href
                        });
                    }
                }
            } catch (e) {}
        }
        return rules;
    });
    
    console.log(info);
    await browser.close();
})();
