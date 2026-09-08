/* Predictivos de los editores de acompañamientos (/dashboard/acompanamientos).
   Tres piezas, todas sobre el mismo patrón de los endpoints fastSearch que ya usan
   admin.js y banda-relaciones.js:

     1. Buscar banda en el índice → navega a su listado.
     2. Cambiar la banda de una fila del editor por localidad (hay N cajas en
        la misma página, así que se resuelve por data-attributes dentro del
        <form> de cada fila, no por id global).
     3. Hermandades ya cargadas, acotadas por la localidad escrita al lado,
        para no meter la misma hermandad con tres grafías distintas.

   Sin dependencias: cada bloque sale solo si sus nodos no están en la página. */
(function () {
    'use strict';

    /** Lanza fetch a un fastSearch con debounce + cancelación del anterior. */
    function buscador(input, minLen, url, pinta) {
        var timer = null;
        var ctrl = null;
        input.addEventListener('input', function () {
            var q = input.value.trim();
            clearTimeout(timer);
            if (q.length < minLen) { pinta(null); return; }
            timer = setTimeout(function () {
                if (ctrl) ctrl.abort();
                ctrl = new AbortController();
                fetch(url(q), { signal: ctrl.signal, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) { pinta(Array.isArray(d.data) ? d.data : []); })
                    .catch(function () { /* abortado o red caída */ });
            }, 200);
        });
    }

    /** Pinta las opciones en un contenedor .suggest y cablea el click. */
    function pintaSugerencias(caja, filas, etiqueta, alElegir) {
        if (!filas || !filas.length) { caja.hidden = true; caja.innerHTML = ''; return; }
        caja.innerHTML = '';
        filas.forEach(function (r) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'suggest-item';
            b.textContent = etiqueta(r);
            b.addEventListener('click', function () {
                alElegir(r, etiqueta(r));
                caja.hidden = true;
                caja.innerHTML = '';
            });
            caja.appendChild(b);
        });
        caja.hidden = false;
    }

    /** Cierra una caja de sugerencias al pinchar fuera de ella. */
    function cierraAlSalir(caja, input) {
        document.addEventListener('mousedown', function (e) {
            if (!caja.contains(e.target) && e.target !== input) { caja.hidden = true; caja.innerHTML = ''; }
        });
    }

    // 1. Índice: elegir banda → ir a su listado de acompañamientos.
    (function () {
        var input = document.getElementById('acBandaSearch');
        var caja = document.getElementById('acBandaSuggest');
        if (!input || !caja) return;
        buscador(input, 3, function (q) {
            return '/api/banda/fastSearch?q=' + encodeURIComponent(q);
        }, function (filas) {
            pintaSugerencias(caja, filas,
                function (r) { return r.LABEL || ('#' + r.ID_BANDA); },
                function (r) { window.location.href = '/dashboard/acompanamientos/banda/' + r.ID_BANDA; });
        });
        cierraAlSalir(caja, input);
    })();

    // 2. Editor por localidad: cambiar la banda de una fila concreta.
    Array.prototype.forEach.call(document.querySelectorAll('[data-acomp-banda-search]'), function (input) {
        var form = input.closest('form');
        if (!form) return;
        var hidden = form.querySelector('[data-acomp-banda-id]');
        var caja = form.querySelector('[data-acomp-banda-suggest]');
        var elegida = form.querySelector('[data-acomp-banda-chosen]');
        if (!hidden || !caja) return;

        buscador(input, 3, function (q) {
            return '/api/banda/fastSearch?q=' + encodeURIComponent(q);
        }, function (filas) {
            pintaSugerencias(caja, filas,
                function (r) { return r.LABEL || ('#' + r.ID_BANDA); },
                function (r, label) {
                    hidden.value = r.ID_BANDA;
                    if (elegida) elegida.textContent = label + ' (#' + r.ID_BANDA + ')';
                    input.value = '';
                });
        });
        cierraAlSalir(caja, input);
    });

    // 3. Alta por banda: predictivo de hermandades acotado por la localidad.
    (function () {
        var input = document.querySelector('[data-acomp-hermandad-search]');
        var caja = document.getElementById('acHermandadSuggest');
        if (!input || !caja) return;
        var locInput = document.getElementById(input.getAttribute('data-acomp-localidad') || '');

        buscador(input, 3, function (q) {
            var loc = locInput ? locInput.value.trim() : '';
            return '/api/hermandad/fastSearch?q=' + encodeURIComponent(q)
                 + (loc ? '&loc=' + encodeURIComponent(loc) : '');
        }, function (filas) {
            pintaSugerencias(caja, filas,
                function (r) { return r.HERMANDAD + (r.LOCALIDAD ? ' · ' + r.LOCALIDAD : ''); },
                function (r) {
                    input.value = r.HERMANDAD;
                    // La localidad viaja con la sugerencia: si la caja estaba
                    // vacía, se rellena sola con la de la hermandad elegida.
                    if (locInput && locInput.value.trim() === '' && r.LOCALIDAD) locInput.value = r.LOCALIDAD;
                });
        });
        cierraAlSalir(caja, input);
    })();
})();
