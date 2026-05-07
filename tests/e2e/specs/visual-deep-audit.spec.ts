import { test, expect, type Page } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const BASE_URL = 'https://awamotos.com';
const ROOT_DIR = path.resolve(__dirname, '..');
const SCREENSHOTS_DIR = path.join(ROOT_DIR, 'screenshots', 'deep-audit');
const REPORT_DIR = path.join(ROOT_DIR, 'reports', 'deep-audit');

interface AuditTarget {
  slug: string;
  url: string;
  label: string;
  fullScroll?: boolean;
}

const TARGETS: AuditTarget[] = [
  { slug: 'home',             url: `${BASE_URL}/`,                                            label: 'Homepage',         fullScroll: true },
  { slug: 'plp-guidoes',      url: `${BASE_URL}/guidoes.html`,                                label: 'PLP Guidoes',      fullScroll: true },
  { slug: 'plp-bagageiros',   url: `${BASE_URL}/bagageiros.html`,                             label: 'PLP Bagageiros',   fullScroll: true },
  { slug: 'plp-retrovisores', url: `${BASE_URL}/retrovisores.html`,                           label: 'PLP Retrovisores', fullScroll: true },
  { slug: 'plp-baus',         url: `${BASE_URL}/baus.html`,                                   label: 'PLP Baus',         fullScroll: true },
  { slug: 'pdp-ret-biz',      url: `${BASE_URL}/ret-biz-100-cr-redondo-universal-2220.html`,  label: 'PDP Ret BIZ',      fullScroll: true },
  { slug: 'search-bagageiro', url: `${BASE_URL}/catalogsearch/result/?q=bagageiro`,            label: 'Search Bagageiro' },
  { slug: 'search-retrovisor',url: `${BASE_URL}/catalogsearch/result/?q=retrovisor`,           label: 'Search Retrovisor' },
  { slug: 'login',            url: `${BASE_URL}/customer/account/login/`,                      label: 'Login Page' },
  { slug: 'register',         url: `${BASE_URL}/customer/account/create/`,                     label: 'Register Page' },
  { slug: 'forgot-password',  url: `${BASE_URL}/customer/account/forgotpassword/`,             label: 'Forgot Password' },
  { slug: 'cart-empty',       url: `${BASE_URL}/checkout/cart/`,                               label: 'Empty Cart' },
  { slug: 'b2b-landing',      url: `${BASE_URL}/seja-cliente-b2b`,                             label: 'B2B Landing',      fullScroll: true },
  { slug: '404-page',         url: `${BASE_URL}/nonexistent-page-test-404.html`,               label: '404 Page' },
  { slug: 'contact',          url: `${BASE_URL}/contato`,                                      label: 'Contato' },
];

type IssueSeverity = 'critical' | 'major' | 'minor';

interface VisualIssue {
  page: string;
  pageUrl: string;
  category: string;
  description: string;
  severity: IssueSeverity;
  selector: string;
  computed: Record<string, string>;
  probableCause: string;
  recommendedFix: string;
  screenshotPath: string;
  viewport: string;
}

interface RawIssue {
  category: string;
  description: string;
  severity: IssueSeverity;
  selector: string;
  computed: Record<string, string>;
  probableCause: string;
  recommendedFix: string;
}

async function dismissCookieBanner(page: Page): Promise<void> {
  const selectors = '#awa-cookie-accept, .awa-cookie-banner__btn--accept, .cookie-btn-accept, #btn-cookie-allow';
  const btn = page.locator(selectors).first();
  const vis = await btn.isVisible({ timeout: 2_000 }).catch(() => false);
  if (vis) {
    await btn.click({ force: true }).catch(() => {});
    await page.waitForTimeout(300);
  }
}

async function stabilizePage(page: Page): Promise<void> {
  await page.evaluate(() => {
    const s = document.createElement('style');
    s.id = 'deep-audit-freeze';
    s.textContent = '*, *::before, *::after { animation-play-state: paused !important; animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; } .link-on-bottom, .awa-whatsapp-float, a.awa-whatsapp-float, [class*="link-on-bottom"], [class*="whatsapp-float"], .fake-purchase-notification, .fake-purchase, [class*="social-proof"], [data-social-proof] { visibility: hidden !important; opacity: 0 !important; }';
    document.head.appendChild(s);
    document.querySelectorAll<HTMLElement>('.owl-carousel .owl-item').forEach((item, i) => {
      if (i > 0 && !item.classList.contains('cloned')) {
        item.style.setProperty('opacity', '0', 'important');
      }
    });
  }).catch(() => {});
  await page.waitForTimeout(500);
}

async function scrollFullPage(page: Page): Promise<void> {
  await page.evaluate(async () => {
    const delay = (ms: number) => new Promise(r => setTimeout(r, ms));
    const totalHeight = document.body.scrollHeight;
    const step = Math.round(window.innerHeight * 0.7);
    for (let y = 0; y < totalHeight; y += step) {
      window.scrollTo(0, y);
      await delay(200);
    }
    window.scrollTo(0, 0);
    await delay(300);
  }).catch(() => {});
}

async function runDeepVisualChecks(page: Page): Promise<RawIssue[]> {
  return page.evaluate(() => {
    const issues: RawIssue[] = [];
    const cs = (el: Element) => window.getComputedStyle(el);
    function px(val: string): number { return parseFloat(val) || 0; }
    const vw = document.documentElement.clientWidth;

    // 1. Horizontal Overflow
    const docOverflow = document.documentElement.scrollWidth - document.documentElement.clientWidth;
    if (docOverflow > 5) {
      issues.push({
        category: 'overflow-x',
        description: 'Page has ' + Math.round(docOverflow) + 'px horizontal overflow',
        severity: 'critical',
        selector: 'html',
        computed: { scrollWidth: String(document.documentElement.scrollWidth), clientWidth: String(document.documentElement.clientWidth) },
        probableCause: 'An element exceeds the viewport width.',
        recommendedFix: 'Find overflowing element and add max-width:100% or overflow:hidden.',
      });
    }

    // 2. Duplicated Borders
    const cards = document.querySelectorAll('.product-item, .product-item-info');
    const borderSeen = new Set<string>();
    cards.forEach(card => {
      const style = cs(card);
      const b = style.borderBottomWidth;
      const parent = card.parentElement;
      if (parent) {
        const pStyle = cs(parent);
        const pb = pStyle.borderTopWidth;
        if (px(b) > 0 && px(pb) > 0 && px(b) + px(pb) > 2) {
          const key = ((card as HTMLElement).className || '').slice(0, 40) + '-dblborder';
          if (!borderSeen.has(key)) {
            borderSeen.add(key);
            issues.push({
              category: 'duplicated-borders',
              description: 'Adjacent borders: child=' + b + ', parent=' + pb,
              severity: 'minor',
              selector: '.' + ((card as HTMLElement).className || '').split(' ').filter(Boolean)[0],
              computed: { childBorderBottom: b, parentBorderTop: pb },
              probableCause: 'Both parent grid and child card define borders.',
              recommendedFix: 'Remove border from one level.',
            });
          }
        }
      }
    });

    // 3. Inconsistent Card Spacing
    const productItems = Array.from(document.querySelectorAll('.products-grid .product-item'));
    if (productItems.length > 1) {
      const margins = productItems.map(el => px(cs(el).marginBottom));
      const uniqueBottom = new Set(margins);
      if (uniqueBottom.size > 1) {
        issues.push({
          category: 'inconsistent-card-spacing',
          description: 'Product cards have ' + uniqueBottom.size + ' different margin-bottom values: ' + [...uniqueBottom].join(', ') + 'px',
          severity: 'minor',
          selector: '.products-grid .product-item',
          computed: { uniqueBottomMargins: [...uniqueBottom].join(', ') },
          probableCause: 'Multiple CSS rules apply different margins to product cards.',
          recommendedFix: 'Standardize margin-bottom on .product-item.',
        });
      }
    }

    // 4. Inconsistent Button Heights
    const buttons = Array.from(document.querySelectorAll<HTMLElement>('.action.primary, .action.tocart, button.btn-cart'));
    if (buttons.length > 1) {
      const heights = buttons.map(b => Math.round(b.getBoundingClientRect().height)).filter(h => h >= 30);
      const uniqueH = new Set(heights);
      if (uniqueH.size > 2) {
        issues.push({
          category: 'inconsistent-button-heights',
          description: 'Primary buttons have ' + uniqueH.size + ' different heights: ' + [...uniqueH].join(', ') + 'px',
          severity: 'major',
          selector: '.action.primary',
          computed: { heights: [...uniqueH].join(', ') },
          probableCause: 'Different padding/font-size on button variants.',
          recommendedFix: 'Set uniform min-height and padding on .action.primary.',
        });
      }
    }

    // 5. Elements Outside Container
    const checkOutside = document.querySelectorAll<HTMLElement>('.page-main, .page-header, .page-footer, .columns, .column.main, .top-home-content, .block.widget');
    checkOutside.forEach(el => {
      const rect = el.getBoundingClientRect();
      if (rect.width <= 0 || rect.height <= 0) return;
      if (rect.left < -5 || rect.right > vw + 5) {
        issues.push({
          category: 'elements-outside-container',
          description: 'Element ' + el.tagName + '.' + (el.className || '').split(' ')[0] + ' extends beyond viewport (left=' + Math.round(rect.left) + ', right=' + Math.round(rect.right) + ')',
          severity: 'major',
          selector: el.tagName.toLowerCase() + '.' + ((el.className || '').split(' ').filter(Boolean)[0] || ''),
          computed: { left: String(Math.round(rect.left)), right: String(Math.round(rect.right)), viewportWidth: String(vw) },
          probableCause: 'Negative margins or fixed widths exceeding container.',
          recommendedFix: 'Add max-width:100% and overflow:hidden.',
        });
      }
    });

    // 6. Broken Grid Alignment
    const gridRows = document.querySelectorAll<HTMLElement>('.products-grid .product-items, .row');
    gridRows.forEach(row => {
      const children = Array.from(row.children) as HTMLElement[];
      if (children.length < 2) return;
      const tops = children.map(c => Math.round(c.getBoundingClientRect().top));
      const rowGroups: number[][] = [];
      tops.forEach(t => {
        const existing = rowGroups.find(g => Math.abs(g[0] - t) < 5);
        if (existing) existing.push(t);
        else rowGroups.push([t]);
      });
      rowGroups.forEach(group => {
        if (group.length > 1) {
          const delta = Math.max(...group) - Math.min(...group);
          if (delta > 3) {
            issues.push({
              category: 'broken-grid-alignment',
              description: 'Grid items misaligned by ' + delta + 'px within same row',
              severity: 'minor',
              selector: row.tagName.toLowerCase() + '.' + ((row.className || '').split(' ').filter(Boolean)[0] || ''),
              computed: { delta: String(delta) },
              probableCause: 'Variable content heights or inconsistent padding.',
              recommendedFix: 'Use CSS Grid with align-items:start or equal-height card pattern.',
            });
          }
        }
      });
    });

    // 7. Excessive White Space
    const mainContent = document.querySelector('.page-main .column.main, .page-main') as HTMLElement | null;
    if (mainContent) {
      const s = cs(mainContent);
      if (px(s.paddingTop) > 80) {
        issues.push({
          category: 'excessive-whitespace',
          description: 'Main content has excessive padding-top: ' + s.paddingTop,
          severity: 'minor',
          selector: '.page-main .column.main',
          computed: { paddingTop: s.paddingTop },
          probableCause: 'Large padding-top on .page-main.',
          recommendedFix: 'Reduce padding-top to 20-40px range.',
        });
      }
    }

    // 8. Typography Inconsistencies
    const h1s = Array.from(document.querySelectorAll<HTMLElement>('h1')).filter(el => el.getBoundingClientRect().height > 0 && !el.classList.contains('awa-sr-only') && cs(el).clip !== 'rect(0px, 0px, 0px, 0px)');
    const h2s = Array.from(document.querySelectorAll<HTMLElement>('h2')).filter(el => el.getBoundingClientRect().height > 0);
    const h1Sizes = new Set(h1s.map(h => cs(h).fontSize));
    const h2Sizes = new Set(h2s.map(h => cs(h).fontSize));
    if (h1Sizes.size > 1) {
      issues.push({
        category: 'typography-inconsistency',
        description: 'H1 has ' + h1Sizes.size + ' different font-sizes: ' + [...h1Sizes].join(', '),
        severity: 'major',
        selector: 'h1',
        computed: { fontSizes: [...h1Sizes].join(', ') },
        probableCause: 'Multiple CSS rules target h1 with different specificity.',
        recommendedFix: 'Define single h1 font-size in design system tokens.',
      });
    }
    if (h2Sizes.size > 2) {
      issues.push({
        category: 'typography-inconsistency',
        description: 'H2 has ' + h2Sizes.size + ' different font-sizes: ' + [...h2Sizes].join(', '),
        severity: 'minor',
        selector: 'h2',
        computed: { fontSizes: [...h2Sizes].join(', ') },
        probableCause: 'Multiple CSS rules target h2 in different contexts.',
        recommendedFix: 'Standardize h2 sizes to max 2 variants.',
      });
    }

    // 9. Overlapping Header/Main
    const headerEls = Array.from(document.querySelectorAll<HTMLElement>('.page-header, .header.content, .panel.wrapper, .awa-site-header, header')).filter(el => el.getBoundingClientRect().height > 0);
    const pageMain = document.querySelector('.page-main') as HTMLElement | null;
    if (headerEls.length > 0 && pageMain) {
      const lastHeader = headerEls[headerEls.length - 1];
      const headerBottom = lastHeader.getBoundingClientRect().bottom;
      const mainTop = pageMain.getBoundingClientRect().top;
      if (headerBottom > mainTop + 10) {
        issues.push({
          category: 'overlapping-elements',
          description: 'Header overlaps page-main by ' + Math.round(headerBottom - mainTop) + 'px (sticky/fixed header)',
          severity: 'minor',
          selector: '.page-header -> .page-main',
          computed: { headerBottom: String(Math.round(headerBottom)), mainTop: String(Math.round(mainTop)) },
          probableCause: 'Fixed/sticky header without corresponding padding-top.',
          recommendedFix: 'Add padding-top equal to header height on .page-main.',
        });
      }
    }

    // 10. Inconsistent Container Widths
    const containers = Array.from(document.querySelectorAll<HTMLElement>('.page-main, .footer-container, .header.content, .nav-sections, .breadcrumbs')).filter(el => el.getBoundingClientRect().width > 0);
    if (containers.length > 1) {
      const widths = containers.map(c => ({ sel: (c.className || '').split(' ').filter(Boolean)[0], w: Math.round(c.getBoundingClientRect().width) }));
      const maxWidth = Math.max(...widths.map(w => w.w));
      widths.forEach(item => {
        const diff = maxWidth - item.w;
        if (diff > 60 && item.w > 400) {
          issues.push({
            category: 'inconsistent-container-sizes',
            description: '.' + item.sel + ' is ' + diff + 'px narrower than widest container (' + item.w + ' vs ' + maxWidth + 'px)',
            severity: 'minor',
            selector: '.' + item.sel,
            computed: { elementWidth: String(item.w), maxContainerWidth: String(maxWidth) },
            probableCause: 'Different max-width values on page sections.',
            recommendedFix: 'Use consistent max-width and margin:0 auto on all containers.',
          });
        }
      });
    }

    // 11. Carousel Breaking Layout
    document.querySelectorAll<HTMLElement>('.owl-carousel, .slick-slider, .swiper-container, .swiper').forEach(carousel => {
      const rect = carousel.getBoundingClientRect();
      if (rect.width > vw + 10 && rect.width > 0) {
        issues.push({
          category: 'carousel-breaking-layout',
          description: 'Carousel width (' + Math.round(rect.width) + 'px) exceeds viewport (' + vw + 'px)',
          severity: 'major',
          selector: carousel.tagName.toLowerCase() + '.' + ((carousel.className || '').split(' ').filter(Boolean)[0] || ''),
          computed: { carouselWidth: String(Math.round(rect.width)), viewportWidth: String(vw) },
          probableCause: 'Carousel not constrained by parent overflow:hidden.',
          recommendedFix: 'Add overflow:hidden to the carousel container.',
        });
      }
    });

    // 12. Inconsistent Section Padding
    const sections = Array.from(document.querySelectorAll<HTMLElement>('.block.widget, .top-home-content, section')).filter(el => el.getBoundingClientRect().height > 50);
    if (sections.length > 2) {
      const uniquePT = new Set(sections.map(el => px(cs(el).paddingTop)));
      if (uniquePT.size > 4) {
        issues.push({
          category: 'inconsistent-section-padding',
          description: uniquePT.size + ' different padding-top values across sections: ' + [...uniquePT].slice(0, 6).join(', ') + 'px',
          severity: 'minor',
          selector: '.block.widget, section',
          computed: { uniquePaddingTops: [...uniquePT].slice(0, 8).join(', ') },
          probableCause: 'Different widgets have ad-hoc padding values.',
          recommendedFix: 'Define spacing tokens and apply consistently.',
        });
      }
    }

    // 13. Misaligned Icons
    const iconContainers = Array.from(document.querySelectorAll<HTMLElement>('.action.showcart, .minicart-wrapper, .block-search .action.search')).filter(el => el.getBoundingClientRect().height > 0);
    iconContainers.forEach(container => {
      const icon = container.querySelector('svg, i, .icon, [class*="icon"], img') as HTMLElement | null;
      const text = container.querySelector('span, .text, .counter') as HTMLElement | null;
      // Skip absolutely positioned elements (e.g., notification badges) — not expected to align center
      if (text && window.getComputedStyle(text).position === 'absolute') return;
      if (icon && text) {
        const iconCenter = icon.getBoundingClientRect().top + icon.getBoundingClientRect().height / 2;
        const textCenter = text.getBoundingClientRect().top + text.getBoundingClientRect().height / 2;
        if (Math.abs(iconCenter - textCenter) > 5) {
          issues.push({
            category: 'misaligned-icons',
            description: 'Icon and text misaligned by ' + Math.round(Math.abs(iconCenter - textCenter)) + 'px',
            severity: 'minor',
            selector: '.' + (container.className || '').split(' ').filter(Boolean).join('.'),
            computed: { iconCenterY: String(Math.round(iconCenter)), textCenterY: String(Math.round(textCenter)) },
            probableCause: 'Missing vertical alignment on icon container.',
            recommendedFix: 'Apply display:flex; align-items:center on the container.',
          });
        }
      }
    });

    // 14. Visual Hierarchy
    if (h1s.length > 0 && h2s.length > 0) {
      const h1Size = px(cs(h1s[0]).fontSize);
      const h2Size = px(cs(h2s[0]).fontSize);
      if (h2Size >= h1Size && h1Size > 0) {
        issues.push({
          category: 'visual-hierarchy',
          description: 'H2 (' + h2Size + 'px) is same size or larger than H1 (' + h1Size + 'px)',
          severity: 'major',
          selector: 'h1, h2',
          computed: { h1FontSize: String(h1Size), h2FontSize: String(h2Size) },
          probableCause: 'H2 has a custom font-size overriding hierarchy.',
          recommendedFix: 'Ensure H1 > H2 > H3 font sizes.',
        });
      }
    }

    // 15. Broken Images
    Array.from(document.querySelectorAll<HTMLImageElement>('img')).forEach(img => {
      const rect = img.getBoundingClientRect();
      if (rect.width < 40 || rect.height < 40) return;
      if (img.complete && img.naturalWidth === 0 && img.getAttribute("loading") !== "lazy") {
        issues.push({
          category: 'broken-images',
          description: 'Broken image: ' + (img.src || '').slice(0, 100),
          severity: 'major',
          selector: 'img',
          computed: { src: (img.src || '').slice(0, 150) },
          probableCause: 'Image file missing from server.',
          recommendedFix: 'Verify image exists at src path.',
        });
      }
    });

    // 16. CSS Conflicts (z-index stacking)
    const fixedEls = Array.from(document.querySelectorAll<HTMLElement>('*')).filter(el => {
      const s = cs(el);
      return s.position === 'fixed' && el.getBoundingClientRect().height > 0;
    });
    if (fixedEls.length > 3) {
      const zIndexes = fixedEls.map(el => ({ cls: (el.className || '').split(' ').filter(Boolean)[0] || el.tagName, z: parseInt(cs(el).zIndex) || 0 }));
      issues.push({
        category: 'css-conflicts-zindex',
        description: fixedEls.length + ' fixed-position elements. Z-indexes: ' + zIndexes.map(z => z.cls + ':' + z.z).join(', '),
        severity: 'minor',
        selector: 'position:fixed',
        computed: Object.fromEntries(zIndexes.map(z => [z.cls, String(z.z)])),
        probableCause: 'Multiple fixed overlays competing for z-index.',
        recommendedFix: 'Define z-index scale in design tokens.',
      });
    }

    // 17. Form Input Consistency
    const inputs = Array.from(document.querySelectorAll<HTMLElement>('input[type="text"], input[type="email"], input[type="password"], select, textarea'));
    if (inputs.length > 1) {
      const inputHeights = new Set(inputs.map(i => Math.round(i.getBoundingClientRect().height)).filter(h => h > 0));
      if (inputHeights.size > 2) {
        issues.push({
          category: 'inconsistent-form-inputs',
          description: 'Form inputs have ' + inputHeights.size + ' different heights: ' + [...inputHeights].join(', ') + 'px',
          severity: 'minor',
          selector: 'input, select, textarea',
          computed: { uniqueHeights: [...inputHeights].join(', ') },
          probableCause: 'Different padding/font-size on input types.',
          recommendedFix: 'Normalize input height with consistent padding and font-size.',
        });
      }
    }

    // 18. Body Margin
    const bodyS = cs(document.body);
    if (px(bodyS.marginLeft) > 0 || px(bodyS.marginRight) > 0) {
      issues.push({
        category: 'unexpected-body-margin',
        // 8px is browser default; if CSS reset hasn't loaded yet it's transient
        description: 'Body has non-zero margins: left=' + bodyS.marginLeft + ', right=' + bodyS.marginRight,
        severity: 'minor',
        selector: 'body',
        computed: { marginLeft: bodyS.marginLeft, marginRight: bodyS.marginRight },
        probableCause: 'Browser default or theme override.',
        recommendedFix: 'Set body { margin: 0; }.',
      });
    }

    // 19. Footer Width
    const footer = document.querySelector('.page-footer, footer, .footer-container') as HTMLElement | null;
    if (footer) {
      const fRect = footer.getBoundingClientRect();
      if (Math.abs(fRect.width - vw) > 5 && fRect.width > 0) {
        issues.push({
          category: 'footer-width-mismatch',
          description: 'Footer width (' + Math.round(fRect.width) + 'px) does not match viewport (' + vw + 'px)',
          severity: 'minor',
          selector: '.page-footer',
          computed: { footerWidth: String(Math.round(fRect.width)), viewportWidth: String(vw) },
          probableCause: 'Footer max-width constrains it.',
          recommendedFix: 'Set footer to width:100% with box-sizing:border-box.',
        });
      }
    }

    // 20. Text Clipping (exclude sr-only/visually-hidden elements by multiple heuristics)
    const isVisuallyHidden = (el: HTMLElement): boolean => {
      const r = el.getBoundingClientRect();
      if (r.width <= 10 || r.height <= 10) return true;
      const s = cs(el);
      if (s.position === 'absolute' || s.position === 'fixed') return true;
      if (s.clip === 'rect(0px, 0px, 0px, 0px)' || (s.clipPath && s.clipPath !== 'none')) return true;
      if (el.classList.contains('awa-sr-only') || el.classList.contains('sr-only') || el.classList.contains('visually-hidden')) return true;
      return false;
    };
    const textContainers = Array.from(document.querySelectorAll<HTMLElement>('.product-item-name, .product-item-link, .page-title, h1, h2, .block-title strong')).filter(el => el.getBoundingClientRect().height > 0 && !isVisuallyHidden(el));
    textContainers.forEach(el => {
      if (el.scrollWidth > el.clientWidth + 6) {
        const s = cs(el);
        if (s.overflow !== 'hidden' || s.textOverflow !== 'ellipsis') {
          issues.push({
            category: 'text-clipping',
            description: 'Text clipping in "' + (el.textContent || '').trim().slice(0, 50) + '"',
            severity: 'major',
            selector: el.tagName.toLowerCase() + '.' + ((el.className || '').split(' ').filter(Boolean)[0] || ''),
            computed: { overflow: s.overflow, textOverflow: s.textOverflow },
            probableCause: 'Fixed width container with long text.',
            recommendedFix: 'Add text-overflow:ellipsis or allow wrapping.',
          });
        }
      }
    });

    return issues;
  }).catch(() => []);
}

async function runResponsiveChecks(page: Page, viewportWidth: number): Promise<RawIssue[]> {
  return page.evaluate((vw) => {
    const issues: RawIssue[] = [];
    const docOverflow = document.documentElement.scrollWidth - document.documentElement.clientWidth;
    if (docOverflow > 5) {
      const allEls = Array.from(document.querySelectorAll('*')) as HTMLElement[];
      const culprits = allEls
        .filter(el => el.scrollWidth > vw && el.getBoundingClientRect().height > 0)
        .map(el => (el.className || '').split(' ').filter(Boolean)[0] || el.tagName)
        .slice(0, 3);
      issues.push({
        category: 'responsive-overflow',
        description: 'Mobile overflow ' + Math.round(docOverflow) + 'px. Culprits: ' + culprits.join(', '),
        severity: 'critical',
        selector: culprits[0] ? '.' + culprits[0] : 'body',
        computed: { overflow: String(docOverflow), culprits: culprits.join(', ') },
        probableCause: 'Fixed-width element not responding to viewport.',
        recommendedFix: 'Add max-width:100% and overflow:hidden to culprit elements.',
      });
    }

    let smallTargets = 0;
    const interactiveEls = Array.from(document.querySelectorAll<HTMLElement>('a, button, input, select, [role="button"]')).filter(el => {
      const rect = el.getBoundingClientRect();
      return rect.height > 0 && rect.width > 0 && rect.top < window.innerHeight;
    });
    interactiveEls.forEach(el => {
      const rect = el.getBoundingClientRect();
      if (rect.height < 32 && rect.width < 32) smallTargets++;
    });
    if (smallTargets > 5) {
      issues.push({
        category: 'responsive-touch-targets',
        description: smallTargets + ' interactive elements below 32px touch target size',
        severity: 'minor',
        selector: 'a, button (multiple)',
        computed: { smallTargetCount: String(smallTargets) },
        probableCause: 'Elements sized for desktop without mobile adjustments.',
        recommendedFix: 'Set min-height:44px; min-width:44px on interactive elements at mobile.',
      });
    }

    return issues;
  }, viewportWidth).catch(() => []);
}

const allIssues: VisualIssue[] = [];
const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
const ISSUES_DIR = path.join(REPORT_DIR, '.issues-' + timestamp);
fs.mkdirSync(ISSUES_DIR, { recursive: true });

test.describe('Deep Visual Audit', () => {
  test.setTimeout(240_000);

  for (const target of TARGETS) {
    test('audit: ' + target.label + ' (' + target.slug + ')', async ({ page, browserName }, testInfo) => {
      const projectName = testInfo.project.name;
      const viewport = page.viewportSize();
      const vpLabel = viewport ? viewport.width + 'x' + viewport.height : 'unknown';
      const ssDir = path.join(SCREENSHOTS_DIR, timestamp, projectName);

      await page.goto(target.url, { waitUntil: 'domcontentloaded', timeout: 90_000 });
      await page.waitForLoadState('load', { timeout: 30_000 }).catch(() => {});
      await page.waitForTimeout(2000);
      await dismissCookieBanner(page);
      await stabilizePage(page);

      if (target.fullScroll) {
        await scrollFullPage(page);
      }

      fs.mkdirSync(ssDir, { recursive: true });
      const aboveFold = path.join(ssDir, target.slug + '-above-fold.png');
      await page.screenshot({ path: aboveFold, fullPage: false }).catch(() => {});

      const rawIssues = await runDeepVisualChecks(page);

      if (viewport && viewport.width < 768) {
        const responsiveIssues = await runResponsiveChecks(page, viewport.width);
        rawIssues.push(...responsiveIssues);
      }

      let fullSS = '';
      if (rawIssues.length > 0 || target.fullScroll) {
        fullSS = path.join(ssDir, target.slug + '-full.png');
        await page.screenshot({ path: fullSS, fullPage: true }).catch(async () => {
          await page.screenshot({ path: fullSS, fullPage: false }).catch(() => {});
        });
      }

      const pageIssues: VisualIssue[] = rawIssues.map(issue => ({
        ...issue,
        page: target.label,
        pageUrl: target.url,
        screenshotPath: fullSS || aboveFold,
        viewport: vpLabel,
      }));

      allIssues.push(...pageIssues);
      fs.writeFileSync(path.join(ISSUES_DIR, target.slug + '.json'), JSON.stringify(pageIssues));

      await testInfo.attach(target.slug + '-above-fold', { path: aboveFold, contentType: 'image/png' }).catch(() => {});
      if (fullSS) {
        await testInfo.attach(target.slug + '-full', { path: fullSS, contentType: 'image/png' }).catch(() => {});
      }
      if (pageIssues.length > 0) {
        const summary = pageIssues.map(i => '[' + i.severity + '] ' + i.category + ': ' + i.description).join('\n');
        await testInfo.attach(target.slug + '-issues', { body: summary, contentType: 'text/plain' }).catch(() => {});
        console.log('\n  [' + target.label + '] ' + pageIssues.length + ' issues:');
        pageIssues.forEach(i => console.log('     [' + i.severity + '] ' + i.category + ': ' + i.description));
      } else {
        console.log('\n  [' + target.label + '] No issues detected');
      }
    });
  }

  test('generate-report', async ({}, testInfo) => {
    fs.mkdirSync(REPORT_DIR, { recursive: true });
    const reportPath = path.join(REPORT_DIR, 'deep-audit-' + timestamp + '.json');
    const mdPath = path.join(REPORT_DIR, 'deep-audit-' + timestamp + '.md');

    // Recover issues from disk (crash-resilient — survives browser/worker restarts)
    if (fs.existsSync(ISSUES_DIR)) {
      const seen = new Set(allIssues.map(i => i.page + ':' + i.category + ':' + i.description));
      for (const f of fs.readdirSync(ISSUES_DIR).filter((f: string) => f.endsWith('.json'))) {
        try {
          const diskIssues: VisualIssue[] = JSON.parse(fs.readFileSync(path.join(ISSUES_DIR, f), 'utf-8'));
          for (const di of diskIssues) {
            const key = di.page + ':' + di.category + ':' + di.description;
            if (!seen.has(key)) { allIssues.push(di); seen.add(key); }
          }
        } catch {}
      }
    }

    const jsonReport = {
      timestamp,
      totalPages: TARGETS.length,
      totalIssues: allIssues.length,
      bySeverity: {
        critical: allIssues.filter(i => i.severity === 'critical').length,
        major: allIssues.filter(i => i.severity === 'major').length,
        minor: allIssues.filter(i => i.severity === 'minor').length,
      },
      byCategory: Object.fromEntries(
        [...new Set(allIssues.map(i => i.category))].map(cat => [cat, allIssues.filter(i => i.category === cat).length])
      ),
      issues: allIssues,
    };
    fs.writeFileSync(reportPath, JSON.stringify(jsonReport, null, 2));

    const md: string[] = [
      '# Deep Visual Audit Report',
      '**Date:** ' + new Date().toISOString().slice(0, 10),
      '**Pages Audited:** ' + TARGETS.length,
      '**Total Issues:** ' + allIssues.length,
      '',
      '| Severity | Count |',
      '|----------|-------|',
      '| Critical | ' + jsonReport.bySeverity.critical + ' |',
      '| Major    | ' + jsonReport.bySeverity.major + ' |',
      '| Minor    | ' + jsonReport.bySeverity.minor + ' |',
      '',
      '## Issues by Category',
      '',
      ...Object.entries(jsonReport.byCategory).map(([cat, count]) => '- **' + cat + '**: ' + count),
      '',
    ];

    const byPage = new Map<string, VisualIssue[]>();
    allIssues.forEach(issue => {
      const key = issue.page;
      if (!byPage.has(key)) byPage.set(key, []);
      byPage.get(key)!.push(issue);
    });

    for (const [pageName, pageIssues] of byPage) {
      md.push('## ' + pageName);
      md.push('**URL:** ' + pageIssues[0].pageUrl);
      md.push('**Viewport:** ' + pageIssues[0].viewport);
      md.push('**Issues:** ' + pageIssues.length);
      md.push('');
      pageIssues.forEach((issue, i) => {
        md.push('### ' + (i + 1) + '. [' + issue.severity.toUpperCase() + '] ' + issue.category);
        md.push('- **Description:** ' + issue.description);
        md.push('- **Selector:** `' + issue.selector + '`');
        md.push('- **Probable Cause:** ' + issue.probableCause);
        md.push('- **Recommended Fix:** ' + issue.recommendedFix);
        if (issue.screenshotPath) {
          const relPath = path.relative(REPORT_DIR, issue.screenshotPath).split(path.sep).join('/');
          md.push('- **Screenshot:** [' + path.basename(issue.screenshotPath) + '](' + relPath + ')');
        }
        md.push('');
      });
    }

    if (allIssues.length === 0) {
      md.push('## No issues detected across all audited pages.');
    }

    fs.writeFileSync(mdPath, md.join('\n'));

    console.log('\n=== AUDIT COMPLETE: ' + allIssues.length + ' issues across ' + TARGETS.length + ' pages ===');
    console.log('  Critical: ' + jsonReport.bySeverity.critical + ' | Major: ' + jsonReport.bySeverity.major + ' | Minor: ' + jsonReport.bySeverity.minor);
    console.log('  JSON: ' + reportPath);
    console.log('  MD:   ' + mdPath);

    await testInfo.attach('audit-report-json', { path: reportPath, contentType: 'application/json' }).catch(() => {});
    await testInfo.attach('audit-report-md', { path: mdPath, contentType: 'text/markdown' }).catch(() => {});
  });
});
