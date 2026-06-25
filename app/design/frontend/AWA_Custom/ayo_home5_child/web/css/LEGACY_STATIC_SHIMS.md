# Legacy static CSS shims — AWA Motos

These files are **compatibility shims** for stale Magento static URLs. They are
not part of the active design system and must not be referenced by new layout
XML, PHTML templates, CMS blocks, or JavaScript.

## Why they exist

Magento, browsers, bots, service workers, and full-page cache can keep old URLs
such as `/static/version1782344458/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/...`
alive after the active theme has moved or removed a CSS file.

When those historical URLs return `404`, the storefront can look slower or
broken in audits even though the current HTML no longer references the files.
These shims keep those stale requests returning `200 text/css` until old caches
naturally expire.

## Files covered

- `awa-plp-shell-final.css`
- `awa-pdp-shell-final.css`
- `awa-cart-shell-final.css`
- `awa-impeccable-layout-2026-06-16.orig.css`
- `awa-home-shell-final.min.css`
- `awa-social-proof.css`
- `awa-bundle-site.css`
- `email-fonts.min.css`

## Rules

1. Do not add these files to new active layout XML/PHTML.
2. Do not remove them during CSS cleanup without checking access logs/static smoke tests.
3. Keep them versioned in `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/`.
4. `pub/static` is generated/symlinked and must not be the canonical source.
5. If one of these names appears in current HTML, fix the source reference instead of expanding the shim.

## Validation

Run the smoke check after deploys or static cleanup:

```bash
bash scripts/check-legacy-static-assets.sh
```

Optional environment variables:

- `BASE_URL` — defaults to `https://awamotos.com`
- `LEGACY_STATIC_VERSIONS` — space-separated static versions to test
- `CURL_INSECURE=1` — pass `-k` to curl when testing non-standard TLS

Created: 2026-06-25.
