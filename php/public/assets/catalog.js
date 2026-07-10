/* Marchas de Cristo — mejoras progresivas del catálogo (sin dependencias).
   Todo funciona sin JS; esto añade orden/filtro en tablas y el atajo "/". */
(function () {
    'use strict';

    // Orden por columna: <table data-sortable>, cabeceras con data-type="num" opcional.
    function initSort(table) {
        var tbody = table.querySelector('tbody');
        if (!tbody) return;
        var dir = {};
        Array.prototype.forEach.call(table.querySelectorAll('thead th'), function (th, i) {
            th.addEventListener('click', function () {
                var type = th.getAttribute('data-type');
                var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                dir[i] = !dir[i];
                rows.sort(function (a, b) {
                    var x = (a.children[i] ? a.children[i].textContent : '').trim();
                    var y = (b.children[i] ? b.children[i].textContent : '').trim();
                    if (type === 'num') {
                        x = parseFloat(x) || 0; y = parseFloat(y) || 0;
                        return dir[i] ? x - y : y - x;
                    }
                    return dir[i] ? x.localeCompare(y, 'es') : y.localeCompare(x, 'es');
                }).forEach(function (r) { tbody.appendChild(r); });
            });
        });
    }

    // Filtro de filas: <input data-filter="id-tabla" data-count="id-contador" data-total="N">
    function initFilter(input) {
        var table = document.getElementById(input.getAttribute('data-filter'));
        if (!table) return;
        var tbody = table.querySelector('tbody');
        var count = document.getElementById(input.getAttribute('data-count') || '');
        var initial = count ? count.textContent : '';
        var total = parseInt(input.getAttribute('data-total') || '0', 10);
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            var vis = 0;
            Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (r) {
                var hit = q === '' || r.textContent.toLowerCase().indexOf(q) !== -1;
                r.classList.toggle('hidden', !hit);
                if (hit) vis++;
            });
            if (count) count.textContent = q === '' ? initial : vis + ' de ' + (total || vis);
        });
    }

    // Fachada de vídeo: al pulsar, sustituye la miniatura por el iframe de
    // youtube-nocookie con autoplay. Hasta entonces no se carga nada de YouTube.
    function initYtFacade(embed) {
        var btn = embed.querySelector('.ytfacade');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var id = embed.getAttribute('data-ytid');
            if (!id) return;
            var iframe = document.createElement('iframe');
            iframe.src = 'https://www.youtube-nocookie.com/embed/' + id +
                '?autoplay=1&rel=0';
            iframe.title = 'Reproductor de vídeo de YouTube';
            iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
            iframe.setAttribute('allowfullscreen', '');
            embed.innerHTML = '';
            embed.appendChild(iframe);
        });
    }

    Array.prototype.forEach.call(document.querySelectorAll('table[data-sortable]'), initSort);
    Array.prototype.forEach.call(document.querySelectorAll('input[data-filter]'), initFilter);
    Array.prototype.forEach.call(document.querySelectorAll('.ytembed[data-ytid]'), initYtFacade);

    // "/" salta al buscador del catálogo (salvo que ya se esté escribiendo).
    var q = document.getElementById('site-q');
    if (q) {
        document.addEventListener('keydown', function (e) {
            var tag = document.activeElement ? document.activeElement.tagName : '';
            if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(tag)) {
                e.preventDefault();
                q.focus();
            }
        });
    }
})();
