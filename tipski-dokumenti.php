<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$currentPage = 'tipski-dokumenti';
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Типски Документи – Факта</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="min-h-screen">

    <?php include 'includes/nav.php'; ?>

    <div class="app-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
    <div class="max-w-6xl mx-auto px-4 pb-16">

        <div class="pt-10 pb-6 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-slate-800">Типски Документи</h1>
                <p class="text-sm text-slate-400 mt-1">Управувај со типски документи и шаблони</p>
            </div>
            <button id="btnNewTemplate" class="btn-new-client mt-1 flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14"/><path d="M12 5v14"/>
                </svg>
                Креирај шаблон
            </button>
        </div>

        <div class="tpl-search-wrap">
            <svg class="tpl-search-ico" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
            </svg>
            <input type="text" id="searchTemplates" class="field tpl-search-input" placeholder="Пребарај шаблони по назив или опис..." autocomplete="off">
        </div>

        <div id="tplGrid" class="tpl-grid"></div>

        <div id="tplEmpty" style="display:none" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="py-16 flex flex-col items-center gap-3 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#d6d0ca">
                    <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/>
                    <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
                </svg>
                <p class="text-sm text-slate-400">Сеуште нема шаблони</p>
                <button id="btnNewTemplateEmpty" class="text-sm font-medium text-slate-600 underline underline-offset-2" style="background:none;border:none;cursor:pointer;text-underline-offset:3px">Креирај го првиот шаблон</button>
            </div>
        </div>

    </div>
    </div>
    </div>

    <!-- Modal: create template -->
    <div id="tplCreateModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" style="max-width:24rem">
            <div class="modal-header">
                <span class="modal-title">Нов шаблон</span>
                <button class="modal-close" id="tplCreateClose">&times;</button>
            </div>
            <p style="font-size:0.8125rem;color:#78716c;margin-bottom:1rem;">Внеси назив и опис за новиот шаблон.</p>
            <input type="text" id="tplNameInput" class="field" placeholder="пр. Договор за работа..." style="margin-bottom:0.75rem;" autocomplete="off">
            <textarea id="tplDescInput" class="field" placeholder="Опис (опционално)..." rows="3" style="margin-bottom:0.875rem;resize:vertical;"></textarea>
            <div class="tpl-color-row">
                <span class="tpl-color-label">Боја на картичка</span>
                <div id="tplColorSwatches" class="tpl-swatches"></div>
            </div>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end">
                <button id="tplCreateCancel" class="btn-secondary">Откажи</button>
                <button id="tplCreateConfirm" class="btn-new-client">Креирај</button>
            </div>
        </div>
    </div>

    <!-- Modal: delete template confirmation -->
    <div id="tplDeleteModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" style="max-width:22rem">
            <div class="modal-header">
                <span class="modal-title">Избриши шаблон</span>
                <button class="modal-close" id="tplDeleteClose">&times;</button>
            </div>
            <p style="font-size:0.875rem;color:#57534e;margin-bottom:1.25rem;">Дали сте сигурни дека сакате да го избришете овој шаблон? Сите документи во него ќе бидат трајно избришани.</p>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end">
                <button id="tplDeleteCancel" class="btn-secondary">Откажи</button>
                <button id="tplDeleteConfirm" class="btn-new-client" style="background:#dc2626">Избриши</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/app.js"></script>
    <script>
    (function () {

        var MK_MONTHS = ['јануари','февруари','март','април','мај','јуни','јули','август','септември','октомври','ноември','декември'];

        function formatDate(dateStr) {
            if (!dateStr) return '';
            var d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            return d.getDate() + ' ' + MK_MONTHS[d.getMonth()] + ' ' + d.getFullYear();
        }

        function escapeHtml(str) {
            if (!str) return '';
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(str));
            return d.innerHTML;
        }

        // ── State, colors & search ──────────────────────────────────────────

        var allTemplates = [];
        var selectedCreateColor = '';

        var TPL_COLORS = [
            { key: '',       label: 'Без боја',   sw: '#ffffff' },
            { key: 'rose',   label: 'Розова',     sw: '#fb7185' },
            { key: 'amber',  label: 'Килибар',    sw: '#fbbf24' },
            { key: 'green',  label: 'Зелена',     sw: '#34d399' },
            { key: 'teal',   label: 'Тиркизна',   sw: '#2dd4bf' },
            { key: 'blue',   label: 'Сина',       sw: '#60a5fa' },
            { key: 'violet', label: 'Виолетова',  sw: '#a78bfa' },
            { key: 'slate',  label: 'Сива',       sw: '#94a3b8' }
        ];

        function swatchHtml(currentKey) {
            return TPL_COLORS.map(function (c) {
                var active = (c.key === (currentKey || '')) ? ' is-active' : '';
                var cls = 'tpl-swatch' + (c.key === '' ? ' tpl-swatch--none' : '') + active;
                var style = c.key === '' ? '' : ' style="background:' + c.sw + '"';
                return '<button type="button" class="' + cls + '" data-color="' + c.key + '" title="' + escapeHtml(c.label) + '"' + style + '></button>';
            }).join('');
        }

        function filterTemplates() {
            var q = (document.getElementById('searchTemplates').value || '').trim().toLowerCase();
            if (!q) return allTemplates;
            return allTemplates.filter(function (t) {
                return ((t.name || '').toLowerCase().indexOf(q) !== -1) ||
                       ((t.description || '').toLowerCase().indexOf(q) !== -1);
            });
        }

        // ── Load & render grid ──────────────────────────────────────────────

        function loadTemplates() {
            fetch('api/template_api.php?action=list')
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) return;
                    allTemplates = res.data || [];
                    renderGrid(filterTemplates());
                });
        }

        function renderGrid(templates) {
            var grid  = document.getElementById('tplGrid');
            var empty = document.getElementById('tplEmpty');

            if (!templates.length) {
                // Distinguish "no templates at all" from "no search results".
                grid.innerHTML = allTemplates.length
                    ? '<p class="list-msg" style="padding:1.5rem 0;grid-column:1/-1">Нема резултати за пребарувањето.</p>'
                    : '';
                empty.style.display = allTemplates.length ? 'none' : '';
                return;
            }

            empty.style.display = 'none';
            var html = '';
            templates.forEach(function (tpl) {
                var docCount = parseInt(tpl.doc_count, 10) || 0;
                var docWord = docCount === 1 ? 'документ' : 'документи';

                var descHtml = tpl.description
                    ? '<div class="tpl-card-desc">' + escapeHtml(tpl.description) + '</div>'
                    : '';

                // "Use template" opens the global draft workspace in place (no
                // navigation). Only shown when there's something to print.
                var useHtml = docCount
                    ? '<button type="button" class="btn-new-client tpl-card-use" data-use-tpl ' +
                          'data-id="' + tpl.id + '" data-name="' + escapeHtml(tpl.name).replace(/"/g, '&quot;') + '">' +
                          '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">' +
                              '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3z"/><path d="M5 3v4"/><path d="M19 17v4"/><path d="M3 5h4"/><path d="M17 19h4"/>' +
                          '</svg>' +
                          'Користи шаблон' +
                      '</button>'
                    : '';

                var colorAttr = tpl.color ? ' data-color="' + escapeHtml(tpl.color) + '"' : '';

                html += '<div class="tpl-card"' + colorAttr + ' data-id="' + tpl.id + '">' +
                    '<div class="tpl-thumb">' +
                        '<div class="tpl-sheet">' +
                            '<span class="tpl-thumb-bar"></span>' +
                            '<div class="tpl-thumb-lines">' +
                                '<span class="l-title"></span>' +
                                '<span></span><span></span><span class="short"></span>' +
                                '<span></span><span></span><span class="short"></span>' +
                            '</div>' +
                            '<span class="tpl-thumb-badge">' +
                                '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
                                docCount +
                            '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="tpl-card-body">' +
                        '<div class="tpl-card-name">' + escapeHtml(tpl.name) + '</div>' +
                        '<div class="tpl-card-meta">' + docCount + ' ' + docWord + ' &middot; ' + formatDate(tpl.created_at) + '</div>' +
                        descHtml +
                        '<div class="tpl-card-actions">' +
                            useHtml +
                            '<a href="pregled-shablon.php?id=' + tpl.id + '" class="btn-secondary tpl-card-open">Отвори &rarr;</a>' +
                            '<button class="btn-icon-color btn-color-tpl" data-id="' + tpl.id + '" title="Боја на картичка">' +
                                '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                                    '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>' +
                                '</svg>' +
                            '</button>' +
                            '<button class="btn-icon-danger btn-delete-tpl" data-id="' + tpl.id + '" data-name="' + escapeHtml(tpl.name) + '" title="Избриши шаблон">' +
                                '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                                    '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>' +
                                '</svg>' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            });
            grid.innerHTML = html;
        }

        // ── Create template modal ───────────────────────────────────────────

        function openCreateModal() {
            document.getElementById('tplNameInput').value = '';
            document.getElementById('tplDescInput').value = '';
            selectedCreateColor = '';
            document.getElementById('tplColorSwatches').innerHTML = swatchHtml('');
            document.getElementById('tplCreateModal').classList.add('open');
            document.getElementById('tplCreateModal').removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
            setTimeout(function () { document.getElementById('tplNameInput').focus(); }, 50);
        }

        // Pick a colour in the create modal.
        document.getElementById('tplColorSwatches').addEventListener('click', function (e) {
            var sw = e.target.closest('.tpl-swatch');
            if (!sw) return;
            selectedCreateColor = sw.getAttribute('data-color') || '';
            this.innerHTML = swatchHtml(selectedCreateColor);
        });

        // Live search.
        document.getElementById('searchTemplates').addEventListener('input', function () {
            renderGrid(filterTemplates());
        });

        function closeCreateModal() {
            document.getElementById('tplCreateModal').classList.remove('open');
            document.getElementById('tplCreateModal').setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }

        document.getElementById('btnNewTemplate').addEventListener('click', openCreateModal);
        document.getElementById('btnNewTemplateEmpty').addEventListener('click', openCreateModal);
        document.getElementById('tplCreateClose').addEventListener('click', closeCreateModal);
        document.getElementById('tplCreateCancel').addEventListener('click', closeCreateModal);

        document.getElementById('tplNameInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter')  { e.preventDefault(); document.getElementById('tplCreateConfirm').click(); }
            if (e.key === 'Escape') closeCreateModal();
        });

        document.getElementById('tplCreateModal').addEventListener('click', function (e) {
            if (e.target === this) closeCreateModal();
        });

        document.getElementById('tplCreateConfirm').addEventListener('click', function () {
            var name = document.getElementById('tplNameInput').value.trim();
            var description = document.getElementById('tplDescInput').value.trim();
            if (!name) { document.getElementById('tplNameInput').focus(); return; }

            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Се креира...';

            var params = new URLSearchParams({ action: 'create', name: name, description: description, color: selectedCreateColor });
            fetch('api/template_api.php', { method: 'POST', body: params })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        closeCreateModal();
                        loadTemplates();
                    } else {
                        alert(res.message || 'Грешка при креирање.');
                    }
                })
                .catch(function () { alert('Грешка при поврзување.'); })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = 'Креирај';
                });
        });

        // ── Delete template modal ───────────────────────────────────────────

        var pendingDeleteId = null;

        function openDeleteModal(id) {
            pendingDeleteId = id;
            document.getElementById('tplDeleteModal').classList.add('open');
            document.getElementById('tplDeleteModal').removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        }

        function closeDeleteModal() {
            pendingDeleteId = null;
            document.getElementById('tplDeleteModal').classList.remove('open');
            document.getElementById('tplDeleteModal').setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }

        document.getElementById('tplDeleteClose').addEventListener('click', closeDeleteModal);
        document.getElementById('tplDeleteCancel').addEventListener('click', closeDeleteModal);
        document.getElementById('tplDeleteModal').addEventListener('click', function (e) {
            if (e.target === this) closeDeleteModal();
        });

        document.getElementById('tplGrid').addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-delete-tpl');
            if (!btn) return;
            openDeleteModal(parseInt(btn.getAttribute('data-id'), 10));
        });

        // "Користи шаблон" → open the global draft workspace in place.
        document.getElementById('tplGrid').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-use-tpl]');
            if (!btn) return;
            var id   = parseInt(btn.getAttribute('data-id'), 10);
            var name = btn.getAttribute('data-name') || '';
            if (window.DraftWorkspace) window.DraftWorkspace.open(id, name);
        });

        // ── Recolor a card from the listing ─────────────────────────────────

        var colorPopover = null;

        function closeColorPopover() {
            if (!colorPopover) return;
            colorPopover.remove();
            colorPopover = null;
            document.removeEventListener('mousedown', onOutsidePopover, true);
        }
        function onOutsidePopover(e) {
            if (colorPopover && !colorPopover.contains(e.target) && !e.target.closest('.btn-color-tpl')) {
                closeColorPopover();
            }
        }

        function findTemplate(id) {
            for (var i = 0; i < allTemplates.length; i++) {
                if (parseInt(allTemplates[i].id, 10) === id) return allTemplates[i];
            }
            return null;
        }

        function recolorTemplate(id, key) {
            var tpl = findTemplate(id);
            if (!tpl) return;
            var params = new URLSearchParams({
                action: 'update', id: id, name: tpl.name, description: tpl.description || '', color: key
            });
            fetch('api/template_api.php', { method: 'POST', body: params })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) { alert(res.message || 'Грешка при зачувување.'); return; }
                    tpl.color = key;
                    var card = document.querySelector('.tpl-card[data-id="' + id + '"]');
                    if (card) {
                        if (key) card.setAttribute('data-color', key);
                        else     card.removeAttribute('data-color');
                    }
                })
                .catch(function () { alert('Грешка при поврзување.'); });
        }

        function openColorPopover(btn, id) {
            closeColorPopover();
            var tpl = findTemplate(id);
            colorPopover = document.createElement('div');
            colorPopover.className = 'tpl-color-popover';
            colorPopover.innerHTML = '<div class="tpl-swatches">' + swatchHtml(tpl ? tpl.color : '') + '</div>';
            document.body.appendChild(colorPopover);

            var r = btn.getBoundingClientRect();
            var w = colorPopover.offsetWidth || 188;
            colorPopover.style.top  = (r.bottom + 6) + 'px';
            colorPopover.style.left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8)) + 'px';

            colorPopover.querySelector('.tpl-swatches').addEventListener('click', function (e) {
                var sw = e.target.closest('.tpl-swatch');
                if (!sw) return;
                recolorTemplate(id, sw.getAttribute('data-color') || '');
                closeColorPopover();
            });
            setTimeout(function () { document.addEventListener('mousedown', onOutsidePopover, true); }, 0);
        }

        document.getElementById('tplGrid').addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-color-tpl');
            if (!btn) return;
            e.stopPropagation();
            openColorPopover(btn, parseInt(btn.getAttribute('data-id'), 10));
        });

        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeColorPopover(); });
        window.addEventListener('resize', closeColorPopover);
        window.addEventListener('scroll', closeColorPopover, true);

        document.getElementById('tplDeleteConfirm').addEventListener('click', function () {
            if (!pendingDeleteId) return;
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Се брише...';

            var params = new URLSearchParams({ action: 'delete', id: pendingDeleteId });
            fetch('api/template_api.php', { method: 'POST', body: params })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        closeDeleteModal();
                        loadTemplates();
                    } else {
                        alert(res.message || 'Грешка при бришење.');
                    }
                })
                .catch(function () { alert('Грешка при поврзување.'); })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = 'Избриши';
                });
        });

        // ── Init ────────────────────────────────────────────────────────────
        loadTemplates();

    }());
    </script>
</body>
</html>
