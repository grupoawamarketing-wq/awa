const fs = require('fs');
const file = 'app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-visual-bugfix-2026-04-30.css';
let css = fs.readFileSync(file, 'utf8');

css += `\n
/* VERY HIGH SPECIFICITY TO FORCE HEADER GRID PROPORTIONS */
html body.page-wrapper .awa-site-header .awa-main-header__inner.wp-header,
:is(html body.cms-index-index, html body.cms-home) .page-wrapper .awa-site-header .header .wp-header,
html body .page-wrapper .awa-site-header .header .awa-main-header__inner.wp-header {
    grid-template-columns: 148px minmax(400px, 1fr) auto !important;
    align-items: center !important;
    column-gap: 32px !important;
    padding: 0 24px !important;
    max-width: 1440px !important;
    margin: 0 auto !important;
    min-height: 92px !important;
}

/* Fix logo width to 148px exactly */
html body.page-wrapper .awa-site-header .awa-header-brand-cell,
:is(html body.cms-index-index, html body.cms-home) .page-wrapper .awa-site-header .header .awa-header-brand-cell {
    width: 148px !important;
    min-width: 148px !important;
    max-width: 148px !important;
    padding: 0 !important;
    margin: 0 !important;
}

/* Make logo image scale properly */
html body.page-wrapper .awa-site-header .awa-header-brand-cell img {
    max-width: 100% !important;
    height: auto !important;
}

/* Search bar height 40px */
html body.page-wrapper .awa-site-header .block-search .control input#search {
    height: 40px !important;
    min-height: 40px !important;
}

/* Topbar 12px height */
html body.page-wrapper .awa-site-header .top-header.awa-b2b-promo-bar .awa-b2b-promo-bar__inner {
    min-height: 12px !important;
    padding: 2px 24px !important;
}
html body.page-wrapper .awa-site-header .top-header.awa-b2b-promo-bar .awa-b2b-promo-bar__tail {
    font-size: 11px !important;
}
`;

fs.writeFileSync(file, css);
console.log('Appended grid specificity rules');
