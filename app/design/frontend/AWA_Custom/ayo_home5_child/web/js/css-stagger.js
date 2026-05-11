/**
 * AWA CSS Stagger — previne Chrome long task > 5s ao parsear 19.6MB de CSS.
 * Enfileira CSS async e aplica 1 por vez com 50ms yield entre cada.
 * Uso: <link ... media="print" onload="window.__awaCssQ(this)"/>
 */
(function () {
    var q = [], running = false;
    function drain() {
        if (!q.length) { running = false; return; }
        q.shift().media = 'all';
        requestAnimationFrame(function () { setTimeout(drain, 50); });
    }
    window.__awaCssQ = function (el) {
        q.push(el);
        if (!running) {
            running = true;
            requestAnimationFrame(function () { setTimeout(drain, 50); });
        }
    };
})();
