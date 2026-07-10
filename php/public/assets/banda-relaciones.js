/* Selector de "otra banda" (single-select) + fecha fin condicional al tipo juvenil.
   JS mínimo sin dependencias, mismo patrón que assets/admin.js. */
(function () {
    const search = document.getElementById('otraBandaSearch');
    const hidden = document.getElementById('otraBandaId');
    const suggest = document.getElementById('otraBandaSuggest');
    const chosen = document.getElementById('otraBandaChosen');
    if (!search || !hidden || !suggest) return;

    function closeSuggest() { suggest.hidden = true; suggest.innerHTML = ''; }

    function setChosen(id, label) {
        hidden.value = id;
        if (chosen) chosen.textContent = label + ' (#' + id + ')';
        search.value = '';
        closeSuggest();
        search.focus();
    }

    let timer, controller;
    search.addEventListener('input', () => {
        const q = search.value.trim();
        clearTimeout(timer);
        if (q.length < 3) { closeSuggest(); return; }
        timer = setTimeout(async () => {
            if (controller) controller.abort();
            controller = new AbortController();
            try {
                const res = await fetch('/api/banda/fastSearch?q=' + encodeURIComponent(q),
                    { signal: controller.signal, credentials: 'same-origin' });
                const data = await res.json();
                const rows = Array.isArray(data.data) ? data.data : [];
                if (!rows.length) { closeSuggest(); return; }
                suggest.innerHTML = '';
                rows.forEach((r) => {
                    const label = r.LABEL || ('#' + r.ID_BANDA);
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'suggest-item';
                    b.textContent = label;
                    b.addEventListener('click', () => setChosen(r.ID_BANDA, r.LABEL || ('#' + r.ID_BANDA)));
                    suggest.appendChild(b);
                });
                suggest.hidden = false;
            } catch (e) { /* abortado o red: ignorar */ }
        }, 200);
    });

    document.addEventListener('click', (e) => {
        if (!suggest.contains(e.target) && e.target !== search) closeSuggest();
    });

    // La fecha de fin solo tiene sentido para relaciones "juvenil".
    const tipo = document.getElementById('tipo');
    const finWrap = document.getElementById('fechaFinWrap');
    function syncFin() {
        if (finWrap) finWrap.style.display = (tipo && tipo.value === 'juvenil') ? '' : 'none';
    }
    if (tipo) { tipo.addEventListener('change', syncFin); syncFin(); }
})();
