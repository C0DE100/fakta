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

        <div class="pt-10 pb-6">
            <!-- Root header -->
            <div id="rootHeader" class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-lg font-semibold text-slate-800">Типски Документи</h1>
                    <p class="text-sm text-slate-400 mt-1">Управувај со типски документи, папки и шаблони</p>
                </div>
                <div id="rootActions" class="flex items-center gap-2 mt-1 flex-shrink-0">
                    <button id="btnNewFolder" class="btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/><line x1="12" x2="12" y1="10" y2="16"/><line x1="9" x2="15" y1="13" y2="13"/>
                        </svg>
                        Нова папка
                    </button>
                    <button id="btnNewTemplate" class="btn-new-client">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14"/><path d="M12 5v14"/>
                        </svg>
                        Креирај шаблон
                    </button>
                </div>
            </div>

            <!-- Folder header (inside a folder) -->
            <div id="folderHeader" class="folder-view-head" style="display:none">
                <nav class="crumbs">
                    <button id="btnBackToRoot" class="crumb crumb-back" title="Типски Документи">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        Типски Документи
                    </button>
                    <span class="crumb-sep"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
                    <span class="crumb crumb-current">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                        <span id="crumbFolderName"></span>
                    </span>
                </nav>
                <div class="folder-view-titlebar">
                    <div class="folder-view-title">
                        <svg class="folder-view-ico" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                        <h1 id="folderTitleName" class="text-lg font-semibold text-slate-800"></h1>
                        <button id="btnFolderRename" class="btn-icon-edit" title="Преименувај папка">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        </button>
                        <span id="folderTitleCount" class="folder-view-count"></span>
                    </div>
                    <div id="folderActions" class="flex items-center gap-2 flex-shrink-0">
                        <button id="btnFolderDelete" class="btn-icon-danger" title="Избриши папка">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                        </button>
                        <button id="btnNewTemplateInFolder" class="btn-new-client">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                            Креирај шаблон
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="tplSearchWrap" class="tpl-search-wrap tpl-search-inline">
            <svg class="tpl-search-ico" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
            </svg>
            <input type="text" id="searchTemplates" class="field tpl-search-input" placeholder="Пребарај шаблони..." autocomplete="off">
        </div>

        <!-- Folders (root view only) -->
        <div id="folderSection" class="folder-section" style="display:none">
            <div class="folder-section-label">Папки</div>
            <div id="folderGrid" class="folder-grid"></div>
        </div>

        <div id="tplGridLabel" class="folder-section-label" style="display:none">Шаблони</div>
        <div id="tplGrid" class="tpl-grid"></div>

        <div id="tplEmpty" style="display:none" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="py-16 flex flex-col items-center gap-3 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#d6d0ca">
                    <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/>
                    <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
                </svg>
                <p id="tplEmptyMsg" class="text-sm text-slate-400">Сеуште нема шаблони</p>
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
            <label class="tpl-field-label" for="tplFolderSelect">Папка</label>
            <select id="tplFolderSelect" class="field" style="margin-bottom:0.875rem;"></select>
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

    <!-- Modal: create / rename folder -->
    <div id="folderModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" style="max-width:22rem">
            <div class="modal-header">
                <span class="modal-title" id="folderModalTitle">Нова папка</span>
                <button class="modal-close" id="folderModalClose">&times;</button>
            </div>
            <p style="font-size:0.8125rem;color:#78716c;margin-bottom:1rem;">Внеси назив за папката.</p>
            <input type="text" id="folderNameInput" class="field" placeholder="пр. Договори..." style="margin-bottom:0.875rem;" autocomplete="off">
            <div style="display:flex;gap:0.5rem;justify-content:flex-end">
                <button id="folderModalCancel" class="btn-secondary">Откажи</button>
                <button id="folderModalConfirm" class="btn-new-client">Зачувај</button>
            </div>
        </div>
    </div>

    <!-- Modal: delete folder confirmation -->
    <div id="folderDeleteModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" style="max-width:22rem">
            <div class="modal-header">
                <span class="modal-title">Избриши папка</span>
                <button class="modal-close" id="folderDeleteClose">&times;</button>
            </div>
            <p style="font-size:0.875rem;color:#57534e;margin-bottom:1.25rem;">Папката ќе биде избришана. Шаблоните во неа нема да се избришат — ќе бидат преместени надвор од папка.</p>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end">
                <button id="folderDeleteCancel" class="btn-secondary">Откажи</button>
                <button id="folderDeleteConfirm" class="btn-new-client" style="background:#dc2626">Избриши</button>
            </div>
        </div>
    </div>

    <!-- Modal: rename template -->
    <div id="tplRenameModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" style="max-width:22rem">
            <div class="modal-header">
                <span class="modal-title">Преименувај шаблон</span>
                <button class="modal-close" id="tplRenameClose">&times;</button>
            </div>
            <p style="font-size:0.8125rem;color:#78716c;margin-bottom:1rem;">Внеси нов назив за шаблонот.</p>
            <input type="text" id="tplRenameInput" class="field" placeholder="Назив на шаблонот..." style="margin-bottom:0.875rem;" autocomplete="off">
            <div style="display:flex;gap:0.5rem;justify-content:flex-end">
                <button id="tplRenameCancel" class="btn-secondary">Откажи</button>
                <button id="tplRenameConfirm" class="btn-new-client">Зачувај</button>
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

        function $id(id) { return document.getElementById(id); }

        // ── State, colors & search ──────────────────────────────────────────

        var allTemplates = [];
        var allFolders   = [];
        var currentFolderId = null;          // null = root
        var selectedCreateColor = '';

        // Permissions: praktikant may only edit/delete templates they created,
        // and may never move templates. Everyone else may manage all.
        var IS_PRAKTIKANT = (window.FAKTA_ROLE === 'praktikant');
        function canManageTpl(tpl) {
            return !IS_PRAKTIKANT || (!!tpl && tpl.created_by && tpl.created_by === window.FAKTA_UID);
        }
        function canMoveTpl() { return !IS_PRAKTIKANT; }
        function canManageFolder(f) {
            return !IS_PRAKTIKANT || (!!f && f.created_by && f.created_by === window.FAKTA_UID);
        }

        var FOLDER_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>';

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

        function folderById(id) {
            for (var i = 0; i < allFolders.length; i++) {
                if (allFolders[i].id === id) return allFolders[i];
            }
            return null;
        }

        function searchQuery() {
            return ($id('searchTemplates').value || '').trim().toLowerCase();
        }

        function matchesSearch(t, q) {
            return ((t.name || '').toLowerCase().indexOf(q) !== -1) ||
                   ((t.description || '').toLowerCase().indexOf(q) !== -1);
        }

        // Templates to show given the current folder + search.
        function templatesForView() {
            var q = searchQuery();
            return allTemplates.filter(function (t) {
                var fid = (t.folder_id === null || typeof t.folder_id === 'undefined') ? null : t.folder_id;
                if (q) {
                    // At root, search flattens across every folder; inside a
                    // folder, it stays scoped to that folder's templates.
                    if (currentFolderId !== null && fid !== currentFolderId) return false;
                    return matchesSearch(t, q);
                }
                return fid === currentFolderId;
            });
        }

        // ── Load & render ───────────────────────────────────────────────────

        function loadTemplates() {
            fetch('api/template_api.php?action=list')
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) return;
                    allTemplates = res.data || [];
                    allFolders   = res.folders || [];
                    // If we were inside a folder that no longer exists, pop to root.
                    if (currentFolderId !== null && !folderById(currentFolderId)) currentFolderId = null;
                    render();
                });
        }

        // Keep the URL in sync with the open folder, so returning from a
        // template (which links back to ?folder=N) lands in the right place
        // and a refresh stays put.
        function updateFolderUrl() {
            if (!(window.history && history.replaceState)) return;
            var u = 'tipski-dokumenti.php' + (currentFolderId !== null ? ('?folder=' + currentFolderId) : '');
            history.replaceState(null, '', u);
        }

        function render() {
            updateFolderUrl();
            var searching = !!searchQuery();
            var inFolder  = currentFolderId !== null;

            // Headers
            $id('rootHeader').style.display   = inFolder ? 'none' : '';
            $id('folderHeader').style.display = inFolder ? '' : 'none';

            // Dock the search to the left of the active header's buttons.
            var actions = $id(inFolder ? 'folderActions' : 'rootActions');
            var sw = $id('tplSearchWrap');
            if (actions && sw && sw.parentNode !== actions) actions.insertBefore(sw, actions.firstChild);
            if (inFolder) {
                var f = folderById(currentFolderId);
                var count = templatesForView().length;
                $id('folderTitleName').textContent = f ? f.name : '';
                $id('crumbFolderName').textContent = f ? f.name : '';
                $id('folderTitleCount').textContent = count + ' ' + (count === 1 ? 'шаблон' : 'шаблони');
                // Praktikant: only show rename/delete for folders they created.
                var canF = canManageFolder(f);
                $id('btnFolderRename').style.display = canF ? '' : 'none';
                $id('btnFolderDelete').style.display = canF ? '' : 'none';
            }

            renderFolders(searching, inFolder);
            renderGrid(templatesForView(), searching, inFolder);
        }

        function renderFolders(searching, inFolder) {
            var section = $id('folderSection');
            // Folders are only shown at the root and when not searching.
            if (inFolder || searching || !allFolders.length) {
                section.style.display = 'none';
                return;
            }
            section.style.display = '';
            var html = '';
            allFolders.forEach(function (f) {
                var count = f.tpl_count || 0;
                html += '<button type="button" class="folder-card" data-folder-id="' + f.id + '">' +
                    '<span class="folder-card-ico">' + FOLDER_SVG + '</span>' +
                    '<span class="folder-card-text">' +
                        '<span class="folder-card-name">' + escapeHtml(f.name) + '</span>' +
                        '<span class="folder-card-count">' + count + ' ' + (count === 1 ? 'шаблон' : 'шаблони') + '</span>' +
                    '</span>' +
                '</button>';
            });
            $id('folderGrid').innerHTML = html;
        }

        function renderGrid(templates, searching, inFolder) {
            var grid  = $id('tplGrid');
            var empty = $id('tplEmpty');
            var label = $id('tplGridLabel');

            // Show a "Шаблони" label only at root when folders exist above.
            label.style.display = (!inFolder && !searching && allFolders.length && templates.length) ? '' : 'none';

            if (!templates.length) {
                grid.innerHTML = '';
                if (searching) {
                    grid.innerHTML = '<p class="list-msg" style="padding:1.5rem 0;grid-column:1/-1">Нема резултати за пребарувањето.</p>';
                    empty.style.display = 'none';
                } else if (inFolder) {
                    $id('tplEmptyMsg').textContent = 'Папката е празна';
                    empty.style.display = '';
                } else if (!allFolders.length) {
                    // Root is truly empty — no templates AND no folders.
                    $id('tplEmptyMsg').textContent = 'Сеуште нема шаблони или папки';
                    empty.style.display = '';
                } else {
                    // Root has folders (just no loose templates) — show nothing.
                    empty.style.display = 'none';
                }
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

                var useHtml = docCount
                    ? '<button type="button" class="btn-new-client tpl-card-use" data-use-tpl ' +
                          'data-id="' + tpl.id + '" data-name="' + escapeHtml(tpl.name).replace(/"/g, '&quot;') + '">' +
                          '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">' +
                              '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>' +
                          '</svg>' +
                          'Преземи шаблон' +
                      '</button>'
                    : '';

                var colorAttr = tpl.color ? ' data-color="' + escapeHtml(tpl.color) + '"' : '';
                var manage = canManageTpl(tpl);

                // Color + delete only for templates the user may manage.
                var manageHtml = manage
                    ? '<button class="btn-icon-color btn-color-tpl" data-id="' + tpl.id + '" title="Боја на картичка">' +
                          '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                              '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>' +
                          '</svg>' +
                      '</button>' +
                      '<button class="btn-icon-danger btn-delete-tpl" data-id="' + tpl.id + '" data-name="' + escapeHtml(tpl.name) + '" title="Избриши шаблон">' +
                          '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                              '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>' +
                          '</svg>' +
                      '</button>'
                    : '';

                html += '<div class="tpl-card" draggable="' + (canMoveTpl() ? 'true' : 'false') + '"' + colorAttr + ' data-id="' + tpl.id + '">' +
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
                            manageHtml +
                        '</div>' +
                    '</div>' +
                '</div>';
            });
            grid.innerHTML = html;
        }

        // ── Folder navigation ───────────────────────────────────────────────

        $id('folderGrid').addEventListener('click', function (e) {
            var card = e.target.closest('.folder-card');
            if (!card) return;
            currentFolderId = parseInt(card.getAttribute('data-folder-id'), 10);
            $id('searchTemplates').value = '';
            render();
        });

        $id('btnBackToRoot').addEventListener('click', function () {
            currentFolderId = null;
            render();
        });

        // ── Drag & drop: move templates into / out of folders ───────────────
        var draggingTplId = null;

        function clearDropHighlights() {
            var els = document.querySelectorAll('.drag-over');
            for (var i = 0; i < els.length; i++) els[i].classList.remove('drag-over');
        }

        $id('tplGrid').addEventListener('dragstart', function (e) {
            var card = e.target.closest('.tpl-card');
            if (!card) return;
            draggingTplId = parseInt(card.getAttribute('data-id'), 10);
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', String(draggingTplId)); } catch (err) {}
            card.classList.add('dragging');
            document.body.classList.add('tpl-dragging');
        });
        $id('tplGrid').addEventListener('dragend', function (e) {
            var card = e.target.closest('.tpl-card');
            if (card) card.classList.remove('dragging');
            draggingTplId = null;
            document.body.classList.remove('tpl-dragging');
            clearDropHighlights();
        });

        // Folder cards = drop targets (root view) → move the template in.
        var folderGridEl = $id('folderGrid');
        folderGridEl.addEventListener('dragover', function (e) {
            if (draggingTplId === null) return;
            var card = e.target.closest('.folder-card');
            if (!card) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (!card.classList.contains('drag-over')) { clearDropHighlights(); card.classList.add('drag-over'); }
        });
        folderGridEl.addEventListener('dragleave', function (e) {
            var card = e.target.closest('.folder-card');
            if (card && !card.contains(e.relatedTarget)) card.classList.remove('drag-over');
        });
        folderGridEl.addEventListener('drop', function (e) {
            if (draggingTplId === null) return;
            var card = e.target.closest('.folder-card');
            if (!card) return;
            e.preventDefault();
            moveTemplate(draggingTplId, parseInt(card.getAttribute('data-folder-id'), 10));
            clearDropHighlights();
        });

        // Back link = drop target (inside a folder) → move the template out to root.
        var backBtn = $id('btnBackToRoot');
        backBtn.addEventListener('dragover', function (e) {
            if (draggingTplId === null) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            backBtn.classList.add('drag-over');
        });
        backBtn.addEventListener('dragleave', function () { backBtn.classList.remove('drag-over'); });
        backBtn.addEventListener('drop', function (e) {
            if (draggingTplId === null) return;
            e.preventDefault();
            moveTemplate(draggingTplId, 0);
            backBtn.classList.remove('drag-over');
        });

        // ── Create template modal ───────────────────────────────────────────

        function fillFolderSelect(selectedId) {
            var sel = $id('tplFolderSelect');
            var html = '<option value="0">Без папка</option>';
            allFolders.forEach(function (f) {
                html += '<option value="' + f.id + '">' + escapeHtml(f.name) + '</option>';
            });
            sel.innerHTML = html;
            sel.value = String(selectedId || 0);
        }

        function openModal(elId) {
            $id(elId).classList.add('open');
            $id(elId).removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        }
        function closeModal(elId) {
            $id(elId).classList.remove('open');
            $id(elId).setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }

        function openCreateModal() {
            $id('tplNameInput').value = '';
            $id('tplDescInput').value = '';
            selectedCreateColor = '';
            $id('tplColorSwatches').innerHTML = swatchHtml('');
            fillFolderSelect(currentFolderId || 0); // default to the folder you're in
            openModal('tplCreateModal');
            setTimeout(function () { $id('tplNameInput').focus(); }, 50);
        }
        function closeCreateModal() { closeModal('tplCreateModal'); }

        $id('tplColorSwatches').addEventListener('click', function (e) {
            var sw = e.target.closest('.tpl-swatch');
            if (!sw) return;
            selectedCreateColor = sw.getAttribute('data-color') || '';
            this.innerHTML = swatchHtml(selectedCreateColor);
        });

        $id('searchTemplates').addEventListener('input', render);

        $id('btnNewTemplate').addEventListener('click', openCreateModal);
        $id('btnNewTemplateInFolder').addEventListener('click', openCreateModal);
        $id('btnNewTemplateEmpty').addEventListener('click', openCreateModal);
        $id('tplCreateClose').addEventListener('click', closeCreateModal);
        $id('tplCreateCancel').addEventListener('click', closeCreateModal);

        $id('tplNameInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter')  { e.preventDefault(); $id('tplCreateConfirm').click(); }
            if (e.key === 'Escape') closeCreateModal();
        });
        $id('tplCreateModal').addEventListener('click', function (e) { if (e.target === this) closeCreateModal(); });

        $id('tplCreateConfirm').addEventListener('click', function () {
            var name = $id('tplNameInput').value.trim();
            var description = $id('tplDescInput').value.trim();
            var folderId = parseInt($id('tplFolderSelect').value, 10) || 0;
            if (!name) { $id('tplNameInput').focus(); return; }

            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Се креира...';

            var params = new URLSearchParams({ action: 'create', name: name, description: description, color: selectedCreateColor, folder_id: folderId });
            fetch('api/template_api.php', { method: 'POST', body: params })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        closeCreateModal();
                        // Jump into the folder the template landed in, so it's visible.
                        currentFolderId = folderId > 0 ? folderId : null;
                        loadTemplates();
                    } else {
                        alert(res.message || 'Грешка при креирање.');
                    }
                })
                .catch(function () { alert('Грешка при поврзување.'); })
                .finally(function () { btn.disabled = false; btn.textContent = 'Креирај'; });
        });

        // ── Folder create / rename modal ────────────────────────────────────

        var folderModalMode = 'create'; // 'create' | 'rename'

        function openFolderCreate() {
            folderModalMode = 'create';
            $id('folderModalTitle').textContent = 'Нова папка';
            $id('folderNameInput').value = '';
            openModal('folderModal');
            setTimeout(function () { $id('folderNameInput').focus(); }, 50);
        }
        function openFolderRename() {
            var f = folderById(currentFolderId);
            if (!f) return;
            folderModalMode = 'rename';
            $id('folderModalTitle').textContent = 'Преименувај папка';
            $id('folderNameInput').value = f.name;
            openModal('folderModal');
            setTimeout(function () { $id('folderNameInput').focus(); $id('folderNameInput').select(); }, 50);
        }
        function closeFolderModal() { closeModal('folderModal'); }

        $id('btnNewFolder').addEventListener('click', openFolderCreate);
        $id('btnFolderRename').addEventListener('click', openFolderRename);
        $id('folderModalClose').addEventListener('click', closeFolderModal);
        $id('folderModalCancel').addEventListener('click', closeFolderModal);
        $id('folderModal').addEventListener('click', function (e) { if (e.target === this) closeFolderModal(); });
        $id('folderNameInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter')  { e.preventDefault(); $id('folderModalConfirm').click(); }
            if (e.key === 'Escape') closeFolderModal();
        });

        $id('folderModalConfirm').addEventListener('click', function () {
            var name = $id('folderNameInput').value.trim();
            if (!name) { $id('folderNameInput').focus(); return; }
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Се зачувува...';

            var params, isRename = folderModalMode === 'rename';
            if (isRename) {
                params = new URLSearchParams({ action: 'folder_update', id: currentFolderId, name: name });
            } else {
                params = new URLSearchParams({ action: 'folder_create', name: name });
            }
            fetch('api/template_api.php', { method: 'POST', body: params })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) { closeFolderModal(); loadTemplates(); }
                    else { alert(res.message || 'Грешка.'); }
                })
                .catch(function () { alert('Грешка при поврзување.'); })
                .finally(function () { btn.disabled = false; btn.textContent = 'Зачувај'; });
        });

        // ── Folder delete ───────────────────────────────────────────────────

        function openFolderDelete() { openModal('folderDeleteModal'); }
        function closeFolderDelete() { closeModal('folderDeleteModal'); }

        $id('btnFolderDelete').addEventListener('click', openFolderDelete);
        $id('folderDeleteClose').addEventListener('click', closeFolderDelete);
        $id('folderDeleteCancel').addEventListener('click', closeFolderDelete);
        $id('folderDeleteModal').addEventListener('click', function (e) { if (e.target === this) closeFolderDelete(); });

        $id('folderDeleteConfirm').addEventListener('click', function () {
            if (currentFolderId === null) return;
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Се брише...';
            var params = new URLSearchParams({ action: 'folder_delete', id: currentFolderId });
            fetch('api/template_api.php', { method: 'POST', body: params })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) { closeFolderDelete(); currentFolderId = null; loadTemplates(); }
                    else { alert(res.message || 'Грешка при бришење.'); }
                })
                .catch(function () { alert('Грешка при поврзување.'); })
                .finally(function () { btn.disabled = false; btn.textContent = 'Избриши'; });
        });

        // ── Delete template modal ───────────────────────────────────────────

        var pendingDeleteId = null;

        function openDeleteModal(id) { pendingDeleteId = id; openModal('tplDeleteModal'); }
        function closeDeleteModal() { pendingDeleteId = null; closeModal('tplDeleteModal'); }

        $id('tplDeleteClose').addEventListener('click', closeDeleteModal);
        $id('tplDeleteCancel').addEventListener('click', closeDeleteModal);
        $id('tplDeleteModal').addEventListener('click', function (e) { if (e.target === this) closeDeleteModal(); });

        $id('tplGrid').addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-delete-tpl');
            if (!btn) return;
            openDeleteModal(parseInt(btn.getAttribute('data-id'), 10));
        });

        $id('tplDeleteConfirm').addEventListener('click', function () {
            if (!pendingDeleteId) return;
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Се брише...';
            var params = new URLSearchParams({ action: 'delete', id: pendingDeleteId });
            fetch('api/template_api.php', { method: 'POST', body: params })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) { closeDeleteModal(); loadTemplates(); }
                    else { alert(res.message || 'Грешка при бришење.'); }
                })
                .catch(function () { alert('Грешка при поврзување.'); })
                .finally(function () { btn.disabled = false; btn.textContent = 'Избриши'; });
        });

        // ── Rename template modal ───────────────────────────────────────────
        var renameTplId = null;

        function openRenameModal(id) {
            var tpl = findTemplate(id);
            if (!tpl) return;
            renameTplId = id;
            $id('tplRenameInput').value = tpl.name || '';
            openModal('tplRenameModal');
            setTimeout(function () { var el = $id('tplRenameInput'); el.focus(); el.select(); }, 50);
        }
        function closeRenameModal() { renameTplId = null; closeModal('tplRenameModal'); }

        $id('tplRenameClose').addEventListener('click', closeRenameModal);
        $id('tplRenameCancel').addEventListener('click', closeRenameModal);
        $id('tplRenameModal').addEventListener('click', function (e) { if (e.target === this) closeRenameModal(); });
        $id('tplRenameInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter')  { e.preventDefault(); $id('tplRenameConfirm').click(); }
            if (e.key === 'Escape') closeRenameModal();
        });

        $id('tplRenameConfirm').addEventListener('click', function () {
            var name = $id('tplRenameInput').value.trim();
            if (!name || !renameTplId) { $id('tplRenameInput').focus(); return; }
            var tpl = findTemplate(renameTplId);
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Се зачувува...';
            // No `color` key → the API preserves the existing colour.
            var params = new URLSearchParams({ action: 'update', id: renameTplId, name: name, description: (tpl && tpl.description) ? tpl.description : '' });
            fetch('api/template_api.php', { method: 'POST', body: params })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) { closeRenameModal(); loadTemplates(); }
                    else { alert(res.message || 'Грешка при зачувување.'); }
                })
                .catch(function () { alert('Грешка при поврзување.'); })
                .finally(function () { btn.disabled = false; btn.textContent = 'Зачувај'; });
        });

        // "Користи шаблон" → open the global draft workspace in place.
        $id('tplGrid').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-use-tpl]');
            if (!btn) return;
            var id   = parseInt(btn.getAttribute('data-id'), 10);
            var name = btn.getAttribute('data-name') || '';
            if (window.DraftWorkspace) window.DraftWorkspace.open(id, name);
        });

        // Clicking a card (except its buttons/links) opens it.
        $id('tplGrid').addEventListener('click', function (e) {
            if (e.target.closest('button, a')) return;
            var card = e.target.closest('.tpl-card');
            if (!card) return;
            var id = card.getAttribute('data-id');
            if (id) window.location.href = 'pregled-shablon.php?id=' + id;
        });

        // ── Popovers (color + move-to-folder) ───────────────────────────────

        var popover = null;

        function closePopover() {
            if (!popover) return;
            popover.remove();
            popover = null;
            document.removeEventListener('mousedown', onOutsidePopover, true);
        }
        function onOutsidePopover(e) {
            if (popover && !popover.contains(e.target) && !e.target.closest('.btn-color-tpl')) {
                closePopover();
            }
        }
        function positionPopover(btn) {
            var r = btn.getBoundingClientRect();
            var w = popover.offsetWidth || 200;
            popover.style.top  = (r.bottom + 6) + 'px';
            popover.style.left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8)) + 'px';
            setTimeout(function () { document.addEventListener('mousedown', onOutsidePopover, true); }, 0);
        }
        function positionPopoverXY(x, y) {
            var w = popover.offsetWidth  || 200;
            var h = popover.offsetHeight || 0;
            popover.style.left = Math.max(8, Math.min(x, window.innerWidth  - w - 8)) + 'px';
            popover.style.top  = Math.max(8, Math.min(y, window.innerHeight - h - 8)) + 'px';
            setTimeout(function () { document.addEventListener('mousedown', onOutsidePopover, true); }, 0);
        }

        function findTemplate(id) {
            for (var i = 0; i < allTemplates.length; i++) {
                if (parseInt(allTemplates[i].id, 10) === id) return allTemplates[i];
            }
            return null;
        }

        // -- Recolor --
        function recolorTemplate(id, key) {
            var tpl = findTemplate(id);
            if (!tpl) return;
            var params = new URLSearchParams({ action: 'update', id: id, name: tpl.name, description: tpl.description || '', color: key });
            fetch('api/template_api.php', { method: 'POST', body: params })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) { alert(res.message || 'Грешка при зачувување.'); return; }
                    tpl.color = key;
                    var card = document.querySelector('.tpl-card[data-id="' + id + '"]');
                    if (card) { key ? card.setAttribute('data-color', key) : card.removeAttribute('data-color'); }
                })
                .catch(function () { alert('Грешка при поврзување.'); });
        }

        function openColorPopover(btn, id) {
            closePopover();
            var tpl = findTemplate(id);
            popover = document.createElement('div');
            popover.className = 'tpl-color-popover';
            popover.innerHTML = '<div class="tpl-swatches">' + swatchHtml(tpl ? tpl.color : '') + '</div>';
            document.body.appendChild(popover);
            positionPopover(btn);
            popover.querySelector('.tpl-swatches').addEventListener('click', function (e) {
                var sw = e.target.closest('.tpl-swatch');
                if (!sw) return;
                recolorTemplate(id, sw.getAttribute('data-color') || '');
                closePopover();
            });
        }

        // -- Move to folder --
        function moveTemplate(id, folderId) {
            var params = new URLSearchParams({ action: 'move', id: id, folder_id: folderId });
            fetch('api/template_api.php', { method: 'POST', body: params })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) { alert(res.message || 'Грешка.'); return; }
                    loadTemplates();
                })
                .catch(function () { alert('Грешка при поврзување.'); });
        }

        function openMovePopover(id, x, y) {
            closePopover();
            var tpl = findTemplate(id);
            var curFid = tpl && tpl.folder_id ? tpl.folder_id : 0;
            popover = document.createElement('div');
            popover.className = 'tpl-move-popover';
            var items = '<button type="button" class="tpl-move-item' + (curFid === 0 ? ' is-active' : '') + '" data-folder="0">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
                'Без папка</button>';
            allFolders.forEach(function (f) {
                items += '<button type="button" class="tpl-move-item' + (curFid === f.id ? ' is-active' : '') + '" data-folder="' + f.id + '">' +
                    '<span class="tpl-move-ico">' + FOLDER_SVG + '</span>' + escapeHtml(f.name) + '</button>';
            });
            if (!allFolders.length) {
                items += '<div class="tpl-move-empty">Нема папки. Креирај папка прво.</div>';
            }
            popover.innerHTML = '<div class="tpl-move-head">Премести во</div>' + items;
            document.body.appendChild(popover);
            positionPopoverXY(x, y);
            popover.addEventListener('click', function (e) {
                var item = e.target.closest('.tpl-move-item');
                if (!item) return;
                moveTemplate(id, parseInt(item.getAttribute('data-folder'), 10));
                closePopover();
            });
        }

        $id('tplGrid').addEventListener('click', function (e) {
            var colorBtn = e.target.closest('.btn-color-tpl');
            if (colorBtn) { e.stopPropagation(); openColorPopover(colorBtn, parseInt(colorBtn.getAttribute('data-id'), 10)); return; }
        });

        // ── Right-click context menu (rename / move) ────────────────────────
        function openContextMenu(x, y, id) {
            var tpl = findTemplate(id);
            var canRename = canManageTpl(tpl); // rename = edit
            var canMove   = canMoveTpl();
            if (!canRename && !canMove) return; // nothing the user may do
            closePopover();
            popover = document.createElement('div');
            popover.className = 'ctx-menu';
            popover.innerHTML =
                (canRename ?
                '<button type="button" class="ctx-item" data-act="rename">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>' +
                    'Преименувај шаблон' +
                '</button>' : '') +
                (canMove ?
                '<button type="button" class="ctx-item" data-act="move">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>' +
                    'Премести во папка' +
                '</button>' : '');
            document.body.appendChild(popover);
            positionPopoverXY(x, y);
            popover.addEventListener('click', function (e) {
                var item = e.target.closest('.ctx-item');
                if (!item) return;
                var act = item.getAttribute('data-act');
                closePopover();
                if (act === 'rename') openRenameModal(id);
                else if (act === 'move') openMovePopover(id, x, y);
            });
        }

        $id('tplGrid').addEventListener('contextmenu', function (e) {
            var card = e.target.closest('.tpl-card');
            if (!card) return;
            e.preventDefault();
            openContextMenu(e.clientX, e.clientY, parseInt(card.getAttribute('data-id'), 10));
        });

        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePopover(); });
        window.addEventListener('resize', closePopover);
        window.addEventListener('scroll', closePopover, true);

        // ── Init ────────────────────────────────────────────────────────────
        (function () {
            var f = parseInt(new URLSearchParams(window.location.search).get('folder'), 10);
            if (f > 0) currentFolderId = f; // restored if the folder still exists
        }());
        loadTemplates();

    }());
    </script>
</body>
</html>
