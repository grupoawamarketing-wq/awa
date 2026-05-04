const test = require("node:test");
const assert = require("node:assert/strict");

const helpers = require("../../app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-pdp-sticky-cta.js");

test("normalizeText trims and collapses spaces", () => {
    assert.equal(helpers.normalizeText("  Comprar   agora  "), "Comprar agora");
    assert.equal(helpers.normalizeText(""), "");
    assert.equal(helpers.normalizeText(null), "");
});

test("hasValidCartAction validates Magento add-to-cart route", () => {
    assert.equal(helpers.hasValidCartAction("/checkout/cart/add/uenc/abc/"), true);
    assert.equal(helpers.hasValidCartAction("https://awamotos.com/checkout/cart/add/"), true);
    assert.equal(helpers.hasValidCartAction("/customer/account/login"), false);
    assert.equal(helpers.hasValidCartAction(null), false);
});

test("isInvalidStickyLabel blocks auth-like labels", () => {
    assert.equal(helpers.isInvalidStickyLabel("Entrar para comprar"), true);
    assert.equal(helpers.isInvalidStickyLabel("Login"), true);
    assert.equal(helpers.isInvalidStickyLabel("Comprar agora"), false);
});

test("isVisible handles hidden state defensively", () => {
    const hiddenNode = {
        hidden: true,
        getAttribute: () => "false",
        offsetWidth: 100,
        offsetHeight: 20,
        getClientRects: () => [{ width: 100 }]
    };
    const ariaHiddenNode = {
        hidden: false,
        getAttribute: () => "true",
        offsetWidth: 100,
        offsetHeight: 20,
        getClientRects: () => [{ width: 100 }]
    };
    const visibleNode = {
        hidden: false,
        getAttribute: () => "false",
        offsetWidth: 10,
        offsetHeight: 10,
        getClientRects: () => [{ width: 10 }]
    };

    assert.equal(helpers.isVisible(hiddenNode), false);
    assert.equal(helpers.isVisible(ariaHiddenNode), false);
    assert.equal(helpers.isVisible(visibleNode), true);
    assert.equal(helpers.isVisible(null), false);
});
