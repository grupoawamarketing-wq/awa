const test = require("node:test");
const assert = require("node:assert/strict");

const a11y = require("../../app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-link-a11y-hotfix.js");

test("text normaliza espaços e trim", () => {
    assert.equal(a11y.text("  Link   com   espacos "), "Link com espacos");
    assert.equal(a11y.text(null), "");
});

test("hasAccessibleName detecta texto e aria-label", () => {
    const withText = {
        nodeType: 1,
        textContent: "Comprar agora",
        getAttribute: () => ""
    };
    const withAria = {
        nodeType: 1,
        textContent: "",
        getAttribute: (name) => (name === "aria-label" ? "Abrir menu" : "")
    };
    const withoutName = {
        nodeType: 1,
        textContent: "",
        getAttribute: () => ""
    };

    assert.equal(a11y.hasAccessibleName(withText), true);
    assert.equal(a11y.hasAccessibleName(withAria), true);
    assert.equal(a11y.hasAccessibleName(withoutName), false);
});

test("resolveImageAlt extrai alt válido da imagem", () => {
    const withAlt = {
        nodeType: 1,
        querySelector: () => ({ getAttribute: () => " Categoria A " })
    };
    const withoutAlt = {
        nodeType: 1,
        querySelector: () => null
    };

    assert.equal(a11y.resolveImageAlt(withAlt), "Categoria A");
    assert.equal(a11y.resolveImageAlt(withoutAlt), "");
});

test("normalizeLink aplica aria-label/title quando necessário", () => {
    const attrs = {};
    const link = {
        nodeType: 1,
        textContent: "",
        getAttribute: (name) => attrs[name] || "",
        setAttribute: (name, value) => { attrs[name] = value; },
        querySelector: () => ({ getAttribute: () => "Peças Honda" })
    };

    a11y.normalizeLink(link);

    assert.equal(attrs["aria-label"], "Peças Honda");
    assert.equal(attrs.title, "Peças Honda");
});

test("normalizeLink não sobrescreve título existente", () => {
    const attrs = { title: "Título existente" };
    const link = {
        nodeType: 1,
        textContent: "",
        getAttribute: (name) => attrs[name] || "",
        setAttribute: (name, value) => { attrs[name] = value; },
        querySelector: () => ({ getAttribute: () => "Imagem alt" })
    };

    a11y.normalizeLink(link);

    assert.equal(attrs["aria-label"], undefined);
    assert.equal(attrs.title, "Título existente");
});

test("collectElementNodesFromMutations retorna apenas nós de elemento", () => {
    const elementA = { nodeType: 1 };
    const textNode = { nodeType: 3 };
    const elementB = { nodeType: 1 };
    const mutations = [
        { addedNodes: [elementA, textNode] },
        null,
        { addedNodes: [elementB] }
    ];

    const result = a11y.collectElementNodesFromMutations(mutations);
    assert.equal(result.length, 2);
    assert.equal(result[0], elementA);
    assert.equal(result[1], elementB);
});

test("normalizeScope falha de querySelectorAll não lança exceção", () => {
    const badScope = {
        querySelectorAll: () => {
            throw new Error("falha simulada");
        }
    };

    assert.doesNotThrow(() => {
        a11y.normalizeScope(badScope);
    });
});
