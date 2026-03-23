# MF-001: Mobile-First Optimization — Week 4 (Final)

**Status**: 🟡 STARTING
**Target**: Mobile breakpoints, RWD images, touch-friendly UI
**Duration**: 2-3 days (final week of 4-week sprint)
**Commit**: TBD (pending implementation)

---

## Objective

Ensure AWA Motos website is fully mobile-optimized:
1. Responsive breakpoints: 375px (mobile), 480px (tablet), 640px+ (desktop)
2. Touch targets: 44×44px minimum for all interactive elements
3. RWD images with srcset for different densities
4. Optimized layout for small screens
5. Mobile keyboard and screen reader testing

---

## Analysis Phase

### Current Mobile Status

- ⚠️ Breakpoints: Minimal mobile breakpoints (mostly 768px threshold)
- ⚠️ Touch targets: Some buttons < 44px (especially in B2B section)
- ⚠️ Images: No srcset, loading full-resolution images on mobile
- ⚠️ Typography: Font sizes may be too small on mobile (< 14px)
- ⚠️ Spacing: Padding/margins too tight on small screens
- ✅ Meta viewport: Already set correctly

### Key Components to Fix

1. **Navigation** (Account nav, B2B shortcuts):
   - Spacing: 16px horizontal padding on mobile (375px)
   - Link height: 48px+ for touch accuracy
   - Font size: ≥ 14px for readability

2. **Buttons & CTAs**:
   - Add to cart: 48×48px touch target
   - Quick order, reorder: 44×44px minimum
   - All buttons: spacing between 8px+ on mobile

3. **Forms**:
   - Input height: 44px+ for touch keyboard
   - Label font size: 14px+ for legibility
   - Error messages: Readable at mobile size

4. **Images**:
   - Product images: Add srcset for 1x, 2x density
   - Homepage hero: Load optimized version for mobile (375px width)
   - Lazy load images outside viewport

5. **Typography**:
   - Body text: 14px minimum on mobile, 16px on desktop
   - Headings: Scale appropriately for small screens
   - Line height: ≥ 1.5 for readability

---

## Implementation Plan

### Phase 1: Mobile Audit & Breakpoints (Today)
- [ ] Run Chrome DevTools mobile simulation (375px, 480px, 640px)
- [ ] Check touch target sizes in DevTools
- [ ] Verify font sizes at 375px viewport
- [ ] Identify layout issues at mobile width

### Phase 2: CSS & Images (Tomorrow)
- [ ] Create awa-bundle-mobilefast-mf001.css
- [ ] Add media queries for mobile breakpoints
- [ ] Increase touch targets to 44×48px
- [ ] Optimize typography scale
- [ ] Add srcset to product/hero images
- [ ] Implement lazy loading where applicable

### Phase 3: Validation & Deploy (Day 3)
- [ ] Test in Chrome DevTools (375px, 480px)
- [ ] Test on physical devices (iPhone, Android tablet)
- [ ] Test keyboard navigation on mobile (Tab key)
- [ ] Canary deployment (10%, 1h)
- [ ] Gradual rollout (25% → 50% → 100%)

---

## Technical Details

### CSS Breakpoints (Mobile-First Approach)

```css
/* MOBILE FIRST: Start at 375px (smallest common viewport) */
@media (min-width: 375px) { /* base styles */ }
@media (min-width: 480px) { /* tablets */ }
@media (min-width: 640px) { /* larger tablets */ }
@media (min-width: 768px) { /* small desktop */ }
@media (min-width: 1024px) { /* desktop */ }
@media (min-width: 1440px) { /* large desktop */ }
```

### Touch Target Sizes

```css
/* Mobile: 44×44px minimum WCAG AA */
button, .button, input[type="submit"] {
    min-height: 44px;
    min-width: 44px;
    padding: 12px 16px;
    line-height: 1.5;
}

/* Larger touch targets for critical actions: 48×48px */
.add-to-cart, .quick-order, .checkout-btn {
    min-height: 48px;
    min-width: 48px;
    padding: 14px 20px;
}

/* Spacing between touch targets: 8px+ */
button + button, .button + .button {
    margin-left: 8px;
}
```

### RWD Images

```html
<!-- Product image with srcset -->
<img src="product-375w.jpg"
     srcset="product-375w.jpg 1x,
             product-750w.jpg 2x,
             product-480w.jpg 480w,
             product-1024w.jpg 1024w"
     sizes="(max-width: 480px) 100vw,
            (max-width: 768px) 50vw,
            33vw"
     alt="Product name"
     loading="lazy">
```

### Typography Scale (Mobile-First)

```css
/* Mobile (375px): Smaller base size */
body { font-size: 14px; line-height: 1.6; }
h1 { font-size: 24px; }
h2 { font-size: 20px; }
h3 { font-size: 18px; }

/* Tablet (480px+) */
@media (min-width: 480px) {
    body { font-size: 15px; }
    h1 { font-size: 28px; }
    h2 { font-size: 22px; }
}

/* Desktop (768px+) */
@media (min-width: 768px) {
    body { font-size: 16px; line-height: 1.7; }
    h1 { font-size: 32px; }
    h2 { font-size: 24px; }
}
```

---

## Metrics

| Metric | Before | Target | Status |
|--------|--------|--------|--------|
| Mobile Usability Score | TBD | 90+ | TBD |
| Touch Target Size | TBD | 100% ≥44px | TBD |
| Font Size Coverage | TBD | 100% ≥14px | TBD |
| Core Web Vitals (LCP) | ~2.5s | <2.5s | TBD |
| Layout Shift (CLS) | TBD | <0.1 | TBD |

---

## Testing Checklist

- [ ] DevTools mobile simulation (375px, 480px, 640px)
- [ ] Touch targets measured ≥44px (DevTools Inspector)
- [ ] Font sizes ≥14px at 375px viewport
- [ ] Images load correctly with srcset
- [ ] Keyboard navigation on mobile (Tab, Enter, Escape)
- [ ] Screen reader test (VoiceOver on iOS, TalkBack on Android)
- [ ] Landscape/portrait orientations work
- [ ] No horizontal scroll at 375px
- [ ] Performance: Core Web Vitals pass

---

## Success Criteria

- ✅ Mobile Usability: 90+ in Google PageSpeed
- ✅ Touch targets: 100% interactive elements ≥44px
- ✅ Typography: All text ≥14px at 375px viewport
- ✅ Images: Correctly optimized via srcset
- ✅ No regressions: A/B test shows no increase in bounce rate on mobile

---

## Current Status

⏳ Phase 1 starting (Mobile audit)
