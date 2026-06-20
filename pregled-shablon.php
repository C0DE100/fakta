<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$currentPage = 'tipski-dokumenti';

$companyId  = current_company_id();
$templateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$templateId) { header('Location: tipski-dokumenti.php'); exit; }

$pdo = $GLOBALS['fakta_db']->getConnection();

$stmt = $pdo->prepare('SELECT * FROM templates WHERE id = ? AND company_id = ?');
$stmt->execute([$templateId, $companyId]);
$template = $stmt->fetch();
if (!$template) { header('Location: tipski-dokumenti.php'); exit; }

// Praktikant may only edit templates (and their documents) they created.
$canManageTemplate = current_role() !== 'praktikant'
    || (int) ($template['created_by'] ?? 0) === (int) (current_user()['id'] ?? -1);

$stmt = $pdo->prepare('SELECT d.*, u.name AS created_by_name
                       FROM documents d
                       LEFT JOIN users u ON u.id = d.created_by
                       WHERE d.template_id = ? AND d.company_id = ?
                       ORDER BY d.sort_order ASC, d.id ASC');
$stmt->execute([$templateId, $companyId]);
$docs = $stmt->fetchAll();
foreach ($docs as &$doc) {
    $doc['pages']     = json_decode($doc['pages'],     true) ?: [];
    $doc['variables'] = json_decode($doc['variables'], true) ?: [];
}
unset($doc);

// Return to the folder this template lives in (if any), so "Назад" lands where
// the user was browsing rather than always at the root.
$backUrl = 'tipski-dokumenti.php' . (!empty($template['folder_id']) ? ('?folder=' . (int) $template['folder_id']) : '');
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($template['name']) ?> – Факта</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="min-h-screen">

    <?php include 'includes/nav.php'; ?>

    <div class="app-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
    <div class="max-w-6xl mx-auto px-4 pb-16">

        <!-- Top bar -->
        <div class="pt-8 pb-6 flex items-start gap-4 flex-wrap">
            <a href="<?= htmlspecialchars($backUrl) ?>" class="btn-secondary" style="flex-shrink:0;margin-top:0.125rem">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>
                </svg>
                Назад
            </a>
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:0.5rem;min-width:0">
                    <h1 id="tplNameHeading" class="text-lg font-semibold text-slate-800" style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($template['name']) ?></h1>
                    <?php if ($canManageTemplate): ?>
                    <button id="btnEditTemplate" class="btn-icon-edit" title="Уреди назив и опис">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                        </svg>
                    </button>
                    <?php endif; ?>
                </div>
                <p id="tplDescDisplay" class="tpl-view-desc"<?= ($template['description'] ?? '') !== '' ? '' : ' style="display:none"' ?>><?= htmlspecialchars($template['description'] ?? '') ?></p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-shrink:0;margin-top:0.125rem;align-items:center">
                <div id="docSearchWrap" class="tpl-search-wrap tpl-search-inline" style="display:none">
                    <svg class="tpl-search-ico" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                    </svg>
                    <input type="text" id="searchDocs" class="field tpl-search-input" placeholder="Пребарај документи..." autocomplete="off">
                </div>
                <!-- Adding documents is allowed for everyone, including praktikant. -->
                <button id="btnImportDoc" class="btn-secondary" title="Импортирај .docx документ со [полиња]">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>
                    </svg>
                    Импортирај
                    <span class="import-ext-badge">.docx</span>
                </button>
                <a href="kreraj-dokument.php?template_id=<?= $templateId ?>" class="btn-new-client">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14"/><path d="M12 5v14"/>
                    </svg>
                    Нов документ
                </a>
                <button id="btnUseTemplate" class="btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" x2="12" y1="15" y2="3"/>
                    </svg>
                    Користи шаблон
                </button>
            </div>
        </div>

        <!-- Document cards grid -->
        <div id="docCardsGrid" class="doc-cards-grid"></div>

        <!-- Empty state -->
        <div id="docCardsEmpty" style="display:none" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="py-16 flex flex-col items-center gap-3 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#d6d0ca">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                <p class="text-sm text-slate-400">Овој шаблон нема документи</p>
                <a href="kreraj-dokument.php?template_id=<?= $templateId ?>" class="text-sm font-medium text-slate-600 underline underline-offset-2" style="text-underline-offset:3px">Додај го првиот документ</a>
            </div>
        </div>

    </div>
    </div>
    </div>

    <!-- Print zone (hidden; used for sequential printing) -->
    <div id="printZone"></div>


    <!-- Modal: fill variable values (shared for single doc + full template) -->
    <div id="useModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" style="max-width:28rem">
            <div class="modal-header">
                <span id="useModalTitle" class="modal-title">Вредности на Променливите</span>
                <button class="modal-close" id="useModalClose">&times;</button>
            </div>
            <p style="font-size:0.8125rem;color:#78716c;margin-bottom:1rem;">Внеси вредност за секоја променлива. Ќе бидат вметнати во PDF, но нема да се зачуваат.</p>
            <div id="useModalFields" style="max-height:60vh;overflow-y:auto;padding-right:0.25rem"></div>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem">
                <button id="useModalCancel" class="btn-secondary">Откажи</button>
                <button id="useModalConfirm" class="btn-new-client">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" x2="12" y1="15" y2="3"/>
                    </svg>
                    Преземи
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: import a Word/PDF file with [placeholders] -->
    <div id="importModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" style="max-width:28rem">
            <div class="modal-header">
                <span class="modal-title">Импортирај документ</span>
                <button class="modal-close" id="importClose">&times;</button>
            </div>
            <p style="font-size:0.8125rem;color:#78716c;margin-bottom:1rem;">
                Прикачи <strong>.docx</strong> (Word) датотека.
                Местата што сакаш да се пополнуваат при преземање означи ги со загради во самиот документ, пр.
                <code>[име]</code>, <code>[број]</code>.
            </p>
            <input type="text" id="importNameInput" class="field" placeholder="Назив на документот..." style="margin-bottom:0.75rem;" autocomplete="off">
            <label id="importDrop" for="importFileInput" class="import-drop">
                <input type="file" id="importFileInput" accept=".docx" style="display:none">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:#a8a29e">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>
                </svg>
                <span id="importDropText" style="font-size:0.8125rem;color:#78716c">Кликни или повлечи датотека тука</span>
            </label>
            <p id="importStatus" style="font-size:0.8125rem;margin:0.75rem 0 0;display:none"></p>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem">
                <button id="importCancel" class="btn-secondary">Откажи</button>
                <button id="importConfirm" class="btn-new-client">Импортирај</button>
            </div>
        </div>
    </div>

    <!-- Modal: fill an imported file's [placeholders] with a live doc preview -->
    <div id="fillPreviewModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box fill-preview-box">
            <div class="modal-header">
                <span class="modal-title" id="fillPreviewTitle">Пополни</span>
                <button class="modal-close" id="fillPreviewClose">&times;</button>
            </div>
            <div class="fill-preview-body">
                <aside class="fill-preview-side">
                    <div class="fill-preview-side-head">Вредности на полињата</div>
                    <div id="fillPreviewFields" class="fill-preview-fields"></div>
                </aside>
                <main class="fill-preview-doc-wrap">
                    <div id="fillPreviewDoc" class="fill-preview-doc">
                        <p class="fill-preview-msg">Се вчитува преглед…</p>
                    </div>
                </main>
            </div>
            <div class="fill-preview-foot">
                <button id="fillPreviewCancel" class="btn-secondary">Откажи</button>
                <button id="fillPreviewConfirm" class="btn-new-client">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    Преземи
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: delete document confirmation -->
    <div id="docDeleteModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" style="max-width:22rem">
            <div class="modal-header">
                <span class="modal-title">Избриши документ</span>
                <button class="modal-close" id="docDeleteClose">&times;</button>
            </div>
            <p style="font-size:0.875rem;color:#57534e;margin-bottom:1.25rem;">Дали сте сигурни дека сакате да го избришете овој документ?</p>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end">
                <button id="docDeleteCancel" class="btn-secondary">Откажи</button>
                <button id="docDeleteConfirm" class="btn-new-client" style="background:#dc2626">Избриши</button>
            </div>
        </div>
    </div>

    <!-- Modal: edit template (name + description) -->
    <div id="tplEditModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" style="max-width:24rem">
            <div class="modal-header">
                <span class="modal-title">Уреди шаблон</span>
                <button class="modal-close" id="tplEditClose">&times;</button>
            </div>
            <p style="font-size:0.8125rem;color:#78716c;margin-bottom:1rem;">Промени го називот и описот на шаблонот.</p>
            <input type="text" id="tplEditName" class="field" placeholder="Назив на шаблонот..." style="margin-bottom:0.75rem;" autocomplete="off">
            <textarea id="tplEditDesc" class="field" placeholder="Опис (опционално)..." rows="3" style="margin-bottom:1rem;resize:vertical;"></textarea>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end">
                <button id="tplEditCancel" class="btn-secondary">Откажи</button>
                <button id="tplEditConfirm" class="btn-new-client">Зачувај</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.7/quill.js"></script>
    <script src="js/app.js"></script>

    <script>
    var TEMPLATE = <?= json_encode($template) ?>;
    var DOCS     = <?= json_encode($docs) ?>;
    var CAN_MANAGE = <?= $canManageTemplate ? 'true' : 'false' ?>;
    var CURRENT_UNAME = <?= json_encode(current_user()['name'] ?? '') ?>;

    // Praktikant may edit/delete only documents they created themselves.
    var IS_PRAKTIKANT = (window.FAKTA_ROLE === 'praktikant');
    function canManageDoc(doc) {
        return !IS_PRAKTIKANT || (doc && doc.created_by && parseInt(doc.created_by, 10) === window.FAKTA_UID);
    }
    </script>

    <script>
    (function () {

        /* ─────────────────────────────────────────────
           Variable blot (read-only preview)
        ───────────────────────────────────────────── */
        var Embed = Quill.import('blots/embed');
        class VariableBlot extends Embed {
            static create(value) {
                var node = super.create();
                node.className = 'ql-variable';
                node.setAttribute('data-var', value);
                node.setAttribute('contenteditable', 'false');
                node.textContent = value;
                return node;
            }
            static value(node) { return node.getAttribute('data-var'); }
        }
        VariableBlot.blotName  = 'variable';
        VariableBlot.tagName   = 'span';
        VariableBlot.className = 'ql-variable';
        Quill.register(VariableBlot);

        /* ─────────────────────────────────────────────
           Helpers
        ───────────────────────────────────────────── */
        function escapeHtml(str) {
            if (!str) return '';
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(str));
            return d.innerHTML;
        }

        /* ─────────────────────────────────────────────
           Merge a document's pages[] into one continuous stream per column.
           New docs save a single page; legacy multi-page docs (with old
           header/footer/page-break markers) concatenate into one flow.
        ───────────────────────────────────────────── */
        function mergePages(pages) {
            var single = null, left = null, right = null, header = '', footer = '';
            (pages || []).forEach(function (p) {
                if (p.single) single = single ? { ops: single.ops.concat(p.single.ops) } : { ops: p.single.ops.slice() };
                if (p.left)   left   = left   ? { ops: left.ops.concat(p.left.ops) }     : { ops: p.left.ops.slice() };
                if (p.right)  right  = right  ? { ops: right.ops.concat(p.right.ops) }   : { ops: p.right.ops.slice() };
                if (!header && p.header) header = p.header;
                if (!footer && p.footer) footer = p.footer;
            });
            return { single: single, left: left, right: right, header: header, footer: footer };
        }

        /* ─────────────────────────────────────────────
           Variable helpers
           doc.variables is a plain array saved at write-time,
           e.g. ["ime", "datum", "firma"]
        ───────────────────────────────────────────── */

        // Returns { varname: true } for a single doc
        function getVarsFromDoc(doc) {
            var set = {};
            (doc.variables || []).forEach(function (v) { set[v] = true; });
            return set;
        }

        // Returns { varname: [docName, ...] } across many docs
        function getVarsFromDocs(docs) {
            var map = {};
            docs.forEach(function (doc) {
                (doc.variables || []).forEach(function (v) {
                    if (!map[v]) map[v] = [];
                    if (map[v].indexOf(doc.name) === -1) map[v].push(doc.name);
                });
            });
            return map;
        }

        /* ─────────────────────────────────────────────
           Render doc cards
        ───────────────────────────────────────────── */
        function renderDocCards() {
            var grid  = document.getElementById('docCardsGrid');
            var empty = document.getElementById('docCardsEmpty');
            var searchWrap = document.getElementById('docSearchWrap');

            // The search is only useful once the template has documents.
            if (searchWrap) searchWrap.style.display = DOCS.length ? '' : 'none';

            if (!DOCS.length) {
                grid.innerHTML = '';
                empty.style.display = '';
                return;
            }

            empty.style.display = 'none';

            var q = (document.getElementById('searchDocs').value || '').trim().toLowerCase();
            var docs = q
                ? DOCS.filter(function (d) { return (d.name || '').toLowerCase().indexOf(q) !== -1; })
                : DOCS;

            if (!docs.length) {
                grid.innerHTML = '<p class="list-msg" style="padding:1.5rem 0;grid-column:1/-1">Нема документи за пребарувањето.</p>';
                return;
            }

            // Document file icon so a card clearly reads as a document.
            var FILE_ICO = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';

            var DOWNLOAD_ICO = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>';
            var DELETE_ICO   = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>';
            var IMPORT_TAG_ICO = '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>';
            var CREATE_TAG_ICO = '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
            var USER_ICO = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

            var html = '';
            docs.forEach(function (doc) {
                var isImported = doc.kind === 'imported';
                var deleteBtn = canManageDoc(doc)
                    ? '<button class="btn-icon-danger btn-delete-doc" data-id="' + doc.id + '" title="Избриши документ">' + DELETE_ICO + '</button>'
                    : '';
                var dlTitle = 'Преземи ' + ((isImported && doc.file_ext) ? doc.file_ext.toUpperCase() : 'PDF');

                // Top label: imported vs created-in-editor.
                var typeRow = '<div class="doc-card-tagrow">' +
                    '<span class="doc-type-badge ' + (isImported ? 'is-imported' : 'is-created') + '">' +
                        (isImported ? IMPORT_TAG_ICO + 'Импортиран' : CREATE_TAG_ICO + 'Креиран') +
                    '</span></div>';

                // Bottom: who created the document.
                var creator = doc.created_by_name ? escapeHtml(doc.created_by_name) : 'Непознат корисник';
                var creatorRow = '<div class="doc-card-creator">' + USER_ICO + '<span>' + creator + '</span></div>';

                var titleBar =
                    '<div class="doc-card-title-bar">' +
                        '<span class="doc-card-ico" aria-hidden="true">' + FILE_ICO + '</span>' +
                        '<span class="doc-card-name" title="' + escapeHtml(doc.name) + '">' + escapeHtml(doc.name) + '</span>' +
                        '<button class="btn-icon-color btn-download-doc" data-id="' + doc.id + '" title="' + dlTitle + '">' + DOWNLOAD_ICO + '</button>' +
                        deleteBtn +
                    '</div>';

                if (doc.kind === 'imported') {
                    var ext   = (doc.file_ext || 'docx').toLowerCase();
                    var nPh   = (doc.variables || []).length;
                    var phTxt = nPh ? (nPh + ' ' + (nPh === 1 ? 'поле за пополнување' : 'полиња за пополнување')) : 'нема полиња';
                    html += '<div class="doc-card doc-card-imported" data-doc-id="' + doc.id + '" data-kind="imported">' +
                        typeRow +
                        titleBar +
                        '<div class="doc-preview-area">' +
                            '<div class="doc-imported-body">' +
                                '<svg class="di-ico" xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
                                '<span class="doc-format-badge" data-ext="' + escapeHtml(ext) + '">' + escapeHtml(ext) + '</span>' +
                                '<span class="doc-imported-meta">' + escapeHtml(phTxt) + '</span>' +
                            '</div>' +
                        '</div>' +
                        creatorRow +
                        '<div class="doc-card-footer">' +
                            '<button class="btn-new-client btn-download-doc" data-id="' + doc.id + '" style="flex:1;justify-content:center">Преземи</button>' +
                        '</div>' +
                    '</div>';
                    return;
                }

                html += '<div class="doc-card" data-doc-id="' + doc.id + '">' +
                    typeRow +
                    titleBar +
                    '<div class="doc-preview-area">' +
                        '<div class="doc-preview-inner" id="preview-' + doc.id + '"></div>' +
                    '</div>' +
                    creatorRow +
                    '<div class="doc-card-footer">' +
                        (canManageDoc(doc)
                            ? '<a href="kreraj-dokument.php?doc_id=' + doc.id + '&template_id=' + TEMPLATE.id + '" class="btn-new-client doc-card-open" style="flex:1;justify-content:center">Отвори</a>'
                            : '<span class="doc-card-readonly" style="flex:1;text-align:center">Само за преглед</span>') +
                    '</div>' +
                '</div>';
            });
            grid.innerHTML = html;

            // Init read-only Quill previews (editor docs only)
            docs.forEach(function (doc) {
                if (doc.kind === 'imported') return;
                var el = document.getElementById('preview-' + doc.id);
                if (!el) return;
                var page = mergePages(doc.pages);
                if (!page.single && !page.left && !page.right) return;

                if (parseInt(doc.is_split)) {
                    // Two-column preview so it's clear the document is split.
                    el.classList.add('doc-preview-split');
                    var leftEl  = document.createElement('div');
                    var divEl   = document.createElement('div');
                    var rightEl = document.createElement('div');
                    leftEl.className  = 'doc-preview-col';
                    rightEl.className = 'doc-preview-col';
                    divEl.className   = 'doc-preview-col-divider';
                    el.appendChild(leftEl);
                    el.appendChild(divEl);
                    el.appendChild(rightEl);

                    var qL = new Quill(leftEl,  { readOnly: true, theme: 'snow', modules: { toolbar: false } });
                    var qR = new Quill(rightEl, { readOnly: true, theme: 'snow', modules: { toolbar: false } });
                    if (page.left)  qL.setContents(page.left,  'silent');
                    if (page.right) qR.setContents(page.right, 'silent');
                } else {
                    var q = new Quill(el, { readOnly: true, theme: 'snow', modules: { toolbar: false } });
                    if (page.single) q.setContents(page.single, 'silent');
                }
            });
        }

        /* ─────────────────────────────────────────────
           applyVarsToDelta — replace variable ops
        ───────────────────────────────────────────── */
        function applyVarsToDelta(delta, values) {
            if (!delta || !delta.ops) return delta;
            var newOps = [];
            delta.ops.forEach(function (op) {
                if (op.insert && typeof op.insert === 'object' && op.insert.variable) {
                    var varName = op.insert.variable;
                    var val = (values && values[varName] !== undefined && values[varName] !== '')
                              ? values[varName]
                              : '[' + varName + ']';
                    var newOp = { insert: val };
                    if (op.attributes) newOp.attributes = op.attributes;
                    newOps.push(newOp);
                } else {
                    newOps.push(op);
                }
            });
            return { ops: newOps };
        }

        /* ─────────────────────────────────────────────
           buildPrintZone
        ───────────────────────────────────────────── */
        function buildPrintZone(doc, values) {
            var zone = document.getElementById('printZone');
            zone.innerHTML = '';
            zone.appendChild(buildPrintTable(mergePages(doc.pages), !!parseInt(doc.is_split), values));
        }

        // Measure a header/footer's rendered height at the printed content width
        // (A4 21cm − 2·3cm margins = 15cm). Used to reserve exactly the right
        // space for it per page (must match the editor's HF_GAP_CM = 0.5cm).
        function measureHFHeight(html) {
            var m = document.createElement('div');
            m.className = 'page-header-editor';
            m.style.cssText = 'position:absolute;left:-99999px;top:0;visibility:hidden;width:15cm;';
            m.innerHTML = html || '';
            document.body.appendChild(m);
            var h = m.offsetHeight;
            m.remove();
            return h;
        }

        // The header/footer are pinned to the top/bottom of EVERY printed page
        // via position:fixed (they repeat per page). The table's <thead>/<tfoot>
        // hold spacers sized to the header/footer height (+gap) so the flowing
        // <tbody> never runs under them. <tbody> breaks across A4 pages.
        function buildPrintTable(page, split, values) {
            var cols = split ? 2 : 1;
            var wrap = document.createElement('div');
            wrap.className = 'doc-print';

            if (page.header) {
                var rh = document.createElement('div'); rh.className = 'doc-print-runhdr';
                var hd = document.createElement('div'); hd.className = 'page-header-editor';
                hd.innerHTML = page.header; rh.appendChild(hd); wrap.appendChild(rh);
            }
            if (page.footer) {
                var rf = document.createElement('div'); rf.className = 'doc-print-runftr';
                var fd = document.createElement('div'); fd.className = 'page-footer-editor';
                fd.innerHTML = page.footer; rf.appendChild(fd); wrap.appendChild(rf);
            }

            var table = document.createElement('table');
            table.className = 'doc-print-table' + (split ? ' split-mode' : '');

            if (page.header) {
                var thead = document.createElement('thead');
                var htr = document.createElement('tr');
                var htd = document.createElement('td'); htd.colSpan = cols;
                var hsp = document.createElement('div'); hsp.className = 'doc-print-spacer doc-print-spacer-h';
                hsp.style.height = 'calc(' + measureHFHeight(page.header) + 'px + 0.5cm)';
                htd.appendChild(hsp); htr.appendChild(htd); thead.appendChild(htr);
                table.appendChild(thead);
            }
            if (page.footer) {
                var tfoot = document.createElement('tfoot');
                var ftr = document.createElement('tr');
                var ftd = document.createElement('td'); ftd.colSpan = cols;
                var fsp = document.createElement('div'); fsp.className = 'doc-print-spacer doc-print-spacer-f';
                fsp.style.height = 'calc(' + measureHFHeight(page.footer) + 'px + 0.5cm)';
                ftd.appendChild(fsp); ftr.appendChild(ftd); tfoot.appendChild(ftr);
                table.appendChild(tfoot);
            }

            var tbody = document.createElement('tbody');
            var tr = document.createElement('tr');
            if (split) {
                var tdL = document.createElement('td'); tdL.className = 'doc-print-col';
                var tdR = document.createElement('td'); tdR.className = 'doc-print-col';
                var lq  = document.createElement('div'); tdL.appendChild(lq);
                var rq  = document.createElement('div'); tdR.appendChild(rq);
                tr.appendChild(tdL); tr.appendChild(tdR);
                tbody.appendChild(tr); table.appendChild(tbody);
                var qL = new Quill(lq, { readOnly: true, theme: 'snow', modules: { toolbar: false } });
                var qR = new Quill(rq, { readOnly: true, theme: 'snow', modules: { toolbar: false } });
                if (page.left)  qL.setContents(applyVarsToDelta(page.left,  values), 'silent');
                if (page.right) qR.setContents(applyVarsToDelta(page.right, values), 'silent');
            } else {
                var td = document.createElement('td'); td.className = 'doc-print-cell';
                var sq = document.createElement('div'); td.appendChild(sq);
                tr.appendChild(td); tbody.appendChild(tr); table.appendChild(tbody);
                var qS = new Quill(sq, { readOnly: true, theme: 'snow', modules: { toolbar: false } });
                if (page.single) qS.setContents(applyVarsToDelta(page.single, values), 'silent');
            }
            wrap.appendChild(table);
            return wrap;
        }

        /* ─────────────────────────────────────────────
           printDoc — returns Promise
        ───────────────────────────────────────────── */
        function printDoc(doc, values) {
            buildPrintZone(doc, values);
            var prevTitle = document.title;
            document.title = doc.name;
            document.body.classList.add('printing-doc');
            return new Promise(function (resolve) {
                window.addEventListener('afterprint', function handler() {
                    window.removeEventListener('afterprint', handler);
                    document.body.classList.remove('printing-doc');
                    document.title = prevTitle;
                    document.getElementById('printZone').innerHTML = '';
                    setTimeout(resolve, 400);
                });
                setTimeout(function () { window.print(); }, 150);
            });
        }

        /* ─────────────────────────────────────────────
           printAllDocs — sequential
        ───────────────────────────────────────────── */
        function printAllDocs(docs, values) {
            var i = 0;
            function next() {
                if (i >= docs.length) return;
                var doc = docs[i++];
                printDoc(doc, values).then(next);
            }
            next();
        }

        /* ─────────────────────────────────────────────
           Use modal
        ───────────────────────────────────────────── */
        var useModalConfirmHandler = null;

        function openUseModal(title, varMap, onConfirm, opts) {
            // varMap: { varname: [docName, ...] }.  opts.bracket → label as [name].
            opts = opts || {};
            document.getElementById('useModalTitle').textContent = title;

            var html = '';
            Object.keys(varMap).forEach(function (vname) {
                var usedIn = varMap[vname];
                var hint = usedIn.length ? 'Употребено во: ' + usedIn.join(', ') : '';
                var label = opts.bracket ? '[' + escapeHtml(vname) + ']' : '$' + escapeHtml(vname) + '$';
                html += '<div style="margin-bottom:0.875rem">' +
                    '<label class="var-use-label">' + label + '</label>' +
                    (hint ? '<p class="var-use-hint">' + escapeHtml(hint) + '</p>' : '') +
                    '<input type="text" class="field var-use-input" data-var="' + escapeHtml(vname) + '" style="width:100%" placeholder="Внеси вредност..." autocomplete="off">' +
                    '</div>';
            });
            document.getElementById('useModalFields').innerHTML = html;

            useModalConfirmHandler = onConfirm;
            document.getElementById('useModal').classList.add('open');
            document.getElementById('useModal').removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');

            var first = document.querySelector('.var-use-input');
            if (first) setTimeout(function () { first.focus(); }, 50);
        }

        function closeUseModal() {
            // Move focus out of the modal before hiding it, otherwise setting
            // aria-hidden on an ancestor of the focused button triggers a warning.
            var modal = document.getElementById('useModal');
            if (modal.contains(document.activeElement)) document.activeElement.blur();
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            useModalConfirmHandler = null;
        }

        document.getElementById('useModalClose').addEventListener('click', closeUseModal);
        document.getElementById('useModalCancel').addEventListener('click', closeUseModal);
        document.getElementById('useModal').addEventListener('click', function (e) {
            if (e.target === this) closeUseModal();
        });

        document.getElementById('useModalFields').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') document.getElementById('useModalConfirm').click();
        });

        document.getElementById('useModalConfirm').addEventListener('click', function () {
            var values = {};
            document.querySelectorAll('.var-use-input').forEach(function (inp) {
                values[inp.getAttribute('data-var')] = inp.value;
            });
            // Capture the handler before closeUseModal() nulls it out.
            var handler = useModalConfirmHandler;
            closeUseModal();
            if (handler) handler(values);
        });

        /* ─────────────────────────────────────────────
           Single doc download
        ───────────────────────────────────────────── */
        // Clicking anywhere on a document card (except its buttons/links) opens it,
        // exactly like the "Отвори" button.
        function findDoc(docId) {
            for (var i = 0; i < DOCS.length; i++) {
                if (parseInt(DOCS[i].id) === docId) return DOCS[i];
            }
            return null;
        }

        document.getElementById('docCardsGrid').addEventListener('click', function (e) {
            if (e.target.closest('button, a')) return; // let the real controls handle it
            var card = e.target.closest('.doc-card');
            if (!card) return;
            // Imported files have no editor — clicking the card downloads them.
            if (card.getAttribute('data-kind') === 'imported') {
                var imp = findDoc(parseInt(card.getAttribute('data-doc-id'), 10));
                if (imp) downloadImported(imp);
                return;
            }
            var docId = card.getAttribute('data-doc-id');
            if (docId) window.location.href = 'kreraj-dokument.php?doc_id=' + docId + '&template_id=' + TEMPLATE.id;
        });

        document.getElementById('docCardsGrid').addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-download-doc');
            if (!btn) return;
            var doc = findDoc(parseInt(btn.getAttribute('data-id'), 10));
            if (!doc) return;

            if (doc.kind === 'imported') {
                downloadImported(doc);
                return;
            }

            // Open the same live preview workspace as "Користи шаблон", but
            // scoped to just this document — so it only asks for this doc's
            // variables and downloads this doc alone.
            if (window.DraftWorkspace) {
                window.DraftWorkspace.open(TEMPLATE.id, TEMPLATE.name, { docId: doc.id, docName: doc.name });
            }
        });

        /* ─────────────────────────────────────────────
           Imported-file download (fill [placeholders] → file)
           Opens a two-pane modal: value fields + a live document preview.
           docx-preview (client-side) renders the .docx master with real
           fidelity — table widths, fonts, margins, page layout.
        ───────────────────────────────────────────── */
        var fillState = { doc: null, values: {} };

        function downloadImported(doc) {
            fillState = { doc: doc, values: {} };
            (doc.variables || []).forEach(function (n) { fillState.values[n] = ''; });
            openFillPreview(doc);
        }

        /* -- docx-preview (+ JSZip dependency) lazy loader -- */
        var dpLoading = false, dpQueue = [];
        function loadScript(src, cb) {
            var s = document.createElement('script');
            s.src = src;
            s.onload = function () { cb(); };
            s.onerror = function () { cb(new Error('load ' + src)); };
            document.head.appendChild(s);
        }
        function ensureDocxPreview(cb) {
            if (window.docx && window.JSZip) { cb(); return; }
            dpQueue.push(cb);
            if (dpLoading) return;
            dpLoading = true;
            var done = function (err) { dpLoading = false; var q = dpQueue; dpQueue = []; q.forEach(function (f) { f(err); }); };
            var loadDocx = function (err) {
                if (err) { done(err); return; }
                if (window.docx) { done(); return; }
                loadScript('https://cdn.jsdelivr.net/npm/docx-preview@0.3.5/dist/docx-preview.min.js', done);
            };
            if (window.JSZip) loadDocx();
            else loadScript('https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js', loadDocx);
        }

        function openFillPreview(doc) {
            document.getElementById('fillPreviewTitle').textContent = 'Пополни: ' + doc.name;

            // Fields (left pane).
            var names = doc.variables || [];
            var fieldsHost = document.getElementById('fillPreviewFields');
            if (!names.length) {
                fieldsHost.innerHTML = '<p style="font-size:0.8125rem;color:#a8a29e">Овој документ нема полиња за пополнување.</p>';
            } else {
                fieldsHost.innerHTML = names.map(function (n) {
                    return '<div class="fill-preview-field">' +
                        '<label>[' + escapeHtml(n) + ']</label>' +
                        '<input type="text" class="field fill-ph-input" data-var="' + escapeHtml(n) + '" placeholder="Внеси вредност..." autocomplete="off">' +
                        '</div>';
                }).join('');
            }

            var docHost = document.getElementById('fillPreviewDoc');
            docHost.innerHTML = '<p class="fill-preview-msg">Се вчитува преглед…</p>';

            var modal = document.getElementById('fillPreviewModal');
            modal.classList.add('open');
            modal.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
            setTimeout(function () { var f = fieldsHost.querySelector('.fill-ph-input'); if (f) f.focus(); }, 50);

            // Render the preview.
            ensureDocxPreview(function (err) {
                if (err) { docHost.innerHTML = '<p class="fill-preview-msg">Прегледот не е достапен. Сепак можеш да го пополниш и преземеш документот.</p>'; return; }
                fetch('api/document_api.php?action=master&id=' + encodeURIComponent(doc.id))
                    .then(function (r) { if (!r.ok) throw new Error('fetch'); return r.blob(); })
                    .then(function (blob) {
                        docHost.innerHTML = '';
                        return window.docx.renderAsync(blob, docHost, null, {
                            inWrapper: true,
                            ignoreLastRenderedPageBreak: true,
                            experimental: true,
                            useBase64URL: true
                        });
                    })
                    .then(function () {
                        wrapPlaceholders(docHost);
                        applyAllPreviewValues();
                    })
                    .catch(function () {
                        docHost.innerHTML = '<p class="fill-preview-msg">Прегледот не може да се вчита. Сепак можеш да го пополниш и преземеш документот.</p>';
                    });
            });
        }

        // Wrap every [placeholder] in the rendered preview with a highlight span.
        function wrapPlaceholders(root) {
            var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
            var nodes = [];
            while (walker.nextNode()) nodes.push(walker.currentNode);
            nodes.forEach(function (node) {
                var text = node.nodeValue;
                if (!text || text.indexOf('[') === -1) return;
                var re = /\[([^\[\]]{1,200})\]/g;
                if (!re.test(text)) return;
                re.lastIndex = 0;
                var frag = document.createDocumentFragment(), last = 0, m;
                while ((m = re.exec(text))) {
                    if (m.index > last) frag.appendChild(document.createTextNode(text.slice(last, m.index)));
                    var span = document.createElement('span');
                    span.className = 'ph-mark';
                    span.setAttribute('data-ph', m[1].trim());
                    span.textContent = '[' + m[1] + ']';
                    frag.appendChild(span);
                    last = m.index + m[0].length;
                }
                if (last < text.length) frag.appendChild(document.createTextNode(text.slice(last)));
                node.parentNode.replaceChild(frag, node);
            });
        }

        function applyPreviewValue(name) {
            var val = fillState.values[name] || '';
            var marks = document.getElementById('fillPreviewDoc').querySelectorAll('.ph-mark');
            for (var i = 0; i < marks.length; i++) {
                if (marks[i].getAttribute('data-ph') !== name) continue;
                if (val !== '') { marks[i].textContent = val; marks[i].classList.add('ph-filled'); }
                else { marks[i].textContent = '[' + name + ']'; marks[i].classList.remove('ph-filled'); }
            }
        }
        function applyAllPreviewValues() {
            Object.keys(fillState.values).forEach(applyPreviewValue);
        }

        document.getElementById('fillPreviewFields').addEventListener('input', function (e) {
            var inp = e.target.closest('.fill-ph-input');
            if (!inp) return;
            var name = inp.getAttribute('data-var');
            fillState.values[name] = inp.value;
            applyPreviewValue(name);
        });

        function closeFillPreview() {
            var m = document.getElementById('fillPreviewModal');
            if (m.contains(document.activeElement)) document.activeElement.blur();
            m.classList.remove('open');
            m.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }
        document.getElementById('fillPreviewClose').addEventListener('click', closeFillPreview);
        document.getElementById('fillPreviewCancel').addEventListener('click', closeFillPreview);
        document.getElementById('fillPreviewModal').addEventListener('click', function (e) {
            if (e.target === this) closeFillPreview();
        });
        document.getElementById('fillPreviewConfirm').addEventListener('click', function () {
            var doc = fillState.doc;
            if (!doc) return;
            closeFillPreview();
            submitFilled(doc, fillState.values);
        });

        function filenameFromDisposition(cd) {
            if (!cd) return '';
            var star = /filename\*=UTF-8''([^;]+)/i.exec(cd);
            if (star) { try { return decodeURIComponent(star[1]); } catch (e) {} }
            var plain = /filename="?([^";]+)"?/i.exec(cd);
            return plain ? plain[1] : '';
        }

        function submitFilled(doc, values) {
            var fd = new FormData();
            fd.append('action', 'download_filled');
            fd.append('id', doc.id);
            fd.append('values', JSON.stringify(values));
            fetch('api/document_api.php', { method: 'POST', body: fd })
                .then(function (r) {
                    if (!r.ok) {
                        return r.json().then(function (j) { throw new Error(j.message || 'Грешка при преземање.'); });
                    }
                    var cd = r.headers.get('Content-Disposition') || '';
                    return r.blob().then(function (b) { return { blob: b, name: filenameFromDisposition(cd) }; });
                })
                .then(function (o) {
                    var fname = o.name || (doc.name + '.' + (doc.file_ext || 'docx'));
                    var url = URL.createObjectURL(o.blob);
                    var a = document.createElement('a');
                    a.href = url; a.download = fname;
                    document.body.appendChild(a); a.click(); a.remove();
                    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
                })
                .catch(function (e) { alert(e.message || 'Грешка при преземање.'); });
        }

        /* ─────────────────────────────────────────────
           Import a Word/PDF file
        ───────────────────────────────────────────── */
        var importFile = null;

        function openImportModal() {
            importFile = null;
            document.getElementById('importNameInput').value = '';
            document.getElementById('importDropText').textContent = 'Кликни или повлечи датотека тука';
            document.getElementById('importDrop').classList.remove('has-file');
            var st = document.getElementById('importStatus');
            st.style.display = 'none'; st.textContent = '';
            document.getElementById('importFileInput').value = '';
            document.getElementById('importModal').classList.add('open');
            document.getElementById('importModal').removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
            setTimeout(function () { document.getElementById('importNameInput').focus(); }, 50);
        }
        function closeImportModal() {
            var m = document.getElementById('importModal');
            if (m.contains(document.activeElement)) document.activeElement.blur();
            m.classList.remove('open');
            m.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }

        function pickImportFile(f) {
            if (!f) return;
            if (!/\.docx$/i.test(f.name)) { alert('Дозволени се само .docx датотеки.'); return; }
            importFile = f;
            document.getElementById('importDropText').textContent = f.name;
            document.getElementById('importDrop').classList.add('has-file');
            // Default the name to the file name (without extension) if empty.
            var nameInp = document.getElementById('importNameInput');
            if (!nameInp.value.trim()) nameInp.value = f.name.replace(/\.[^.]+$/, '');
        }

        var btnImport = document.getElementById('btnImportDoc');
        if (btnImport) btnImport.addEventListener('click', openImportModal);
        document.getElementById('importClose').addEventListener('click', closeImportModal);
        document.getElementById('importCancel').addEventListener('click', closeImportModal);
        document.getElementById('importModal').addEventListener('click', function (e) {
            if (e.target === this) closeImportModal();
        });
        document.getElementById('importFileInput').addEventListener('change', function () {
            pickImportFile(this.files && this.files[0]);
        });
        var dropEl = document.getElementById('importDrop');
        ['dragenter', 'dragover'].forEach(function (ev) {
            dropEl.addEventListener(ev, function (e) { e.preventDefault(); dropEl.classList.add('is-drag'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            dropEl.addEventListener(ev, function (e) { e.preventDefault(); dropEl.classList.remove('is-drag'); });
        });
        dropEl.addEventListener('drop', function (e) {
            pickImportFile(e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]);
        });

        document.getElementById('importConfirm').addEventListener('click', function () {
            var name = document.getElementById('importNameInput').value.trim();
            if (!name) { document.getElementById('importNameInput').focus(); return; }
            if (!importFile) { alert('Избери датотека за импортирање.'); return; }

            var btn = this, st = document.getElementById('importStatus');
            btn.disabled = true; btn.textContent = 'Се импортира...';
            st.style.display = ''; st.style.color = '#78716c'; st.textContent = 'Се обработува датотеката…';

            var fd = new FormData();
            fd.append('action', 'import');
            fd.append('template_id', TEMPLATE.id);
            fd.append('name', name);
            fd.append('file', importFile);
            fetch('api/document_api.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) { throw new Error(res.message || 'Грешка при импортирање.'); }
                    closeImportModal();
                    // Append to DOCS and re-render.
                    DOCS.push({
                        id: res.id, name: name, kind: 'imported',
                        is_split: 0, pages: [], variables: res.placeholders || [],
                        file_ext: importFile.name.replace(/^.*\./, '').toLowerCase(),
                        created_by: window.FAKTA_UID, created_by_name: CURRENT_UNAME
                    });
                    renderDocCards();
                })
                .catch(function (e) {
                    st.style.display = ''; st.style.color = '#dc2626';
                    st.textContent = e.message || 'Грешка при импортирање.';
                })
                .finally(function () { btn.disabled = false; btn.textContent = 'Импортирај'; });
        });

        /* ──────────────────────────────
           "Користи шаблон" → global draft workspace
           (defined in js/draft-workspace.js, shared by every page; the
           entered values + inline edits persist across pages)
        ────────────────────────────── */
        function useTemplate() {
            if (!DOCS.length) { alert('Овој шаблон нема документи.'); return; }
            if (window.DraftWorkspace) window.DraftWorkspace.open(TEMPLATE.id, TEMPLATE.name);
        }

        document.getElementById('btnUseTemplate').addEventListener('click', useTemplate);

        /* ─────────────────────────────────────────────
           Delete document
        ───────────────────────────────────────────── */
        var pendingDeleteDocId = null;

        function openDocDeleteModal(id) {
            pendingDeleteDocId = id;
            document.getElementById('docDeleteModal').classList.add('open');
            document.getElementById('docDeleteModal').removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        }

        function closeDocDeleteModal() {
            pendingDeleteDocId = null;
            document.getElementById('docDeleteModal').classList.remove('open');
            document.getElementById('docDeleteModal').setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }

        document.getElementById('docDeleteClose').addEventListener('click', closeDocDeleteModal);
        document.getElementById('docDeleteCancel').addEventListener('click', closeDocDeleteModal);
        document.getElementById('docDeleteModal').addEventListener('click', function (e) {
            if (e.target === this) closeDocDeleteModal();
        });

        document.getElementById('docCardsGrid').addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-delete-doc');
            if (!btn) return;
            openDocDeleteModal(parseInt(btn.getAttribute('data-id'), 10));
        });

        document.getElementById('docDeleteConfirm').addEventListener('click', function () {
            if (!pendingDeleteDocId) return;
            var btn = this;
            var deleteId = pendingDeleteDocId;
            btn.disabled = true;
            btn.textContent = 'Се брише...';

            var params = new URLSearchParams({ action: 'delete', id: deleteId });
            fetch('api/document_api.php', { method: 'POST', body: params })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        closeDocDeleteModal();
                        // Remove from DOCS array
                        DOCS = DOCS.filter(function (d) { return parseInt(d.id) !== deleteId; });
                        // Remove card from DOM
                        var card = document.querySelector('.doc-card[data-doc-id="' + deleteId + '"]');
                        if (card) card.remove();
                        // Show empty if needed
                        if (!DOCS.length) {
                            document.getElementById('docCardsEmpty').style.display = '';
                        }
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

        /* ─────────────────────────────────────────────
           Edit template (name + description)
        ───────────────────────────────────────────── */
        function openEditModal() {
            document.getElementById('tplEditName').value = TEMPLATE.name || '';
            document.getElementById('tplEditDesc').value = TEMPLATE.description || '';
            document.getElementById('tplEditModal').classList.add('open');
            document.getElementById('tplEditModal').removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
            setTimeout(function () { document.getElementById('tplEditName').focus(); }, 50);
        }

        function closeEditModal() {
            var modal = document.getElementById('tplEditModal');
            if (modal.contains(document.activeElement)) document.activeElement.blur();
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }

        var btnEditTpl = document.getElementById('btnEditTemplate');
        if (btnEditTpl) btnEditTpl.addEventListener('click', openEditModal);
        document.getElementById('tplEditClose').addEventListener('click', closeEditModal);
        document.getElementById('tplEditCancel').addEventListener('click', closeEditModal);
        document.getElementById('tplEditModal').addEventListener('click', function (e) {
            if (e.target === this) closeEditModal();
        });
        document.getElementById('tplEditName').addEventListener('keydown', function (e) {
            if (e.key === 'Enter')  { e.preventDefault(); document.getElementById('tplEditConfirm').click(); }
            if (e.key === 'Escape') closeEditModal();
        });

        document.getElementById('tplEditConfirm').addEventListener('click', function () {
            var name = document.getElementById('tplEditName').value.trim();
            var description = document.getElementById('tplEditDesc').value.trim();
            if (!name) { document.getElementById('tplEditName').focus(); return; }

            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Зачувува...';

            var params = new URLSearchParams({ action: 'update', id: TEMPLATE.id, name: name, description: description });
            fetch('api/template_api.php', { method: 'POST', body: params })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        TEMPLATE.name = name;
                        TEMPLATE.description = description;
                        document.getElementById('tplNameHeading').textContent = name;
                        document.title = name + ' – Факта';
                        var desc = document.getElementById('tplDescDisplay');
                        desc.textContent = description;
                        desc.style.display = description ? '' : 'none';
                        closeEditModal();
                    } else {
                        alert(res.message || 'Грешка при зачувување.');
                    }
                })
                .catch(function () { alert('Грешка при поврзување.'); })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = 'Зачувај';
                });
        });

        /* ─────────────────────────────────────────────
           Init
        ───────────────────────────────────────────── */
        document.getElementById('searchDocs').addEventListener('input', renderDocCards);
        renderDocCards();

        // Arrived via "Користи шаблон" from the listings page (?use=1) →
        // open the draft workspace automatically, then strip the flag so a
        // refresh doesn't re-trigger it.
        (function () {
            var params = new URLSearchParams(window.location.search);
            if (params.get('use') !== '1') return;
            params.delete('use');
            var qs = params.toString();
            history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
            setTimeout(useTemplate, 100);
        }());

    }());
    </script>
</body>
</html>
