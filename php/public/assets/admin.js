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
    }

    box.addEventListener('click', (e) => {
        if (e.target.classList.contains('chip-x')) e.target.closest('.chip').remove();
    });

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

    document.addEventListener('click', (e) => {
        if (!suggest.contains(e.target) && e.target !== search) closeSuggest();
    });
})();
