/*
 * SIPUS - Dynamic Layer
 * Membuat pencarian, filter, paginasi, dan area tertentu diperbarui tanpa reload penuh.
 *
 * Konvensi markup:
 *   <form method="GET" data-live-form> ... </form>     -> form apa pun (filter/pencarian)
 *   <div data-live-part="stats"> ... </div>            -> potongan yang diganti (kunci unik per halaman)
 *   <div data-live-part="list"> ... </div>
 *   <div data-live-part="stats" data-autorefresh="30000"> -> auto refresh tiap N ms
 */
(function () {
    'use strict';

    var LIVE_FORM = 'form[data-live-form]';
    var PART = '[data-live-part]';
    var activeController = null;

    function parts() {
        return Array.prototype.slice.call(document.querySelectorAll(PART));
    }

    function byKey(root, key) {
        return Array.prototype.slice.call(root.querySelectorAll('[data-live-part="' + key + '"]'));
    }

    function setLoading(on) {
        if (document.body) {
            document.body.classList.toggle('is-live-loading', on);
        }
        parts().forEach(function (part) {
            part.classList.toggle('is-live-loading', on);
        });
    }

    function formUrl(form) {
        var url = new URL(form.getAttribute('action') || window.location.href, window.location.origin);
        var params = new URLSearchParams();

        new FormData(form).forEach(function (value, key) {
            if (typeof value === 'string' && value.trim() === '') {
                return;
            }
            params.append(key, value);
        });

        url.search = params.toString();
        return url.toString();
    }

    function applyParts(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var replaced = 0;

        parts().forEach(function (part) {
            var key = part.getAttribute('data-live-part');
            var index = byKey(document, key).indexOf(part);
            var fresh = byKey(doc, key)[index];
            if (!fresh) {
                return;
            }
            part.innerHTML = fresh.innerHTML;
            replaced++;
        });

        return replaced;
    }

    function load(url, push) {
        if (activeController) {
            try { activeController.abort(); } catch (e) { /* diabaikan */ }
        }

        var controller = ('AbortController' in window) ? new AbortController() : null;
        activeController = controller;
        setLoading(true);

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined
        })
            .then(function (res) {
                if (!res.ok) { throw new Error(res.status); }
                return res.text();
            })
            .then(function (html) {
                if (activeController !== controller) { return; }
                activeController = null;
                setLoading(false);

                var replaced = applyParts(html);

                if (replaced === 0) {
                    window.location.href = url;
                    return;
                }

                if (push) {
                    history.pushState({ live: true }, '', url);
                } else if (window.location.href !== url) {
                    history.replaceState({ live: true }, '', url);
                }
            })
            .catch(function (err) {
                if (activeController !== controller) { return; }
                activeController = null;
                setLoading(false);

                if (err && err.name === 'AbortError') { return; }
                window.location.href = url;
            });
    }

    function stripInlineOnchange(root) {
        (root || document).querySelectorAll(LIVE_FORM + ' [onchange]').forEach(function (el) {
            el.removeAttribute('onchange');
        });
    }

    // ---- Delegasi event (tetap berfungsi setelah potongan diganti) ----

    document.addEventListener('submit', function (e) {
        if (!e.target.matches || !e.target.matches(LIVE_FORM)) { return; }
        e.preventDefault();
        load(formUrl(e.target), true);
    }, true);

    document.addEventListener('change', function (e) {
        var el = e.target;
        if (!el.form || !el.form.matches(LIVE_FORM)) { return; }
        if (el.matches('select, input[type="checkbox"], input[type="radio"]')) {
            load(formUrl(el.form), true);
        }
    }, true);

    var debounceTimer = null;
    document.addEventListener('input', function (e) {
        var el = e.target;
        if (!el.form || !el.form.matches(LIVE_FORM)) { return; }
        if (el.matches('input[type="text"], input[type="search"]')) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                load(formUrl(el.form), false);
            }, 320);
        }
    }, true);

    document.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('a[href]') : null;
        if (!link) { return; }
        if (link.closest('.sidebar, .bottom-nav, .topbar-brand') || link.target === '_blank' || link.hasAttribute('data-no-live')) {
            return;
        }

        var raw = link.getAttribute('href');
        if (!raw || raw.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(raw)) { return; }

        var url;
        try { url = new URL(link.href, window.location.origin); } catch (err) { return; }
        if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) { return; }

        e.preventDefault();
        load(url.toString(), true);
    }, true);

    window.addEventListener('popstate', function () {
        load(window.location.href, false);
    });

    // Master checkbox tabel (langsung/delegasi).
    document.addEventListener('change', function (e) {
        var el = e.target;
        if (el.matches && el.matches('#checkAll, [data-check-all]')) {
            var scope = el.closest('table') || document;
            scope.querySelectorAll('.row-check, [data-check-row]').forEach(function (cb) {
                cb.checked = el.checked;
            });
            return;
        }
        if (el.matches && el.matches('.row-check, [data-check-row]')) {
            var table = el.closest('table');
            var master = table ? table.querySelector('#checkAll, [data-check-all]') : null;
            if (master) {
                var rows = table.querySelectorAll('.row-check, [data-check-row]');
                master.checked = Array.prototype.every.call(rows, function (cb) { return cb.checked; });
            }
        }
    });

    // ---- Auto refresh ----
    function startAutoRefresh(root) {
        (root || document).querySelectorAll('[data-autorefresh]').forEach(function (el) {
            if (el.dataset.autorefBound === '1') { return; }
            el.dataset.autorefBound = '1';
            var ms = parseInt(el.getAttribute('data-autorefresh'), 10) || 15000;
            setInterval(function () {
                if (document.hidden) { return; }
                load(window.location.href, false);
            }, ms);
        });
    }

    function boot() {
        stripInlineOnchange(document);
        startAutoRefresh(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.SIPUSLive = {
        load: load,
        refresh: function () { load(window.location.href, false); }
    };
})();
