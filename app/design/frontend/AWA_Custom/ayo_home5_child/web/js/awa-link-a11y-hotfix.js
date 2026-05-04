(function (factory) {
    "use strict";

    if (typeof define === "function" && define.amd) {
        define([], function () {
            return factory(window, document);
        });
        return;
    }

    if (typeof module === "object" && module.exports) {
        module.exports = factory(globalThis, null);
        return;
    }

    factory(window, document);
})(function (root, documentRef) {
    "use strict";

    function reportError(context, error) {
        if (root && root.console && typeof root.console.warn === "function") {
            root.console.warn("[AWA Link A11y Hotfix] " + context, error);
        }
    }

    function text(value) {
        return String(value || "").replace(/\s+/g, " ").trim();
    }

    function isElement(node) {
        return !!(node && node.nodeType === 1);
    }

    function hasAccessibleName(link) {
        if (!isElement(link) || typeof link.getAttribute !== "function") {
            return false;
        }
        return Boolean(
            text(link.textContent) ||
            text(link.getAttribute("aria-label")) ||
            text(link.getAttribute("aria-labelledby")) ||
            text(link.getAttribute("title"))
        );
    }

    function resolveImageAlt(link) {
        if (!isElement(link) || typeof link.querySelector !== "function") {
            return "";
        }
        var img = link.querySelector("img[alt]");
        if (!img || typeof img.getAttribute !== "function") {
            return "";
        }
        return text(img.getAttribute("alt"));
    }

    function normalizeLink(link) {
        if (!isElement(link) || typeof link.setAttribute !== "function") {
            return;
        }

        if (hasAccessibleName(link)) {
            return;
        }

        var alt = "";
        try {
            alt = resolveImageAlt(link);
        } catch (error) {
            reportError("resolveImageAlt", error);
            return;
        }

        if (!alt) {
            return;
        }

        link.setAttribute("aria-label", alt);
        if (!text(link.getAttribute("title"))) {
            link.setAttribute("title", alt);
        }
    }

    function normalizeScope(rootScope) {
        var scope = rootScope || documentRef;
        var links;
        var i;

        if (!scope || typeof scope.querySelectorAll !== "function") {
            return;
        }

        try {
            links = scope.querySelectorAll('a:not([aria-hidden="true"])');
        } catch (error) {
            reportError("querySelectorAll links", error);
            return;
        }

        for (i = 0; i < links.length; i += 1) {
            normalizeLink(links[i]);
        }
    }

    function collectElementNodesFromMutations(mutations) {
        var nodes = [];
        var i;
        var j;
        var mutation;
        var node;

        if (!Array.isArray(mutations)) {
            return nodes;
        }

        for (i = 0; i < mutations.length; i += 1) {
            mutation = mutations[i];
            if (!mutation || !mutation.addedNodes) {
                continue;
            }
            for (j = 0; j < mutation.addedNodes.length; j += 1) {
                node = mutation.addedNodes[j];
                if (isElement(node)) {
                    nodes.push(node);
                }
            }
        }

        return nodes;
    }

    function boot(documentNode) {
        var doc = documentNode || documentRef;
        var flushQueued = false;
        var pendingNodes = [];

        if (!doc) {
            return;
        }

        normalizeScope(doc);
        if (typeof MutationObserver === "undefined" || !doc.body) {
            return;
        }

        function flushPendingNodes() {
            var queue = pendingNodes.slice();
            var i;
            flushQueued = false;
            pendingNodes = [];

            for (i = 0; i < queue.length; i += 1) {
                normalizeScope(queue[i]);
            }
        }

        function scheduleFlush() {
            if (flushQueued) {
                return;
            }
            flushQueued = true;
            if (root && typeof root.requestAnimationFrame === "function") {
                root.requestAnimationFrame(flushPendingNodes);
                return;
            }
            root.setTimeout(flushPendingNodes, 16);
        }

        new MutationObserver(function (mutations) {
            pendingNodes = pendingNodes.concat(collectElementNodesFromMutations(mutations));
            scheduleFlush();
        }).observe(doc.body, { childList: true, subtree: true });
    }

    var api = {
        text: text,
        hasAccessibleName: hasAccessibleName,
        resolveImageAlt: resolveImageAlt,
        normalizeLink: normalizeLink,
        normalizeScope: normalizeScope,
        collectElementNodesFromMutations: collectElementNodesFromMutations,
        boot: boot
    };

    if (documentRef) {
        if (documentRef.readyState === "loading") {
            documentRef.addEventListener("DOMContentLoaded", function () {
                boot(documentRef);
            }, { once: true });
        } else {
            boot(documentRef);
        }
    }

    return api;
});
