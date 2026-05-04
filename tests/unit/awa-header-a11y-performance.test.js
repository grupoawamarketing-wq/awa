const test = require("node:test");
const assert = require("node:assert/strict");

const header = require("../../app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-header-a11y-performance.js");

test("normalizeText normaliza espaços e trim", () => {
    assert.equal(header.normalizeText("  Busca   rápida "), "Busca rápida");
    assert.equal(header.normalizeText(null), "");
});

test("clampInt aplica fallback e limites", () => {
    assert.equal(header.clampInt("10", 0, 100, 0), 10);
    assert.equal(header.clampInt("999", 0, 100, 0), 100);
    assert.equal(header.clampInt("-3", 0, 99, 0), 0);
    assert.equal(header.clampInt("abc", 0, 99, 7), 7);
});
