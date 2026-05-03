const fs = require('fs');
const file = '../../app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-visual-bugfix-2026-04-30.css';
let css = fs.readFileSync(file, 'utf8');

css = css.replace(/html body\.page-wrapper/g, 'html body .page-wrapper');

fs.writeFileSync(file, css);
console.log('Fixed body wrapper syntax');
