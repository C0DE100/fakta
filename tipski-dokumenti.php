<?php $currentPage = 'tipski-dokumenti'; ?>
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
            <textarea id="tplDescInput" class="field" placeholder="Опис (опционално)..." rows="3" style="margin-bottom:1rem;resize:vertical;"></textarea>
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

        // ── Load & render grid ──────────────────────────────────────────────

        function loadTemplates() {
            fetch('api/template_api.php?action=list')
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) return;
                    renderGrid(res.data || []);
                });
        }

        function renderGrid(templates) {
            var grid  = document.getElementById('tplGrid');
            var empty = document.getElementById('tplEmpty');

            if (!templates.length) {
                grid.innerHTML = '';
                empty.style.display = '';
                return;
            }

            empty.style.display = 'none';
            var html = '';
            templates.forEach(function (tpl) {
                var docCount = parseInt(tpl.doc_count, 10) || 0;
                var docWord = docCount === 1 ? 'документ' : 'документи';
                var docs = tpl.documents || [];

                var descHtml = tpl.description
                    ? '<div class="tpl-card-desc">' + escapeHtml(tpl.description) + '</div>'
                    : '';

                var docsHtml;
                if (docs.length) {
                    docsHtml = '<ul class="tpl-card-docs">' +
                        docs.slice(0, 4).map(function (n) {
                            return '<li title="' + escapeHtml(n) + '">' +
                                '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
                                escapeHtml(n) + '</li>';
                        }).join('') +
                        (docs.length > 4 ? '<li class="tpl-card-docs-more">+' + (docs.length - 4) + ' уште</li>' : '') +
                        '</ul>';
                } else {
                    docsHtml = '<div class="tpl-card-docs-empty">Нема документи</div>';
                }

                html += '<div class="tpl-card">' +
                    '<div class="tpl-card-name">' + escapeHtml(tpl.name) + '</div>' +
                    '<div class="tpl-card-meta">' + docCount + ' ' + docWord + ' &middot; ' + formatDate(tpl.created_at) + '</div>' +
                    descHtml +
                    docsHtml +
                    '<div class="tpl-card-actions">' +
                        '<a href="pregled-shablon.php?id=' + tpl.id + '" class="btn-secondary tpl-card-open">Отвори &rarr;</a>' +
                        '<button class="btn-icon-danger btn-delete-tpl" data-id="' + tpl.id + '" data-name="' + escapeHtml(tpl.name) + '" title="Избриши шаблон">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                                '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>' +
                            '</svg>' +
                        '</button>' +
                    '</div>' +
                '</div>';
            });
            grid.innerHTML = html;
        }

        // ── Create template modal ───────────────────────────────────────────

        function openCreateModal() {
            document.getElementById('tplNameInput').value = '';
            document.getElementById('tplDescInput').value = '';
            document.getElementById('tplCreateModal').classList.add('open');
            document.getElementById('tplCreateModal').removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
            setTimeout(function () { document.getElementById('tplNameInput').focus(); }, 50);
        }

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

            var params = new URLSearchParams({ action: 'create', name: name, description: description });
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
