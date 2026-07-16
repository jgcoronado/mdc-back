/* Autocomplete de autores para el formulario de marcha (JS mínimo, sin dependencias). */
(function () {
    const box = document.getElementById('autoresBox');
    const search = document.getElementById('autorSearch');
    const suggest = document.getElementById('autorSuggest');
    if (!box || !search || !suggest) return;

    const selectedIds = () =>
        Array.from(box.querySelectorAll('input[name="autoresIds[]"]')).map((i) => i.value);

    function addChip(id, label) {
        if (selectedIds().includes(String(id))) return;
        const chip = document.createElement('span');
        chip.className = 'chip';
        chip.dataset.id = id;
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'autoresIds[]';
        hidden.value = id;
        const span = document.createElement('span');
        span.textContent = label;
        const x = document.createElement('button');
        x.type = 'button';
        x.className = 'chip-x';
        x.setAttribute('aria-label', 'Quitar');
        x.textContent = '×';
        chip.append(hidden, span, x);
        box.appendChild(chip);
        triggerDuplicateCheck();
    }

    box.addEventListener('click', (e) => {
        if (e.target.classList.contains('chip-x')) {
            e.target.closest('.chip').remove();
            triggerDuplicateCheck();
        }
    });

    // API pública mínima para que otras páginas (p.ej. revisión de ingesta)
    // puedan añadir un autor ya conocido sin pasar por el cuadro de búsqueda.
    window.AutorAutocomplete = { addChip, selectedIds };

    function closeSuggest() { suggest.hidden = true; suggest.innerHTML = ''; }

    let timer, controller;
    search.addEventListener('input', () => {
        const q = search.value.trim();
        clearTimeout(timer);
        if (q.length < 3) { closeSuggest(); return; }
        timer = setTimeout(async () => {
            if (controller) controller.abort();
            controller = new AbortController();
            try {
                const res = await fetch('/api/autor/fastSearch?nombre=' + encodeURIComponent(q),
                    { signal: controller.signal, credentials: 'same-origin' });
                const data = await res.json();
                const rows = Array.isArray(data.data) ? data.data : [];
                const sel = selectedIds();
                const items = rows.filter((r) => !sel.includes(String(r.ID_AUTOR)));
                if (!items.length) { closeSuggest(); return; }
                suggest.innerHTML = '';
                items.forEach((r) => {
                    const label = r.NOMBRE_COMPLETO || ((r.APELLIDOS || '') + ' ' + (r.NOMBRE || '')).trim();
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'suggest-item';
                    b.textContent = label;
                    b.addEventListener('click', () => {
                        clearTimeout(timer);
                        if (controller) { controller.abort(); controller = null; }
                        addChip(r.ID_AUTOR, label);
                        search.value = '';
                        closeSuggest();
                        search.focus();
                    });
                    suggest.appendChild(b);
                });
                suggest.hidden = false;
            } catch (e) { /* abortado o red: ignorar */ }
        }, 200);
    });

    document.addEventListener('mousedown', (e) => {
        if (!suggest.contains(e.target) && e.target !== search) closeSuggest();
    });
})();

/* Autocomplete de localidad/provincia en el formulario de marcha. */
(function () {
    function initLocalidadAc(inputEl, suggestEl, campo) {
        if (!inputEl || !suggestEl) return;

        function close() { suggestEl.hidden = true; suggestEl.innerHTML = ''; }

        let timer, ctrl;
        inputEl.addEventListener('input', () => {
            const q = inputEl.value.trim();
            clearTimeout(timer);
            if (q.length < 2) { close(); return; }
            timer = setTimeout(async () => {
                if (ctrl) ctrl.abort();
                ctrl = new AbortController();
                try {
                    const res = await fetch(
                        '/api/localidad/fastSearch?campo=' + campo + '&q=' + encodeURIComponent(q),
                        { signal: ctrl.signal, credentials: 'same-origin' }
                    );
                    const data = await res.json();
                    const items = Array.isArray(data.data) ? data.data : [];
                    if (!items.length) { close(); return; }
                    suggestEl.innerHTML = '';
                    items.forEach((val) => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'suggest-item';
                        b.textContent = val;
                        b.addEventListener('click', () => {
                            inputEl.value = val;
                            close();
                            inputEl.focus();
                            // Si se selecciona una localidad, intentar rellenar la provincia.
                            if (campo === 'localidad') autoFillProvincia(val);
                        });
                        suggestEl.appendChild(b);
                    });
                    suggestEl.hidden = false;
                } catch (_) { /* abortado */ }
            }, 200);
        });

        document.addEventListener('mousedown', (e) => {
            if (!suggestEl.contains(e.target) && e.target !== inputEl) close();
        });
    }

    async function autoFillProvincia(localidad) {
        const provInput = document.getElementById('PROVINCIA');
        if (!provInput || provInput.value.trim() !== '') return;
        try {
            const res = await fetch(
                '/api/localidad/fastSearch?campo=provincia&q=' + encodeURIComponent(localidad),
                { credentials: 'same-origin' }
            );
            const data = await res.json();
            // Si hay exactamente una provincia para esa localidad, rellenarla.
            if (Array.isArray(data.data) && data.data.length === 1) {
                provInput.value = data.data[0];
            }
        } catch (_) { /* ignorar */ }
    }

    const locInput  = document.querySelector('[data-localidad-ac]');
    const locSuggest = document.getElementById('localidadSuggest');
    const provInput  = document.querySelector('[data-provincia-ac]');
    const provSuggest = document.getElementById('provinciaSuggest');

    initLocalidadAc(locInput, locSuggest, 'localidad');
    initLocalidadAc(provInput, provSuggest, 'provincia');
})();

/* Autocomplete de banda de estreno (single-select) en el formulario de marcha. */
(function () {
    const hidden  = document.getElementById('BANDA_ESTRENO');
    const search  = document.getElementById('bandaEstrenoSearch');
    const suggest = document.getElementById('bandaEstrenoSuggest');
    const clear   = document.getElementById('bandaEstrenoClear');
    if (!hidden || !search || !suggest) return;

    function close() { suggest.hidden = true; suggest.innerHTML = ''; }

    function setChosen(id, label) {
        hidden.value = id;
        search.value = label + ' (#' + id + ')';
        close();
        search.focus();
    }

    if (clear) {
        clear.addEventListener('click', () => {
            hidden.value = '';
            search.value = '';
            close();
        });
    }

    let timer, ctrl;
    search.addEventListener('input', () => {
        const q = search.value.trim();
        // Si el usuario borra el campo, limpiar el hidden también.
        if (q === '') hidden.value = '';
        clearTimeout(timer);
        if (q.length < 3) { close(); return; }
        timer = setTimeout(async () => {
            if (ctrl) ctrl.abort();
            ctrl = new AbortController();
            try {
                const res = await fetch('/api/banda/fastSearch?q=' + encodeURIComponent(q),
                    { signal: ctrl.signal, credentials: 'same-origin' });
                const data = await res.json();
                const rows = Array.isArray(data.data) ? data.data : [];
                if (!rows.length) { close(); return; }
                suggest.innerHTML = '';
                rows.forEach((r) => {
                    const label = r.LABEL || ('#' + r.ID_BANDA);
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'suggest-item';
                    b.textContent = label;
                    b.addEventListener('click', () => setChosen(r.ID_BANDA, label));
                    suggest.appendChild(b);
                });
                suggest.hidden = false;
            } catch (_) { /* abortado */ }
        }, 200);
    });

    document.addEventListener('mousedown', (e) => {
        if (!suggest.contains(e.target) && e.target !== search) close();
    });
})();

/* Comprobación de duplicados: avisa si ya existe una marcha con título similar (≥ 80 %)
   para los mismos autores. Se dispara al cambiar el título o los autores. */
(function () {
    const tituloInput = document.getElementById('TITULO');
    const alert = document.getElementById('duplicateAlert');
    if (!tituloInput || !alert) return;

    const excludeId = (typeof window._marchaExcludeId !== 'undefined') ? window._marchaExcludeId : 0;

    function getAutorIds() {
        return Array.from(document.querySelectorAll('input[name="autoresIds[]"]')).map((i) => i.value);
    }

    let timer, ctrl;

    function triggerCheck() {
        const titulo = tituloInput.value.trim();
        const ids = getAutorIds();
        clearTimeout(timer);
        alert.hidden = true;
        if (titulo.length < 3 || ids.length === 0) return;
        timer = setTimeout(async () => {
            if (ctrl) ctrl.abort();
            ctrl = new AbortController();
            try {
                const params = new URLSearchParams({ titulo, excludeId });
                ids.forEach((id) => params.append('autorIds[]', id));
                const res = await fetch('/api/marcha/checkDuplicate?' + params.toString(),
                    { signal: ctrl.signal, credentials: 'same-origin' });
                const data = await res.json();
                const hits = Array.isArray(data.data) ? data.data : [];
                if (!hits.length) { alert.hidden = true; return; }
                const links = hits.map((h) =>
                    '<a href="/dashboard/marcha/' + h.ID_MARCHA + '" target="_blank">' +
                    escHtml(h.TITULO) + ' (M-' + h.ID_MARCHA + ', ' + Math.round(h.sim * 100) + '% similitud)</a>'
                ).join('; ');
                alert.innerHTML = '⚠️ Posible duplicado — ya existe una marcha similar: ' + links + '. Revísala antes de continuar.';
                alert.hidden = false;
            } catch (_) { /* abortado o red */ }
        }, 400);
    }

    function escHtml(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    tituloInput.addEventListener('input', triggerCheck);

    // Se llama también desde el bloque de autores al añadir/quitar chips.
    window.triggerDuplicateCheck = triggerCheck;
})();
