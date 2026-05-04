const test = require("node:test");
const assert = require("node:assert/strict");

const sticky = require("../../app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-round3-pdp-sticky-cta.js");

test("normalizeText normaliza espaços e trim", () => {
    assert.equal(sticky.normalizeText("  Comprar   agora "), "Comprar agora");
    assert.equal(sticky.normalizeText(null), "");
});

test("hasValidCartActionFromAction valida rota add-to-cart", () => {
    assert.equal(sticky.hasValidCartActionFromAction("/checkout/cart/add/"), true);
    assert.equal(sticky.hasValidCartActionFromAction("https://awamotos.com/checkout/cart/add/uenc/"), true);
    assert.equal(sticky.hasValidCartActionFromAction("/customer/account/login"), false);
    assert.equal(sticky.hasValidCartActionFromAction(null), false);
});

test("isInvalidStickyLabel bloqueia rótulos de autenticação", () => {
    assert.equal(sticky.isInvalidStickyLabel("Entrar para ver preço"), true);
    assert.equal(sticky.isInvalidStickyLabel("Login"), true);
    assert.equal(sticky.isInvalidStickyLabel("Comprar"), false);
});
