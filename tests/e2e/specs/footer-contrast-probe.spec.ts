import { test } from '@playwright/test';

test('footer contrast probe', async ({ page }) => {
  await page.goto('https://awamotos.com/', { waitUntil: 'networkidle', timeout: 60000 });
  const r = await page.evaluate(() => {
    const out: any = { css: '', items: [] };
    const link: any = document.querySelector('[href*="awa-align-grid-terminal"]');
    out.css = link ? link.href : 'not found';
    const checks: [string, string][] = [
      ['h2.awa-newsletter-title', 'newsletter-h2'],
      ['.awa-newsletter-title', 'newsletter'],
      ['.awa-footer-atendimento__label', 'atendimento-label'],
    ];
    checks.forEach(([sel, lab]) => {
      const el = document.querySelector(sel);
      if (!el) { out.items.push({ lab, found: false }); return; }
      const cs = getComputedStyle(el);
      out.items.push({ lab, found: true, color: cs.color, bg: cs.backgroundColor, text: el.textContent?.trim().slice(0, 30) });
    });
    return out;
  });
  console.log('\nFOOTER PROBE:', JSON.stringify(r, null, 2));
});
