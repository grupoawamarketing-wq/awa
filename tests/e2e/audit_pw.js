const { chromium } = require('playwright');
(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto('https://awamotos.com/bagageiro-biz-100-modelo-98-05-preto-macico-2008.html', { waitUntil: 'networkidle' });

    const bugs = await page.evaluate(() => {
        const issues = [];
        
        document.querySelectorAll('img:not([alt]), img[alt=""]').forEach(el => {
            issues.push('Image sem atributo alt: ' + (el.src || el.outerHTML.substring(0, 50)));
        });

        document.querySelectorAll('a:empty').forEach(el => {
            if (!el.getAttribute('aria-label') && !el.getAttribute('title')) {
                issues.push('Link vazio sem aria-label/title: ' + (el.href || el.outerHTML.substring(0, 50)));
            }
        });

        const ids = Array.from(document.querySelectorAll('[id]')).map(el => el.id);
        const duplicates = ids.filter((item, index) => ids.indexOf(item) !== index);
        [...new Set(duplicates)].forEach(id => {
            if (id) issues.push('ID duplicado na página: #' + id);
        });

        document.querySelectorAll('input:not([type="submit"]):not([type="hidden"]), select, textarea').forEach(el => {
            if (el.id && !document.querySelector('label[for="' + el.id + '"]') && !el.getAttribute('aria-label')) {
                issues.push('Input sem label associado: ' + el.outerHTML.substring(0, 50));
            }
        });

        document.querySelectorAll('[style*="color"], [style*="background"], [style*="font"]').forEach(el => {
            issues.push('Uso de estilo inline (anti-pattern no Magento): ' + el.outerHTML.substring(0, 50));
        });

        document.querySelectorAll('font, center, b, i, u, strike').forEach(el => {
            issues.push('Uso de tag HTML obsoleta (' + el.tagName + '): ' + el.outerHTML.substring(0, 50));
        });

        document.querySelectorAll('button:not([type])').forEach(el => {
            issues.push('Botão sem atributo type: ' + el.outerHTML.substring(0, 50));
        });

        document.querySelectorAll('a[href="#"]').forEach(el => {
            issues.push('Link apontando para # (pode causar pulo de página): ' + el.outerHTML.substring(0, 50));
        });

        document.querySelectorAll('a[target="_blank"]:not([rel*="noopener"])').forEach(el => {
            issues.push('Link target="_blank" sem rel="noopener": ' + el.href);
        });

        const h1s = document.querySelectorAll('h1');
        if (h1s.length > 1) {
            issues.push('Múltiplas tags H1 encontradas na página (' + h1s.length + ')');
        } else if (h1s.length === 0) {
            issues.push('Nenhuma tag H1 encontrada na página');
        }

        // Headings order check (basic)
        let lastLevel = 0;
        document.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(el => {
            const level = parseInt(el.tagName.replace('H', ''));
            if (lastLevel > 0 && level - lastLevel > 1) {
                issues.push('Salto de hierarquia de heading: de H' + lastLevel + ' para H' + level + ' (' + el.textContent.trim().substring(0,20) + ')');
            }
            lastLevel = level;
        });

        // check for hardcoded hex colors in css classes vs variables
        const styles = Array.from(document.styleSheets);
        // This is restricted by CORS so we might skip

        return issues;
    });

    console.log(JSON.stringify(bugs, null, 2));
    await browser.close();
})();
