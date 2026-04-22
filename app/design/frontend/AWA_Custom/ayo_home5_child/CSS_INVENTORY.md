# CSS_INVENTORY.md — Inventário de Arquivos CSS
**AWA Motos · ayo_home5_child · web/css/**  |  **Última atualização:** 2026-04-22

**Legenda:** `ATIVO` carregado em produção | `UNMIN` fonte não-minificada (debug) | `LAYERS` carregado via layout XML específico

---

## Ativos — Global (todas as páginas)

| Arquivo | Modo | Mecanismo |
|---|---|---|
| `awa-core-variables.css` | **MERGED** | fundido em `awa-bundle-core.css` (2026-04-22) — eliminado 1 HTTP request |
| `awa-bundle-vendor-libs.css` | sync | `default_head_blocks.xml` |
| `awa-bundle-core.css` | sync | `default_head_blocks.xml` |
| `awa-critical-fold.css` | sync | `default_head_blocks.xml` |
| `awa-phase17-print.css` | print | `default_head_blocks.xml` |
| `awa-animate-theme.css` | async | `awa-head-preload.phtml` |
| `awa-bundle-accessibility-af001.css` | async | `awa-head-preload.phtml` |
| `awa-bundle-custom.css` | async | `awa-head-preload.phtml` |
| `awa-bundle-phases.css` | async | `awa-head-preload.phtml` |
| `awa-bundle-refinements.css` | async | `awa-head-preload.phtml` |
| `awa-bundle-site.css` | async | `awa-head-preload.phtml` |
| `awa-bundle-tail.css` | async | `awa-head-preload.phtml` |
| `awa-polish-sweep.css` | async | `awa-head-preload.phtml` |
| `awa-visual-fixes-critical.css` | async | `awa-head-preload.phtml` |
| `swiper-bundle.min.css` | async | `awa-head-preload.phtml` |
| `awa-bundle-cosmetic.css` | async deferred | `awa-deferred-cosmetic-css.phtml` |
| `awa-bundle-cosmetic-home.css` | async deferred | `awa-deferred-cosmetic-css.phtml` |

## Ativos — Página-específicos

| Arquivo | Página | Layout XML |
|---|---|---|
| `awa-bundle-pdp.css` | PDP | `catalog_product_view.xml` |
| `awa-pdp-b2b-pro.css` | PDP | `catalog_product_view.xml` |
| `awa-pdp-clean-refinement.css` | PDP | `catalog_product_view.xml` |
| `awa-home-vertical-menu-shell-fix.css` | Home | `cms_index_index.xml` |
| `awa-bundle-home-custom.css` | Home | `cms_index_index.xml` |
| `awa-bundle-category.css` | PLP | `catalog_category_view.xml` |
| `awa-plp-final-polish.css` | PLP | `catalog_category_view.xml` |
| `awa-bundle-search.css` | Busca + Carrinho | `catalogsearch_result_index.xml` + `checkout_cart_index.xml` |
| `awa-consistency-home5.css` | Home (stage only) | `cms_page_view_id_*.xml` (parent) |
| `awa-bundle-inner-pages.css` | Checkout + B2B | `checkout_index_index.xml` + B2B |
| `awa-bundle-auth.css` | B2B Auth/Register | B2B layouts |
| `awa-bundle-blog.css` | Blog | `blog_*.xml` |
| `b2b/auth/refine.css` | B2B Auth | `b2b_auth_shell.xml` |
| `b2b/register-override.css` | B2B Register | `b2b_register_index.xml` |
| `layers/pages/account-b2b.css` | Conta | `customer_account.xml` |
| `layers/pages/cart-checkout.css` | Checkout OPC | `onepagecheckout_index_index.xml` |

## LESS Sources (compilados em tempo de execução pelo Magento)

| Arquivo | Papel |
|---|---|
| `source/_awa-variables.less` | ✅ Fonte canônica única — 6 brand tokens |
| `source/_variables.less` | Shim Magento Blank + `@import '_awa-variables'` |
| `source/_extend.less` | Estilos globais AWA DS + CSS custom props via LESS vars |
| `source/_awa-header-professional.less` | Header styles |
| `source/_awa-search-professional.less` | Search styles |
| `source/_awa-search-autocomplete.less` | Search autocomplete |
| `source/_awa-ux-audit-fixes.less` | UX audit fixes |
| `source/_awa-b2b-phases4-7.less` | B2B styles |

## .unmin.css — Fontes de desenvolvimento (não carregadas pelo Magento)

`awa-bundle-core.unmin.css`, `awa-bundle-cosmetic.unmin.css`, `awa-bundle-cosmetic-home.unmin.css`,
`awa-bundle-custom.unmin.css`, `awa-bundle-home-custom.unmin.css`, `awa-bundle-phases.unmin.css`,
`awa-bundle-refinements.unmin.css`, `awa-bundle-site.unmin.css`, `awa-bundle-tail.unmin.css`,
`awa-bundle-vendor-libs.unmin.css`, `awa-bundle-category.unmin.css`, `awa-bundle-pdp.unmin.css` *(se existir)*,
`awa-pdp-b2b-pro.unmin.css`, `awa-core-variables.unmin.css`, `awa-critical-fold.unmin.css`,
`awa-bundle-accessibility-af001.unmin.css`, `awa-visual-fixes-critical.unmin.css`,
`awa-polish-sweep.unmin.css`, `awa-animate-theme.unmin.css`

---

## Resumo — Antes vs Depois (Fase 2 completa)

| Categoria | Antes | Depois | Ação |
|---|---|---|---|
| Ativos (bundles + individuais) | ~40 | ~40 | Mantidos |
| LESS sources | 8 | 8 | Padronizados (brand tokens) |
| Layers ativos | 2 | 2 | Mantidos |
| Layers órfãos | 9 | 0 | ✅ Deletados |
| Merged (em bundles, no disco) | ~27 | 0 | ✅ Deletados |
| Backups (.bak/.backup) | 13 | 0 | ✅ Deletados |
| Orphaned (sem referência) | ~39 | 0 | ✅ Deletados |
| Deprecated | 1 | 0 | ✅ Deletados |
| .unmin.css (fontes dev) | ~26 | ~20 | Mantidos |
| **Total** | **~162** | **~46** | **-116 arquivos (-72%)** |
