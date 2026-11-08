(function () {
    var body = document.body;
    var overlay = document.querySelector('.sidebar-overlay');
    var toggler = document.querySelector('[data-sidebar-toggle]');

    function open() {
        body.classList.add('sidebar-open');
    }

    function close() {
        body.classList.remove('sidebar-open');
    }

    if (toggler) {
        toggler.addEventListener('click', open);
    }

    if (overlay) {
        overlay.addEventListener('click', close);
    }

    document.querySelectorAll('.sidebar a, .sidebar button, .bottom-nav a').forEach(function (el) {
        el.addEventListener('click', close);
    });

    document.querySelectorAll('[data-check-all]').forEach(function (master) {
        var rows = master.closest('table') ? master.closest('table').querySelectorAll('[data-check-row]') : [];

        master.addEventListener('change', function () {
            rows.forEach(function (cb) {
                cb.checked = master.checked;
            });
        });

        rows.forEach(function (cb) {
            cb.addEventListener('change', function () {
                master.checked = Array.prototype.every.call(rows, function (r) {
                    return r.checked;
                });
            });
        });
    });
})();